<?php

namespace Modules\CRM\Http\Controllers\Reports;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Concerns\ExportsListToFile;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Morilog\Jalali\Jalalian;

/**
 * گزارش مالی — هم‌سو با list_financial.php پنل WP.
 *
 * ۹ باکس جمع‌بندی + لیست ردیف‌به‌ردیف اسناد مالی در بازهٔ شمسی.
 * منبع داده: crm_invoices + crm_tech_wallet_transactions (UNION).
 *
 * فقط خواندنی است — هیچ نوشتنی در DB ندارد.
 */
class FinancialReportController extends Controller
{
    use ExportsListToFile;

    public function index(Request $request)
    {
        $filters = $this->parseFilters($request);

        $summary = $this->buildSummary($filters);
        $rows = $this->buildRows($filters, paginate: true);

        $technicians = Technician::query()
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech']);

        return view('crm::reports.financial', [
            'filters' => $filters,
            'summary' => $summary,
            'rows' => $rows,
            'technicians' => $technicians,
            'docTypes' => $this->docTypeOptions(),
            'profitOps' => ['gte' => '≥', 'lte' => '≤'],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->parseFilters($request);
        $format = strtolower((string) $request->query('format', 'xlsx'));

        $headers = [
            'تاریخ',
            'نوع سند',
            'شماره سند',
            'تکنسین',
            'مشتری',
            'مبلغ (تومان)',
            'توضیح',
        ];

        $rowsCallback = function () use ($filters) {
            foreach ($this->streamRows($filters) as $row) {
                yield [
                    $row['date_jalali'],
                    $row['type_label'],
                    $row['ref'],
                    $row['tech_name'],
                    $row['customer'],
                    number_format($row['amount']),
                    $row['note'],
                ];
            }
        };

        $base = 'financial-report-'.($filters['from_g'] ?: 'all').'-to-'.($filters['to_g'] ?: 'now');

        return $this->streamSpreadsheet($base, $format, $headers, $rowsCallback);
    }

    // ─── Filters ────────────────────────────────────────────────

    /**
     * @return array{from_j:?string, to_j:?string, from_g:?string, to_g:?string,
     *               technician_id:?int, doc_type:string, doc_no:?string,
     *               mobile:?string, profit_op:?string, profit_val:?int}
     */
    protected function parseFilters(Request $request): array
    {
        $fromJ = trim((string) $request->query('from_date', ''));
        $toJ = trim((string) $request->query('to_date', ''));

        // پیش‌فرض: ماه شمسی جاری
        if ($fromJ === '' && $toJ === '') {
            $now = Jalalian::now();
            $fromJ = $now->format('Y/m/').'01';
            $toJ = $now->format('Y/m/').str_pad((string) $now->getDaysOf($now->getMonth()), 2, '0', STR_PAD_LEFT);
        }

        return [
            'from_j' => $fromJ ?: null,
            'to_j' => $toJ ?: null,
            'from_g' => $this->jalaliToGregorian($fromJ),
            'to_g' => $this->jalaliToGregorian($toJ),
            'technician_id' => (int) $request->query('technician_id') ?: null,
            'doc_type' => (string) $request->query('doc_type', ''),
            'doc_no' => trim((string) $request->query('doc_no', '')) ?: null,
            'mobile' => trim((string) $request->query('mobile', '')) ?: null,
            'profit_op' => in_array($request->query('profit_op'), ['gte', 'lte'], true) ? $request->query('profit_op') : null,
            'profit_val' => is_numeric($request->query('profit_val')) ? (int) $request->query('profit_val') : null,
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

    // ─── Summary boxes ──────────────────────────────────────────

    protected function buildSummary(array $f): array
    {
        // فاکتورها (فقط active — global scope superseded را حذف می‌کند)
        $invoiceQ = Invoice::query();
        $this->applyInvoiceFilters($invoiceQ, $f);
        $invAgg = (clone $invoiceQ)
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(company_share), 0) AS company_share,
                COALESCE(SUM(tech_share), 0) AS tech_share,
                COUNT(*) AS cnt
            ')
            ->first();

        // هزینه‌ها = sum of orders.cost_price برای فاکتورهای همین بازه
        $expenses = (int) (clone $invoiceQ)
            ->join('crm_orders', 'crm_orders.id', '=', 'crm_invoices.order_id')
            ->sum('crm_orders.cost_price');

        // تراکنش‌های کیف‌پول
        $txQ = WalletTransaction::query();
        $this->applyTxFilters($txQ, $f);

        $rewardSum = (int) (clone $txQ)->where('type', WalletTxType::Reward->value)->sum('amount');
        $penaltySum = (int) (clone $txQ)->where('type', WalletTxType::Penalty->value)->sum('amount');
        // adjustment+ → بستانکاری، adjustment− → بدهکاری
        $adjPos = (int) (clone $txQ)->where('type', WalletTxType::Adjustment->value)->where('amount', '>', 0)->sum('amount');
        $adjNeg = (int) (clone $txQ)->where('type', WalletTxType::Adjustment->value)->where('amount', '<', 0)->sum('amount');
        $chargeSum = (int) (clone $txQ)->where('type', WalletTxType::WalletCharge->value)->sum('amount');
        // پرداختِ آنلاینِ مشتری هم ورودیِ کیف‌پولِ تکنسین است و باید در گزارش دیده شود.
        $onlinePaymentSum = (int) (clone $txQ)->where('type', WalletTxType::OnlinePayment->value)->sum('amount');

        $totalInvoice = (int) $invAgg->total_amount;
        $companyShare = (int) $invAgg->company_share;
        $techShare = (int) $invAgg->tech_share;
        $profitPct = $totalInvoice > 0 ? round(($companyShare / $totalInvoice) * 100, 1) : 0;

        // وضعیت حساب تعمیرکار:
        //  - اگر تکنسین فیلتر شده: true_balance همان
        //  - وگرنه: مجموع true_balance همه‌ی تکنسین‌های فعال
        $techStatusLabel = '—';
        $techStatusValue = 0;
        if ($f['technician_id']) {
            $t = Technician::query()
                ->where('id', $f['technician_id'])
                ->withSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share')
                ->first(['id', 'first_name', 'last_name', 'firstname_tech', 'wallet_balance']);
            if ($t) {
                $techStatusValue = (int) $t->true_balance;
            }
        } else {
            $sumWallet = (int) Technician::query()->active()->sum('wallet_balance');
            $sumDebt = (int) Invoice::query()->where('in_wallet', false)->sum('company_share');
            $techStatusValue = $sumWallet - $sumDebt;
        }
        $techStatusLabel = $techStatusValue > 0
            ? number_format($techStatusValue).' تومان (بستانکار)'
            : ($techStatusValue < 0
                ? number_format(abs($techStatusValue)).' تومان (بدهکار)'
                : '۰ تومان (تسویه)');

        return [
            'total_invoice' => $totalInvoice,
            'company_share' => $companyShare,
            'tech_share' => $techShare,
            'reward' => $rewardSum + $adjPos,
            'penalty' => $penaltySum + abs($adjNeg),
            'profit_pct' => $profitPct,
            'expenses' => $expenses,
            'wallet_charge' => $chargeSum,
            'online_payment' => $onlinePaymentSum,
            'tech_status' => $techStatusLabel,
            'tech_status_val' => $techStatusValue,
            'invoice_count' => (int) $invAgg->cnt,
        ];
    }

    // ─── Rows (UNION invoices + wallet transactions) ────────────

    protected function buildRows(array $f, bool $paginate = true)
    {
        $all = $this->collectRows($f);

        // مرتب‌سازی نزولی بر اساس تاریخ
        $all = $all->sortByDesc('date_g')->values();

        if (! $paginate) {
            return $all;
        }

        // pagination دستی روی کالکشن
        $perPage = 25;
        $page = (int) request()->query('page', 1) ?: 1;
        $slice = $all->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /** @return iterable<array> برای stream */
    protected function streamRows(array $f): iterable
    {
        $all = $this->collectRows($f)->sortByDesc('date_g')->values();
        foreach ($all as $row) {
            yield $row;
        }
    }

    protected function collectRows(array $f): \Illuminate\Support\Collection
    {
        $rows = collect();

        // فاکتورها
        if (in_array($f['doc_type'], ['', 'invoice'], true)) {
            $invQ = Invoice::query()
                ->with(['technician:id,first_name,last_name,firstname_tech', 'customer:id,first_name,mobile', 'order:id,order_code']);
            $this->applyInvoiceFilters($invQ, $f);

            foreach ($invQ->get() as $inv) {
                $rows->push([
                    'date_g' => $inv->issued_at,
                    'date_jalali' => $inv->issued_at ? Jalalian::fromDateTime($inv->issued_at)->format('Y/m/d') : '—',
                    'type' => 'invoice',
                    'type_label' => 'فاکتور',
                    'ref' => $inv->invoice_code,
                    'invoice_id' => (int) $inv->id,
                    'technician_id' => $inv->technician_id ? (int) $inv->technician_id : null,
                    'customer_id' => $inv->customer_id ? (int) $inv->customer_id : null,
                    'tech_name' => $inv->technician?->full_name ?? '—',
                    'customer' => $inv->customer?->first_name ?? '—',
                    'amount' => (int) $inv->total_amount,
                    'note' => 'سهم شرکت: '.number_format((int) $inv->company_share)
                                   .' / سهم تکنسین: '.number_format((int) $inv->tech_share),
                ]);
            }
        }

        // تراکنش‌های کیف‌پول
        $allowedTxTypes = $this->txTypesForDocFilter($f['doc_type']);
        if (! empty($allowedTxTypes)) {
            $txQ = WalletTransaction::query()
                ->with(['technician:id,first_name,last_name,firstname_tech'])
                ->whereIn('type', $allowedTxTypes);
            $this->applyTxFilters($txQ, $f);

            foreach ($txQ->get() as $tx) {
                $type = $tx->type instanceof WalletTxType ? $tx->type : WalletTxType::tryFrom((string) $tx->type);
                $rows->push([
                    'date_g' => $tx->created_at,
                    'date_jalali' => $tx->created_at ? Jalalian::fromDateTime($tx->created_at)->format('Y/m/d') : '—',
                    'type' => $type?->value ?? 'tx',
                    'type_label' => $type?->label() ?? '—',
                    'ref' => '#'.$tx->id,
                    'invoice_id' => null,
                    'technician_id' => $tx->technician_id ? (int) $tx->technician_id : null,
                    'customer_id' => null,
                    'tech_name' => $tx->technician?->full_name ?? '—',
                    'customer' => '—',
                    'amount' => (int) $tx->amount,
                    'note' => $tx->note ?? '—',
                ]);
            }
        }

        return $rows;
    }

    // ─── Query helpers ──────────────────────────────────────────

    protected function applyInvoiceFilters($q, array $f): void
    {
        if ($f['from_g']) {
            $q->whereDate('issued_at', '>=', $f['from_g']);
        }
        if ($f['to_g']) {
            $q->whereDate('issued_at', '<=', $f['to_g']);
        }
        if ($f['technician_id']) {
            // صریح کردن نام جدول — این builder بعداً با crm_orders هم join می‌شود
            // (مثلاً برای محاسبهٔ هزینه‌ها) و چون هر دو جدول ستون technician_id
            // دارند، WHERE بدون پیشوند ابهام داشت.
            $q->where('crm_invoices.technician_id', $f['technician_id']);
        }
        if ($f['doc_no']) {
            $q->where('invoice_code', 'like', '%'.$f['doc_no'].'%');
        }
        if ($f['mobile']) {
            $q->whereHas('customer', fn ($c) => $c->where('mobile', 'like', '%'.$f['mobile'].'%'));
        }
    }

    protected function applyTxFilters($q, array $f): void
    {
        if ($f['from_g']) {
            $q->whereDate('created_at', '>=', $f['from_g']);
        }
        if ($f['to_g']) {
            $q->whereDate('created_at', '<=', $f['to_g']);
        }
        if ($f['technician_id']) {
            $q->where('technician_id', $f['technician_id']);
        }
        if ($f['doc_no']) {
            $q->where('id', (int) $f['doc_no']);
        }
    }

    /** نگاشت فیلتر «نوع سند» به انواع wallet transaction. */
    protected function txTypesForDocFilter(string $docType): array
    {
        return match ($docType) {
            '' => [WalletTxType::Reward->value, WalletTxType::Penalty->value, WalletTxType::WalletCharge->value, WalletTxType::OnlinePayment->value, WalletTxType::Adjustment->value, WalletTxType::Payout->value, WalletTxType::Credit->value],
            'reward' => [WalletTxType::Reward->value],
            'penalty' => [WalletTxType::Penalty->value],
            'charge' => [WalletTxType::WalletCharge->value],
            'online_payment' => [WalletTxType::OnlinePayment->value],
            'invoice' => [],
            default => [],
        };
    }

    protected function docTypeOptions(): array
    {
        return [
            '' => '— همه —',
            'invoice' => 'فاکتور',
            'charge' => 'شارژ کیف‌پول',
            'online_payment' => 'پرداخت آنلاین مشتری',
            'reward' => 'بستانکاری',
            'penalty' => 'بدهکاری',
        ];
    }
}
