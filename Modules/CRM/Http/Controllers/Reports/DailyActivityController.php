<?php

namespace Modules\CRM\Http\Controllers\Reports;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\WalletTransaction;
use Morilog\Jalali\Jalalian;

/**
 * گزارش فعالیت روزانه — همه‌ی سفارش‌هایی که در یک روز خاص «اتفاقی»
 * برایشان افتاده، با هایلایت anomalyها.
 *
 * اتفاق = هر کدام از این موارد در روز انتخاب‌شده:
 *   - سفارش جدید ایجاد شد (created_at)
 *   - status تغییر کرد (OrderStatusLog.created_at)
 *   - فاکتور صادر شد (Invoice.issued_at)
 *   - تراکنش کیف‌پول ایجاد شد (WalletTransaction.created_at)
 *
 * Anomaly flag‌ها:
 *   ⚠ سفارش Completed شد ولی فاکتور صادر نشد
 *   ⚠ فاکتور صادر شد ولی in_wallet=false (wallet tx گم‌شده)
 *   ⚠ تکنسین فعال wp_id ندارد
 *   ⚠ سفارش به status قبلی برگشت (مثلاً completed → new)
 */
class DailyActivityController extends Controller
{
    public function index(Request $request)
    {
        // تاریخ پیش‌فرض: امروز
        $dateJ = trim((string) $request->query('date', ''));
        if ($dateJ === '') {
            $dateJ = Jalalian::now()->format('Y/m/d');
        }
        $dateG = $this->jalaliToGregorian($dateJ);
        if (! $dateG) {
            $dateG = now()->toDateString();
            $dateJ = Jalalian::now()->format('Y/m/d');
        }

        $start = Carbon::parse($dateG)->startOfDay();
        $end = Carbon::parse($dateG)->endOfDay();

        // ─── جمع‌آوری orderهای دارای فعالیت در این بازه ──────────
        $orderIds = [];

        // ۱) سفارش‌های ایجاد شده در این روز
        $createdToday = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->pluck('id')->all();
        $orderIds = array_merge($orderIds, $createdToday);

        // ۲) سفارش‌هایی که status لاگ امروز دارند
        $statusLogToday = OrderStatusLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->pluck('order_id')->all();
        $orderIds = array_merge($orderIds, $statusLogToday);

        // ۳) سفارش‌هایی که فاکتور امروز دارند
        $invoiceToday = Invoice::query()
            ->whereBetween('issued_at', [$start, $end])
            ->whereNotNull('order_id')
            ->pluck('order_id')->all();
        $orderIds = array_merge($orderIds, $invoiceToday);

        // ۴) سفارش‌هایی که wallet tx امروز دارند
        $walletToday = WalletTransaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('order_id')
            ->pluck('order_id')->all();
        $orderIds = array_merge($orderIds, $walletToday);

        $orderIds = array_values(array_unique(array_filter($orderIds)));

        if (empty($orderIds)) {
            return view('crm::reports.daily-activity', [
                'dateJ' => $dateJ,
                'orders' => [],
                'summary' => $this->emptySummary(),
            ]);
        }

        // ─── لود سفارش‌ها + همه‌ی events مربوط به این روز ────────
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with([
                'customer:id,first_name,mobile',
                'technician:id,first_name,last_name,firstname_tech,wp_id',
                'creator:id,first_name,last_name,email',
            ])
            ->get();

        $statusLogs = OrderStatusLog::query()
            ->whereIn('order_id', $orderIds)
            ->whereBetween('created_at', [$start, $end])
            ->with('changer:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->get()
            ->groupBy('order_id');

        $invoices = Invoice::query()
            ->whereIn('order_id', $orderIds)
            ->with('creator:id,first_name,last_name,email')
            ->orderBy('issued_at')
            ->get()
            ->groupBy('order_id');

        $walletTxs = WalletTransaction::query()
            ->whereIn('order_id', $orderIds)
            ->whereBetween('created_at', [$start, $end])
            ->with('creator:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->get()
            ->groupBy('order_id');

        // ─── ساخت رکورد به ازای هر سفارش با timeline + anomalies ─
        $records = [];
        $anomCounts = [
            'completed_no_invoice' => 0,
            'invoice_no_wallet' => 0,
            'tech_no_wp_id' => 0,
            'status_regression' => 0,
        ];

        foreach ($orders as $order) {
            $logs = $statusLogs->get($order->id, collect());
            $invs = $invoices->get($order->id, collect());
            $wtxs = $walletTxs->get($order->id, collect());

            // timeline events این روز (همه با هم، مرتب بر اساس زمان)
            $events = [];
            $createdInWindow = $order->created_at && $order->created_at->between($start, $end);
            if ($createdInWindow) {
                $by = $order->creator
                    ? trim($order->creator->full_name ?: $order->creator->email ?: ('کاربر #' . $order->creator->id))
                    : 'سیستم/سینک WP';
                $events[] = [
                    'time' => $order->created_at,
                    'kind' => 'created',
                    'icon' => '🆕',
                    'label' => 'سفارش ایجاد شد',
                    'detail' => 'توسط: ' . $by . ' — وضعیت اولیه: ' . ($order->status?->label() ?? '—'),
                ];
            }
            foreach ($logs as $log) {
                $from = OrderStatus::tryFrom((string) $log->from_status)?->label() ?? ($log->from_status ?? '—');
                $to = OrderStatus::tryFrom((string) $log->to_status)?->label() ?? $log->to_status;
                $by = $log->changer
                    ? trim($log->changer->full_name ?: $log->changer->email ?: ('کاربر #' . $log->changer->id))
                    : 'سیستم';
                $detail = 'توسط: ' . $by;
                if ($log->note) {
                    $detail .= ' — ' . $log->note;
                }
                $events[] = [
                    'time' => $log->created_at,
                    'kind' => 'status',
                    'icon' => '🔄',
                    'label' => "وضعیت: {$from} → {$to}",
                    'detail' => $detail,
                ];
            }
            foreach ($invs as $inv) {
                if ($inv->issued_at && $inv->issued_at->between($start, $end)) {
                    $by = $inv->creator
                        ? trim($inv->creator->full_name ?: $inv->creator->email ?: ('کاربر #' . $inv->creator->id))
                        : 'سیستم';
                    $events[] = [
                        'time' => $inv->issued_at,
                        'kind' => 'invoice',
                        'icon' => '📄',
                        'label' => 'فاکتور صادر شد: ' . $inv->invoice_code,
                        'detail' => 'توسط: ' . $by
                            . ' — سهم شرکت: ' . number_format((int) $inv->company_share)
                            . ' / تکنسین: ' . number_format((int) $inv->tech_share)
                            . ' / in_wallet: ' . ($inv->in_wallet ? 'بله' : 'خیر'),
                    ];
                }
            }
            foreach ($wtxs as $tx) {
                $type = $tx->type instanceof WalletTxType ? $tx->type : WalletTxType::tryFrom((string) $tx->type);
                $by = $tx->creator
                    ? trim($tx->creator->full_name ?: $tx->creator->email ?: ('کاربر #' . $tx->creator->id))
                    : 'سیستم';
                $detail = 'توسط: ' . $by;
                if ($tx->note) {
                    $detail .= ' — ' . $tx->note;
                }
                $events[] = [
                    'time' => $tx->created_at,
                    'kind' => 'wallet',
                    'icon' => '💰',
                    'label' => 'کیف‌پول: ' . ($type?->label() ?? '—')
                        . ' ' . (($tx->amount >= 0 ? '+' : '') . number_format((int) $tx->amount)),
                    'detail' => $detail,
                ];
            }

            usort($events, fn ($a, $b) => $a['time']->getTimestamp() <=> $b['time']->getTimestamp());

            // ─── Anomaly detection ─────────────────────────────────
            $anomalies = [];

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            $movedToCompletedToday = $logs->contains(fn ($l) => $l->to_status === OrderStatus::Completed->value);
            $hasActiveInvoice = $invs->whereNull('superseded_at')->isNotEmpty();

            // سفارش‌های is_legacy_closed عمداً بدون فاکتورند — flag نکن
            if ($movedToCompletedToday && ! $hasActiveInvoice && ! $order->is_legacy_closed) {
                $anomalies[] = ['type' => 'completed_no_invoice', 'msg' => 'Completed شد اما فاکتور ندارد'];
                $anomCounts['completed_no_invoice']++;
            }

            $invoiceIssuedToday = $invs->first(fn ($i) => $i->issued_at && $i->issued_at->between($start, $end));
            if ($invoiceIssuedToday && ! $invoiceIssuedToday->in_wallet && $invoiceIssuedToday->technician_id && $invoiceIssuedToday->company_share > 0) {
                $anomalies[] = ['type' => 'invoice_no_wallet', 'msg' => 'فاکتور دارد ولی wallet tx ندارد (in_wallet=false)'];
                $anomCounts['invoice_no_wallet']++;
            }

            if ($order->technician_id && $order->technician && ! $order->technician->wp_id) {
                $anomalies[] = ['type' => 'tech_no_wp_id', 'msg' => 'تکنسین wp_id ندارد'];
                $anomCounts['tech_no_wp_id']++;
            }

            $statusOrder = [
                'new' => 0, 'coordinated' => 1, 'open' => 2, 'suspended' => 3,
                'completed' => 4, 'cancelled' => 5, 'declined' => 5, 'transit' => 5, 'returned' => 5,
            ];
            foreach ($logs as $log) {
                $fromW = $statusOrder[(string) $log->from_status] ?? -1;
                $toW = $statusOrder[(string) $log->to_status] ?? -1;
                if ($fromW > 0 && $toW > 0 && $toW < $fromW) {
                    $anomalies[] = ['type' => 'status_regression', 'msg' => 'وضعیت برگشت کرد: ' . $log->from_status . ' → ' . $log->to_status];
                    $anomCounts['status_regression']++;
                    break; // فقط یک‌بار گزارش
                }
            }

            $records[] = [
                'order' => $order,
                'events' => $events,
                'anomalies' => $anomalies,
                'last_event_at' => end($events)['time'] ?? $order->created_at,
            ];
        }

        // مرتب‌سازی نزولی بر اساس آخرین event
        usort($records, fn ($a, $b) => $b['last_event_at']->getTimestamp() <=> $a['last_event_at']->getTimestamp());

        $summary = [
            'total_orders' => count($records),
            'created_today' => count(array_unique($createdToday)),
            'status_changes' => $statusLogs->flatten()->count(),
            'invoices_issued' => $invoices->flatten()->filter(fn ($i) => $i->issued_at && $i->issued_at->between($start, $end))->count(),
            'wallet_txs' => $walletTxs->flatten()->count(),
            'anomalies' => $anomCounts,
        ];

        return view('crm::reports.daily-activity', [
            'dateJ' => $dateJ,
            'orders' => $records,
            'summary' => $summary,
        ]);
    }

    protected function emptySummary(): array
    {
        return [
            'total_orders' => 0,
            'created_today' => 0,
            'status_changes' => 0,
            'invoices_issued' => 0,
            'wallet_txs' => 0,
            'anomalies' => [
                'completed_no_invoice' => 0,
                'invoice_no_wallet' => 0,
                'tech_no_wp_id' => 0,
                'status_regression' => 0,
            ],
        ];
    }

    protected function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }
        $latin = strtr($jalaliDate, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $latin = str_replace('-', '/', trim($latin));
        try {
            return Jalalian::fromFormat('Y/m/d', $latin)->toCarbon()->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
