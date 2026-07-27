<?php

namespace Modules\Technician\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Technician;

/**
 * تجمیعِ آمارِ داشبوردِ مدیریتیِ تکنسین‌ها.
 *
 * کلِ سفارش‌ها با «یک» کوئریِ گروهی خوانده می‌شود (technician_id × status)
 * و بقیهٔ محاسبات در PHP انجام می‌گیرد؛ پس تعدادِ کوئری ثابت است و با
 * افزایشِ تکنسین‌ها N+1 نمی‌شود.
 */
class TechnicianDashboardService
{
    /** بازه‌های زمانیِ قابلِ انتخاب. */
    public const RANGES = [
        'all' => 'کل دوره',
        '30' => '۳۰ روز اخیر',
        '90' => '۹۰ روز اخیر',
        '365' => 'یک سال اخیر',
    ];

    /** ستون‌های قابلِ مرتب‌سازی. */
    public const SORTS = [
        'orders' => 'بیشترین سفارش',
        'in_progress' => 'بیشترین کار در جریان',
        'completed' => 'بیشترین تکمیل‌شده',
        'completion_rate' => 'بالاترین نرخ تکمیل',
        'revenue' => 'بیشترین گردش مالی',
        'last_order' => 'تازه‌ترین سفارش',
        'profile' => 'کامل‌ترین پروفایل',
    ];

    /**
     * @param  array{range?:string,status?:string,province?:string,q?:string,sort?:string,only_with_orders?:bool}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $range = (string) ($filters['range'] ?? 'all');
        $since = $this->since($range);

        $orderStats = $this->orderStatsByTechnician($since);

        $technicians = Technician::query()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->search($v))
            ->when(($filters['province'] ?? '') !== '', fn ($q) => $q->where('province', $filters['province']))
            ->when(($filters['status'] ?? '') === 'active', fn ($q) => $q->where('status', 'active'))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where('status', '!=', 'active'))
            ->when(($filters['status'] ?? '') === 'ready', fn ($q) => $q->where('status', 'active')->where('ready_for_delivery', true))
            ->get();

        $rows = $technicians
            ->map(fn (Technician $tech) => $this->row($tech, $orderStats[$tech->id] ?? null))
            ->when(! empty($filters['only_with_orders']), fn (Collection $c) => $c->where('total', '>', 0))
            ->pipe(fn (Collection $c) => $this->sort($c, (string) ($filters['sort'] ?? 'orders')))
            ->values();

        return [
            'rows' => $rows,
            'summary' => $this->summary($rows, $orderStats),
            'statusTotals' => $this->statusTotals($orderStats),
            'provinces' => Technician::query()
                ->whereNotNull('province')->where('province', '!=', '')
                ->distinct()->orderBy('province')->pluck('province'),
            'range' => $range,
            'unassignedOrders' => $this->unassignedOrders($since),
        ];
    }

    /**
     * یک کوئری: تعداد و گردشِ مالیِ هر (تکنسین × وضعیت) + تاریخِ آخرین سفارش.
     *
     * @return array<int, array{by_status: array<string,int>, total: int, revenue: int, last_at: ?string}>
     */
    private function orderStatsByTechnician(?string $since): array
    {
        // اولویتِ مبلغ هم‌راستا با CommissionCalculator:
        // price_customer ← total_invoice ← final_price
        $amount = 'COALESCE(NULLIF(price_customer, 0), NULLIF(total_invoice, 0), NULLIF(final_price, 0), 0)';

        $rows = DB::table('crm_orders')
            ->selectRaw('technician_id, status, COUNT(*) as cnt, MAX(created_at) as last_at, SUM('.$amount.') as revenue')
            ->whereNotNull('technician_id')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->groupBy('technician_id', 'status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->technician_id;
            $out[$id] ??= ['by_status' => [], 'total' => 0, 'revenue' => 0, 'last_at' => null];

            $out[$id]['by_status'][(string) $row->status] = (int) $row->cnt;
            $out[$id]['total'] += (int) $row->cnt;
            $out[$id]['revenue'] += (int) $row->revenue;

            if ($row->last_at && (! $out[$id]['last_at'] || $row->last_at > $out[$id]['last_at'])) {
                $out[$id]['last_at'] = (string) $row->last_at;
            }
        }

        return $out;
    }

    /**
     * ردیفِ آمادهٔ نمایشِ یک تکنسین.
     *
     * @param  array<string, mixed>|null  $stats
     * @return array<string, mixed>
     */
    private function row(Technician $tech, ?array $stats): array
    {
        $byStatus = $stats['by_status'] ?? [];
        $groups = ['in_progress' => 0, 'waiting' => 0, 'finished' => 0];
        $labelled = [];

        foreach ($byStatus as $value => $count) {
            $status = OrderStatus::tryFrom((string) $value);
            if (! $status) {
                // وضعیتِ ناشناخته (دادهٔ قدیمیِ WP) — در «در انتظار» شمرده می‌شود
                // تا از مجموع حذف نشود.
                $groups['waiting'] += $count;
                $labelled[] = ['value' => (string) $value, 'label' => (string) $value, 'badge' => 'bg-gray-100 text-gray-800', 'count' => $count];

                continue;
            }
            $groups[$status->group()] += $count;
            $labelled[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'badge' => $status->badgeClass(),
                'count' => $count,
            ];
        }

        usort($labelled, fn ($a, $b) => $b['count'] <=> $a['count']);

        $total = (int) ($stats['total'] ?? 0);
        $completed = (int) ($byStatus[OrderStatus::Completed->value] ?? 0);
        $cancelled = (int) ($byStatus[OrderStatus::Cancelled->value] ?? 0)
            + (int) ($byStatus[OrderStatus::Declined->value] ?? 0);

        return [
            'technician' => $tech,
            'total' => $total,
            'groups' => $groups,
            'statuses' => $labelled,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'open' => $groups['in_progress'] + $groups['waiting'],
            'completion_rate' => $total > 0 ? (int) round($completed / $total * 100) : null,
            'revenue' => (int) ($stats['revenue'] ?? 0),
            'last_at' => $stats['last_at'] ?? null,
            'profile' => $this->profileCompleteness($tech),
        ];
    }

