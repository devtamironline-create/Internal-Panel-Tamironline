<?php

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;

/**
 * تحلیلِ «باشگاه مشتریان» — اکتساب، تبدیل، تقاضا، زمان‌بندی، درآمد و نگه‌داشت.
 *
 * همهٔ محاسبات با چند کوئریِ گروهیِ ثابت انجام می‌شود (بدونِ N+1) و فقط
 * می‌خوانَد (هیچ نوشتنی در دیتابیس نیست). ستون‌های اختیاری با Schema::hasColumn
 * محافظت می‌شوند تا روی هر نسخهٔ اسکیمای پروداکشن امن بماند.
 *
 * نکتهٔ ساعت/روز: فرض بر این است که created_at به وقتِ محلی (Asia/Tehran)
 * ذخیره می‌شود (تنظیمِ پروژه)، پس HOUR()/DAYOFWEEK() محلی‌اند.
 */
class CustomerClubAnalytics
{
    public const RANGES = [
        'all' => 'کل دوره',
        '30' => '۳۰ روز اخیر',
        '90' => '۹۰ روز اخیر',
        '365' => 'یک سال اخیر',
    ];

    /** آستانه‌های سگمنت‌بندی (روز / تعداد سفارش). */
    private const AT_RISK_MIN_DAYS = 90;

    private const AT_RISK_MAX_DAYS = 365;

    private const VIP_MIN_ORDERS = 3;

    /**
     * @param  array{range?:string,source?:string,city_id?:int|string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $range = (string) ($filters['range'] ?? 'all');
        $since = $this->since($range);
        $source = trim((string) ($filters['source'] ?? ''));
        $cityId = (int) ($filters['city_id'] ?? 0);

        return [
            'filters' => ['range' => $range, 'source' => $source, 'city_id' => $cityId ?: ''],
            'ranges' => self::RANGES,
            'sources' => $this->distinctSources(),
            'cities' => $this->cityOptions(),
            'acquisition' => $this->acquisition($since),
            'conversion' => $this->conversion(),
            'demand' => $this->demand($since, $source, $cityId),
            'temporal' => $this->temporal($since, $source, $cityId),
            'revenue' => $this->revenue($since, $source, $cityId),
            'retention' => $this->retention(),
            'segments' => $this->segmentCounts(),
        ];
    }

    // ─────────────────────────── کمک‌ها ───────────────────────────

    private function since(string $range): ?string
    {
        return match ($range) {
            '30' => now()->subDays(30)->toDateTimeString(),
            '90' => now()->subDays(90)->toDateTimeString(),
            '365' => now()->subDays(365)->toDateTimeString(),
            default => null,
        };
    }

    /** عبارتِ مبلغِ سفارش، هم‌راستا با بقیهٔ محاسباتِ مالی. */
    private function amountExpr(): string
    {
        $cols = [];
        foreach (['price_customer', 'total_invoice', 'final_price'] as $c) {
            if (Schema::hasColumn('crm_orders', $c)) {
                $cols[] = "NULLIF({$c}, 0)";
            }
        }

        return $cols === [] ? '0' : 'COALESCE('.implode(', ', $cols).', 0)';
    }

