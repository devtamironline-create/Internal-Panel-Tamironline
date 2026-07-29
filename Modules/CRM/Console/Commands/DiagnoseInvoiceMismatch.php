<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\WalletTransaction;

/**
 * چرا عددِ «فاکتور سفارش» با فاکتورِ صادرشده نمی‌خواند؟
 *
 * دو عددِ کنارِ هم روی صفحهٔ سفارش از دو منبعِ متفاوت می‌آیند:
 *
 *   • کارتِ «فاکتور سفارش» → Order::financialSummary() که *همین حالا* از
 *     روی price_customer و درصدِ *فعلیِ* تکنسین حساب می‌شود.
 *   • فاکتورِ صادرشده → یک snapshot که در لحظهٔ صدور از
 *     CommissionCalculator گرفته شده و درصد را در تاریخِ completed_at
 *     می‌خواند.
 *
 * تا وقتی هیچ‌چیز عوض نشود این دو یکی‌اند. ولی چند مسیرِ قانونی می‌توانند
 * جدایشان کنند: صدورِ دستیِ فاکتور پیش از نهایی‌شدنِ مبلغ، تکمیلِ سفارش
 * به‌صورت «پیش‌نویس» وقتی فاکتور از قبل هست، یا تغییرِ درصدِ تکنسین بعد
 * از صدور. این دستور می‌گوید کدامش بوده.
 */
class DiagnoseInvoiceMismatch extends Command
{
    protected $signature = 'crm:diagnose:invoice-mismatch
                            {--order= : شناسه یا کدِ یک سفارشِ مشخص}
                            {--limit=50 : سقفِ ردیف در گزارشِ کلی}';

    protected $description = 'مقایسهٔ مبلغِ سفارش با فاکتورِ صادرشده و نشان‌دادنِ علتِ مغایرت';

    public function handle(): int
    {
        if ($order = $this->option('order')) {
            return $this->detail((string) $order);
        }

        return $this->sweep();
    }