    /**
     * درصدِ کاملی پروفایل + فهرستِ موارد ناقص (برای ستونِ «وضعیت پروفایل»).
     *
     * @return array{percent: int, missing: list<string>}
     */
    private function profileCompleteness(Technician $tech): array
    {
        $checks = [
            'نام و نام خانوادگی' => filled($tech->first_name),
            'موبایل' => filled($tech->mobile),
            'کد ملی' => filled($tech->national_code),
            'استان' => filled($tech->province),
            'آدرس' => filled($tech->address),
            'تخصص' => filled($tech->specialty) || filled($tech->type_tech),
            'درصد کمیسیون' => (int) ($tech->percent ?? 0) > 0,
            'عکس پرسنلی' => filled($tech->img_personal),
            'حساب کاربری' => filled($tech->user_id),
        ];

        $missing = array_keys(array_filter($checks, fn ($ok) => ! $ok));
        $percent = (int) round((count($checks) - count($missing)) / max(1, count($checks)) * 100);

        return ['percent' => $percent, 'missing' => array_values($missing)];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'in_progress' => $rows->sortByDesc(fn ($r) => $r['groups']['in_progress']),
            'completed' => $rows->sortByDesc('completed'),
            'completion_rate' => $rows->sortByDesc(fn ($r) => [$r['completion_rate'] ?? -1, $r['total']]),
            'revenue' => $rows->sortByDesc('revenue'),
            'last_order' => $rows->sortByDesc(fn ($r) => $r['last_at'] ?? ''),
            'profile' => $rows->sortByDesc(fn ($r) => $r['profile']['percent']),
            // پیش‌فرضِ خواستهٔ مدیریت: بیشترین سفارش → کمترین
            default => $rows->sortByDesc(fn ($r) => [$r['total'], $r['completed']]),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $orderStats
     * @return array<string, mixed>
     */
    private function summary(Collection $rows, array $orderStats): array
    {
        $totalOrders = (int) $rows->sum('total');
        $withOrders = $rows->where('total', '>', 0)->count();

        return [
            'technicians' => $rows->count(),
            'active' => $rows->filter(fn ($r) => $r['technician']->status === 'active')->count(),
            'ready' => $rows->filter(fn ($r) => $r['technician']->status === 'active' && $r['technician']->ready_for_delivery)->count(),
            'with_orders' => $withOrders,
            'idle' => $rows->count() - $withOrders,
            'orders' => $totalOrders,
            'in_progress' => (int) $rows->sum(fn ($r) => $r['groups']['in_progress']),
            'waiting' => (int) $rows->sum(fn ($r) => $r['groups']['waiting']),
            'finished' => (int) $rows->sum(fn ($r) => $r['groups']['finished']),
            'completed' => (int) $rows->sum('completed'),
            'revenue' => (int) $rows->sum('revenue'),
            'avg_orders' => $withOrders > 0 ? round($totalOrders / $withOrders, 1) : 0,
            'incomplete_profiles' => $rows->filter(fn ($r) => $r['profile']['percent'] < 100)->count(),
        ];
    }

    /**
     * تفکیکِ کلِ سفارش‌ها به‌ازای هر وضعیت (برای نوارِ وضعیتِ بالای صفحه).
     *
     * @param  array<int, array<string, mixed>>  $orderStats
     * @return list<array{label: string, badge: string, count: int, value: string}>
     */
    private function statusTotals(array $orderStats): array
    {
        $totals = [];
        foreach ($orderStats as $stats) {
            foreach ($stats['by_status'] as $value => $count) {
                $totals[$value] = ($totals[$value] ?? 0) + $count;
            }
        }
        arsort($totals);

        $out = [];
        foreach ($totals as $value => $count) {
            $status = OrderStatus::tryFrom((string) $value);
            $out[] = [
                'value' => (string) $value,
                'label' => $status?->label() ?? (string) $value,
                'badge' => $status?->badgeClass() ?? 'bg-gray-100 text-gray-800',
                'count' => $count,
            ];
        }

        return $out;
    }

    /** سفارش‌های بدونِ تکنسین در همان بازه — نشانهٔ صفِ تخصیص‌نیافته. */
    private function unassignedOrders(?string $since): int
    {
        return (int) DB::table('crm_orders')
            ->whereNull('technician_id')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->count();
    }

    private function since(string $range): ?string
    {
        return match ($range) {
            '30' => now()->subDays(30)->toDateTimeString(),
            '90' => now()->subDays(90)->toDateTimeString(),
            '365' => now()->subDays(365)->toDateTimeString(),
            default => null,
        };
    }
}