    private function hasSource(): bool
    {
        return Schema::hasColumn('crm_orders', 'source');
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /** عبارتِ «سالِ-ماه» (YYYY-MM) مستقل از درایور. */
    private function monthExpr(string $col): string
    {
        return $this->isSqlite() ? "strftime('%Y-%m', {$col})" : "DATE_FORMAT({$col}, '%Y-%m')";
    }

    /** ساعتِ روز (0..23) مستقل از درایور. */
    private function hourExpr(string $col): string
    {
        return $this->isSqlite() ? "CAST(strftime('%H', {$col}) AS INTEGER)" : "HOUR({$col})";
    }

    /** روزِ هفته به‌سبکِ MySQL (1=یکشنبه..7=شنبه) مستقل از درایور. */
    private function dowExpr(string $col): string
    {
        return $this->isSqlite() ? "(CAST(strftime('%w', {$col}) AS INTEGER) + 1)" : "DAYOFWEEK({$col})";
    }

    /** اختلافِ روزِ بینِ دو تاریخ (a - b) مستقل از درایور. */
    private function daysBetweenExpr(string $a, string $b): string
    {
        return $this->isSqlite() ? "(julianday({$a}) - julianday({$b}))" : "DATEDIFF({$a}, {$b})";
    }

    /** اعمالِ فیلترهای بازه/منبع/شهر روی یک کوئریِ crm_orders. */
    private function scopeOrders($q, ?string $since, string $source = '', int $cityId = 0)
    {
        return $q
            ->when($since, fn ($qq) => $qq->where('crm_orders.created_at', '>=', $since))
            ->when($source !== '' && $this->hasSource(), fn ($qq) => $qq->where('crm_orders.source', $source))
            ->when($cityId > 0, fn ($qq) => $qq->where('crm_orders.city_id', $cityId));
    }

    /** @return array<int, string> */
    private function distinctSources(): array
    {
        if (! $this->hasSource()) {
            return [];
        }

        return DB::table('crm_orders')->select('source')->whereNotNull('source')
            ->where('source', '!=', '')->distinct()->orderBy('source')->pluck('source')->all();
    }

    /** @return array<int, array{id:int,name:string}> */
    private function cityOptions(): array
    {
        if (! Schema::hasTable('crm_cities')) {
            return [];
        }

        return DB::table('crm_orders')
            ->join('crm_cities', 'crm_cities.id', '=', 'crm_orders.city_id')
            ->select('crm_cities.id', 'crm_cities.name')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('crm_cities.id', 'crm_cities.name')
            ->orderByDesc('c')
            ->limit(60)
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    // ─────────────────────────── ۱) اکتساب ───────────────────────────

    /** @return array<string, mixed> */
    private function acquisition(?string $since): array
    {
        $total = (int) DB::table('crm_customers')->whereNull('deleted_at')->count();
        $new = (int) DB::table('crm_customers')->whereNull('deleted_at')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))->count();

        // روندِ ماهانهٔ ثبت‌نام — ۱۲ ماهِ اخیر.
        $from = now()->copy()->subMonths(11)->startOfMonth();
        $rows = DB::table('crm_customers')->whereNull('deleted_at')
            ->where('created_at', '>=', $from->toDateTimeString())
            ->selectRaw($this->monthExpr('created_at').' as ym, COUNT(*) as c')
            ->groupBy('ym')->pluck('c', 'ym');

        $trend = [];
        for ($m = $from->copy(); $m <= now(); $m->addMonth()) {
            $ym = $m->format('Y-m');
            $trend[] = [
                'label' => $this->jMonth($ym),
                'count' => (int) ($rows[$ym] ?? 0),
            ];
        }

        return ['total' => $total, 'new_in_range' => $new, 'trend' => $trend];
    }

    // ─────────────────────────── ۲) تبدیل ───────────────────────────

    /** @return array<string, mixed> */
    private function conversion(): array
    {
        $total = (int) DB::table('crm_customers')->whereNull('deleted_at')->count();
        $withOrders = (int) DB::table('crm_orders')->distinct()->count('customer_id');
        $withOrders = min($withOrders, $total);
        $without = max(0, $total - $withOrders);

        // میانگینِ فاصلهٔ ثبت‌نام تا اولین سفارش (روز).
        $avgDays = null;
        try {
            $first = DB::table('crm_orders')->selectRaw('customer_id, MIN(created_at) as first_at')->groupBy('customer_id');
            $row = DB::table('crm_customers as c')
                ->joinSub($first, 'fo', fn ($j) => $j->on('fo.customer_id', '=', 'c.id'))
                ->whereNull('c.deleted_at')
                ->selectRaw('AVG('.$this->daysBetweenExpr('fo.first_at', 'c.created_at').') as avg_days')
                ->first();
            $avgDays = $row && $row->avg_days !== null ? round((float) $row->avg_days, 1) : null;
        } catch (\Throwable $e) {
        }

        return [
            'total' => $total,
            'with_orders' => $withOrders,
            'without_orders' => $without,
            'rate' => $total > 0 ? round($withOrders / $total * 100, 1) : 0.0,
            'avg_days_to_first_order' => $avgDays,
        ];
    }