    /** گزارشِ کلی — همهٔ سفارش‌هایی که عددشان با فاکتورشان نمی‌خواند. */
    private function sweep(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $rows = [];
        $scanned = 0;

        Order::query()
            ->whereHas('invoices')
            ->with(['technician', 'invoices'])
            ->orderByDesc('id')
            ->chunkById(200, function ($orders) use (&$rows, &$scanned, $limit) {
                foreach ($orders as $order) {
                    $scanned++;
                    $diff = self::mismatchOf($order);
                    if ($diff === null) {
                        continue;
                    }

                    $rows[] = $diff + ['order' => $order->order_code ?: '#'.$order->id];

                    if (count($rows) >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $this->newLine();
        if (empty($rows)) {
            $this->info("✓ در {$scanned} سفارشِ بررسی‌شده مغایرتی نبود.");

            return self::SUCCESS;
        }

        $this->warn(count($rows).' سفارش با مغایرت (از '.$scanned.' بررسی‌شده):');
        $this->table(
            ['سفارش', 'مبلغ سفارش', 'مبلغ فاکتور', 'اختلاف', 'سهم تکنسین (سفارش/فاکتور)', 'علت'],
            array_map(fn ($r) => [
                $r['order'],
                number_format($r['order_total']),
                number_format($r['invoice_total']),
                number_format($r['order_total'] - $r['invoice_total']),
                number_format($r['order_tech']).' / '.number_format($r['invoice_tech']),
                $r['reason'],
            ], $rows)
        );

        $this->newLine();
        $this->line('برای ریزِ یک مورد: <fg=cyan>php artisan crm:diagnose:invoice-mismatch --order=CODE</>');

        return self::SUCCESS;
    }

    /** ریزِ یک سفارش — با تایم‌لاین تا معلوم شود چه‌وقت جدا شدند. */
    private function detail(string $ref): int
    {
        $order = Order::query()
            ->when(is_numeric($ref), fn ($q) => $q->where('id', (int) $ref))
            ->when(! is_numeric($ref), fn ($q) => $q->where('order_code', $ref))
            ->with(['technician', 'statusLogs'])
            ->first();

        if (! $order) {
            $this->error('سفارش پیدا نشد.');

            return self::FAILURE;
        }

        $fin = $order->financialSummary();

        $this->newLine();
        $this->line('<fg=cyan>── سفارش '.($order->order_code ?: '#'.$order->id).' ──</>');
        $this->table(['فیلد', 'مقدار'], [
            ['وضعیت', (string) ($order->status?->value ?? '—')],
            ['پیش‌نویس (save_as_draft)', $order->save_as_draft ? 'بله ⚠' : 'خیر'],
            ['price_customer', number_format((int) $order->price_customer)],
            ['cost_price', number_format((int) $order->cost_price)],
            ['total_invoice', number_format((int) $order->total_invoice)],
            ['final_price', number_format((int) $order->final_price)],
            ['completed_at', (string) ($order->completed_at ?? '—')],
            ['به‌روزرسانی', (string) ($order->updated_at ?? '—')],
            ['legacy_closed', $order->is_legacy_closed ? 'بله' : 'خیر'],
        ]);

        $this->newLine();
        $this->line('<fg=cyan>── کارتِ «فاکتور سفارش» (محاسبهٔ زنده) ──</>');
        $this->table(['جمع کل', 'سهم تکنسین', 'سهم شرکت', 'درصد', 'روش'], [[
            number_format($fin['customer_total']),
            number_format($fin['tech_share']),
            number_format($fin['company_share']),
            $fin['percent'].'%',
            $fin['calc_type'] ?? '—',
        ]]);

        $this->newLine();
        $this->line('<fg=cyan>── فاکتورهای این سفارش (شاملِ باطل‌شده‌ها) ──</>');

        $invoices = Invoice::withoutGlobalScope('active')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->line('  هیچ فاکتوری صادر نشده.');
        } else {
            $this->table(
                ['کد', 'مبلغ', 'سهم تکنسین', 'سهم شرکت', 'درصد', 'صدور', 'وضعیت'],
                $invoices->map(fn ($i) => [
                    $i->invoice_code,
                    number_format((int) $i->total_amount),
                    number_format((int) $i->tech_share),
                    number_format((int) $i->company_share),
                    $i->commission_percent.'%',
                    (string) ($i->issued_at ?? '—'),
                    $i->superseded_at ? 'باطل‌شده' : $i->status,
                ])->all()
            );
        }

        // درصدِ تکنسین: «الان» در برابر «لحظهٔ تکمیل».
        if ($tech = $order->technician) {
            $atCompletion = $tech->percentProfileAt($order->completed_at ?? now());
            $nowPercent = (int) ($tech->percent ?? 0);

            $this->newLine();
            $this->line('<fg=cyan>── درصدِ تکنسین ──</>');
            $this->line('  الان: '.$nowPercent.'%   ·   در لحظهٔ تکمیل: '.$atCompletion['percent'].'%');
            if ($nowPercent !== (int) $atCompletion['percent']) {
                $this->warn('  ⚠ درصد بعد از تکمیل عوض شده — همین یک‌تنه سهم‌ها را جدا می‌کند.');
            }
        }

        $this->newLine();
        $this->line('<fg=cyan>── تراکنش‌های کیف‌پول این سفارش ──</>');
        $txs = WalletTransaction::where('order_id', $order->id)->orderBy('id')->get();
        if ($txs->isEmpty()) {
            $this->line('  تراکنشی ثبت نشده.');
        } else {
            $this->table(
                ['نوع', 'مبلغ', 'فاکتور', 'زمان', 'شرح'],
                $txs->map(fn ($t) => [
                    $t->type,
                    number_format((int) $t->amount),
                    $t->invoice_id ?: '—',
                    (string) ($t->created_at ?? '—'),
                    mb_substr((string) $t->note, 0, 40),
                ])->all()
            );
        }

        $this->newLine();
        $this->line('<fg=cyan>── تایم‌لاین وضعیت ──</>');
        $this->table(
            ['زمان', 'به وضعیت', 'عامل', 'یادداشت'],
            $order->statusLogs->map(fn ($l) => [
                (string) ($l->created_at ?? '—'),
                (string) $l->to_status,
                ($l->actorRoleLabel() ?? '—').' '.($l->actor_name ?? ''),
                mb_substr((string) $l->note, 0, 40),
            ])->all()
        );

        $diff = self::mismatchOf($order);
        $this->newLine();
        if ($diff === null) {
            $this->info('✓ مغایرتی نیست.');
        } else {
            $this->error('⚠ علتِ محتمل: '.$diff['reason']);
        }

        return self::SUCCESS;
    }

    /**
     * مغایرتِ یک سفارش، یا null اگر نبود.
     *
     * @return array{order_total:int, invoice_total:int, order_tech:int, invoice_tech:int, reason:string}|null
     */
    public static function mismatchOf(Order $order): ?array
    {
        $invoice = $order->invoices->firstWhere('superseded_at', null)
            ?? $order->invoices->last();

        if (! $invoice) {
            return null;
        }

        $fin = $order->financialSummary();

        $orderTotal = (int) $fin['customer_total'];
        $invoiceTotal = (int) $invoice->total_amount;
        $orderTech = (int) $fin['tech_share'];
        $invoiceTech = (int) $invoice->tech_share;

        if ($orderTotal === $invoiceTotal && $orderTech === $invoiceTech) {
            return null;
        }

        return [
            'order_total' => $orderTotal,
            'invoice_total' => $invoiceTotal,
            'order_tech' => $orderTech,
            'invoice_tech' => $invoiceTech,
            'reason' => self::reasonFor($order, $invoice, $orderTotal, $invoiceTotal),
        ];
    }

    private static function reasonFor(Order $order, Invoice $invoice, int $orderTotal, int $invoiceTotal): string
    {
        if ($order->is_legacy_closed) {
            return 'سفارشِ legacy — اعداد از لاگ WP خوانده می‌شوند';
        }

        if ($orderTotal !== $invoiceTotal) {
            if ($order->save_as_draft) {
                return 'تکمیل به‌صورت «پیش‌نویس» — فاکتور بازصادر نشد';
            }

            if ($invoice->issued_at && $order->updated_at
                && $invoice->issued_at->lessThan($order->updated_at)) {
                return 'مبلغِ سفارش بعد از صدورِ فاکتور تغییر کرده';
            }

            return 'مبلغِ سفارش با مبلغِ فاکتور یکی نیست';
        }

        $tech = $order->technician;
        if ($tech && (int) ($tech->percent ?? 0) !== (int) $invoice->commission_percent) {
            return 'درصدِ تکنسین بعد از صدورِ فاکتور عوض شده';
        }

        return 'سهم‌ها با هم نمی‌خوانند';
    }
}