    // ─────────────────────────── ۳) تقاضا ───────────────────────────

    /** @return array<string, mixed> */
    private function demand(?string $since, string $source, int $cityId): array
    {
        return [
            'top_devices' => $this->topBy('crm_devices', 'device_id', $since, $source, $cityId),
            'top_brands' => $this->topBy('crm_brands', 'brand_id', $since, $source, $cityId),
            'top_cities' => $this->topBy('crm_cities', 'city_id', $since, $source, $cityId),
            'status_funnel' => $this->statusFunnel($since, $source, $cityId),
            'source_split' => $this->sourceSplit($since, $cityId),
        ];
    }

    /** @return array<int, array{name:string,count:int}> */
    private function topBy(string $table, string $fk, ?string $since, string $source, int $cityId): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return $this->scopeOrders(DB::table('crm_orders'), $since, $source, $cityId)
            ->join($table, "{$table}.id", '=', "crm_orders.{$fk}")
            ->whereNotNull("crm_orders.{$fk}")
            ->selectRaw("{$table}.name as name, COUNT(*) as c")
            ->groupBy("{$table}.name")
            ->orderByDesc('c')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => (string) ($r->name ?: '—'), 'count' => (int) $r->c])
            ->all();
    }

    /** @return array<int, array{label:string,badge:string,count:int,group:string}> */
    private function statusFunnel(?string $since, string $source, int $cityId): array
    {
        $rows = $this->scopeOrders(DB::table('crm_orders'), $since, $source, $cityId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $out = [];
        foreach ($rows as $value => $count) {
            $status = OrderStatus::tryFrom((string) $value);
            $out[] = [
                'label' => $status?->label() ?? (string) $value,
                'badge' => $status?->badgeClass() ?? 'bg-gray-100 text-gray-800',
                'group' => $status?->group() ?? 'waiting',
                'count' => (int) $count,
            ];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /** @return array<int, array{source:string,count:int}> */
    private function sourceSplit(?string $since, int $cityId): array
    {
        if (! $this->hasSource()) {
            return [];
        }

        return $this->scopeOrders(DB::table('crm_orders'), $since, '', $cityId)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'نامشخص') as src, COUNT(*) as c")
            ->groupBy('src')->orderByDesc('c')->get()
            ->map(fn ($r) => ['source' => (string) $r->src, 'count' => (int) $r->c])->all();
    }

    // ─────────────────────────── ۴) زمان‌بندی ───────────────────────────

    /** @return array<string, mixed> */
    private function temporal(?string $since, string $source, int $cityId): array
    {
        // DAYOFWEEK: 1=یکشنبه..7=شنبه → به ترتیبِ هفتهٔ ایران می‌چینیم.
        $rows = $this->scopeOrders(DB::table('crm_orders'), $since, $source, $cityId)
            ->selectRaw($this->dowExpr('created_at').' as dow, '.$this->hourExpr('created_at').' as h, COUNT(*) as c')
            ->groupBy('dow', 'h')->get();

        // ترتیبِ نمایش: شنبه..جمعه
        $order = [7 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه', 3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه'];
        $matrix = [];
        $weekdayTotals = [];
        foreach ($order as $dow => $name) {
            $matrix[$dow] = array_fill(0, 24, 0);
            $weekdayTotals[$dow] = 0;
        }
        $max = 0;
        foreach ($rows as $r) {
            $dow = (int) $r->dow;
            $h = (int) $r->h;
            if (! isset($matrix[$dow])) {
                continue;
            }
            $matrix[$dow][$h] = (int) $r->c;
            $weekdayTotals[$dow] += (int) $r->c;
            $max = max($max, (int) $r->c);
        }

        $heatmap = [];
        foreach ($order as $dow => $name) {
            $heatmap[] = ['weekday' => $name, 'hours' => $matrix[$dow], 'total' => $weekdayTotals[$dow]];
        }

        // روندِ ماهانهٔ سفارش — ۱۲ ماهِ اخیر.
        $from = now()->copy()->subMonths(11)->startOfMonth();
        $monthlyRows = $this->scopeOrders(DB::table('crm_orders'), $from->toDateTimeString(), $source, $cityId)
            ->selectRaw($this->monthExpr('created_at').' as ym, COUNT(*) as c')->groupBy('ym')->pluck('c', 'ym');
        $monthly = [];
        for ($m = $from->copy(); $m <= now(); $m->addMonth()) {
            $ym = $m->format('Y-m');
            $monthly[] = ['label' => $this->jMonth($ym), 'count' => (int) ($monthlyRows[$ym] ?? 0)];
        }

        return ['heatmap' => $heatmap, 'heatmap_max' => $max, 'monthly' => $monthly];
    }

    // ─────────────────────────── ۵) درآمد ───────────────────────────

    /** @return array<string, mixed> */
    private function revenue(?string $since, string $source, int $cityId): array
    {
        $amount = $this->amountExpr();
        $completed = OrderStatus::Completed->value;

        $row = $this->scopeOrders(DB::table('crm_orders'), $since, $source, $cityId)
            ->where('status', $completed)
            ->selectRaw("COUNT(*) as c, SUM({$amount}) as total")->first();

        $count = (int) ($row->c ?? 0);
        $total = (int) ($row->total ?? 0);

        $byDevice = [];
        if (Schema::hasTable('crm_devices')) {
            $byDevice = $this->scopeOrders(DB::table('crm_orders'), $since, $source, $cityId)
                ->where('status', $completed)
                ->join('crm_devices', 'crm_devices.id', '=', 'crm_orders.device_id')
                ->whereNotNull('crm_orders.device_id')
                ->selectRaw("crm_devices.name as name, SUM({$amount}) as total")
                ->groupBy('crm_devices.name')->orderByDesc('total')->limit(10)->get()
                ->map(fn ($r) => ['name' => (string) ($r->name ?: '—'), 'total' => (int) $r->total])->all();
        }

        return [
            'total_revenue' => $total,
            'completed_orders' => $count,
            'aov' => $count > 0 ? (int) round($total / $count) : 0,
            'top_devices_by_revenue' => $byDevice,
        ];
    }

    // ─────────────────────────── ۶) نگه‌داشت + RFM ───────────────────────────

    /** @return array<string, mixed> */
    private function retention(): array
    {
        $amount = $this->amountExpr();
        // یک ردیف به‌ازای هر مشتریِ دارای سفارش: تعداد، آخرین سفارش، مجموعِ مبلغ.
        $rows = DB::table('crm_orders')
            ->selectRaw("customer_id, COUNT(*) as freq, MAX(created_at) as last_at, SUM({$amount}) as monetary")
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')->get();

        $dist = ['1' => 0, '2' => 0, '3' => 0, '4+' => 0];
        $segments = ['champions' => 0, 'loyal' => 0, 'new' => 0, 'at_risk' => 0, 'dormant' => 0, 'others' => 0];
        $repeat = 0;
        $now = now();

        foreach ($rows as $r) {
            $freq = (int) $r->freq;
            $recency = $r->last_at ? $now->diffInDays(Carbon::parse($r->last_at)) : 99999;

            $dist[$freq >= 4 ? '4+' : (string) $freq]++;
            if ($freq >= 2) {
                $repeat++;
            }

            if ($recency > self::AT_RISK_MAX_DAYS) {
                $segments['dormant']++;
            } elseif ($recency > self::AT_RISK_MIN_DAYS) {
                $segments['at_risk']++;
            } elseif ($freq >= self::VIP_MIN_ORDERS && $recency <= 60) {
                $segments['champions']++;
            } elseif ($freq >= 2 && $recency <= 120) {
                $segments['loyal']++;
            } elseif ($freq === 1 && $recency <= 30) {
                $segments['new']++;
            } else {
                $segments['others']++;
            }
        }

        $withOrders = $rows->count();

        return [
            'with_orders' => $withOrders,
            'repeat' => $repeat,
            'one_time' => $withOrders - $repeat,
            'repeat_rate' => $withOrders > 0 ? round($repeat / $withOrders * 100, 1) : 0.0,
            'distribution' => $dist,
            'segments' => $segments,
        ];
    }

    /** شمارشِ سگمنت‌های اکشن‌پذیر (برای کارت‌ها و خروجیِ اکسل). */
    private function segmentCounts(): array
    {
        return [
            'no_order' => $this->noOrderQuery()->count(),
            'vip' => $this->vipQuery()->count(),
            'at_risk' => $this->atRiskQuery()->count(),
        ];
    }

    // ─────────── کوئری‌های سگمنت (هم برای شمارش، هم برای خروجی) ───────────

    /** مشتریانِ بدونِ هیچ سفارش. */
    public function noOrderQuery()
    {
        return DB::table('crm_customers as c')
            ->whereNull('c.deleted_at')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('crm_orders as o')->whereColumn('o.customer_id', 'c.id'))
            ->select('c.id', 'c.first_name', 'c.mobile', 'c.created_at');
    }

    /** مشتریانِ VIP — حداقل VIP_MIN_ORDERS سفارش. */
    public function vipQuery()
    {
        $amount = $this->amountExpr();

        return DB::table('crm_customers as c')
            ->whereNull('c.deleted_at')
            ->joinSub(
                DB::table('crm_orders')->selectRaw("customer_id, COUNT(*) as freq, SUM({$amount}) as monetary")
                    ->whereNotNull('customer_id')->groupBy('customer_id'),
                'o', fn ($j) => $j->on('o.customer_id', '=', 'c.id')
            )
            ->where('o.freq', '>=', self::VIP_MIN_ORDERS)
            ->orderByDesc('o.monetary')
            ->select('c.id', 'c.first_name', 'c.mobile', 'o.freq', 'o.monetary');
    }

    /** مشتریانِ در خطرِ ریزش — آخرین سفارش بینِ ۹۰ تا ۳۶۵ روزِ پیش. */
    public function atRiskQuery()
    {
        $amount = $this->amountExpr();

        return DB::table('crm_customers as c')
            ->whereNull('c.deleted_at')
            ->joinSub(
                DB::table('crm_orders')->selectRaw("customer_id, COUNT(*) as freq, MAX(created_at) as last_at, SUM({$amount}) as monetary")
                    ->whereNotNull('customer_id')->groupBy('customer_id'),
                'o', fn ($j) => $j->on('o.customer_id', '=', 'c.id')
            )
            ->whereRaw('o.last_at < ?', [now()->subDays(self::AT_RISK_MIN_DAYS)->toDateTimeString()])
            ->whereRaw('o.last_at >= ?', [now()->subDays(self::AT_RISK_MAX_DAYS)->toDateTimeString()])
            ->orderByDesc('o.monetary')
            ->select('c.id', 'c.first_name', 'c.mobile', 'o.freq', 'o.last_at', 'o.monetary');
    }

    /** برچسبِ ماهِ شمسی از 'Y-m'. */
    private function jMonth(string $ym): string
    {
        try {
            return \Morilog\Jalali\Jalalian::fromCarbon(Carbon::parse($ym.'-01'))->format('Y/m');
        } catch (\Throwable $e) {
            return $ym;
        }
    }
}
