<?php

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;

/**
 * تحلیلِ «باشگاه مشتریان» — فقط مشتریانِ «واقعیِ اپ».
 *
 * «مشتریِ اپ» = رکوردی که موبایلش تأیید شده (`mobile_verified_at` پر است؛ یعنی
 * از اپ/سایت با OTP وارد شده) و بلاک/حذف‌نشده است. این همان تعریفی است که
 * داشبوردِ «مدیریت اپ» هم استفاده می‌کند. مشتریانِ واردشده از وردپرس/تلفنیِ
 * قدیمی `mobile_verified_at = null` دارند و در این آمار شمرده نمی‌شوند — به
 * همین دلیل «کل مشتریان» نباید کلِ جدولِ ۱۱۸هزارتایی باشد.
 *
 * همهٔ بخش‌ها (اکتساب/تبدیل/تکرار/VIP/ریزش/RFM + روند/تقاضا/ساعاتِ اوج) به
 * مشتریانِ اپ و سفارش‌های آن‌ها محدود می‌شوند. فقط می‌خوانَد؛ توابعِ MySQL
 * به‌شکلِ driver-aware اجرا می‌شوند تا روی SQLite (تست) هم کار کنند.
 */
class CustomerClubAnalytics
{
    public const WINDOWS = [1 => '۱ ماه', 3 => '۳ ماه', 6 => '۶ ماه', 12 => 'یک سال'];

    private const CHURN_DAYS = 90;

    private const VIP_LIMIT = 30;

    private const ACTIVE_DAYS = 90;

    private const UNSPECIFIED_BRANDS = ['سایر', 'متفرقه', 'نامشخص', 'سایر برندها'];

    public const RFM_LABELS = [
        'champions' => 'قهرمانان',
        'loyal' => 'وفادار',
        'potential_loyalist' => 'وفادارِ بالقوه',
        'new' => 'تازه‌وارد',
        'promising' => 'امیدوارکننده',
        'need_attention' => 'نیازمندِ توجه',
        'about_to_sleep' => 'در آستانهٔ خواب',
        'at_risk' => 'در خطرِ ریزش',
        'cant_lose' => 'نباید از دست داد',
        'hibernating' => 'خفته',
        'lost' => 'از‌دست‌رفته',
    ];

    /**
     * @param  array{window?:int|string,city_id?:int|string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $window = (int) ($filters['window'] ?? 3);
        if (! isset(self::WINDOWS[$window])) {
            $window = 3;
        }
        $cityId = (int) ($filters['city_id'] ?? 0);
        $since = now()->copy()->subMonthsNoOverflow($window)->startOfDay();

        return [
            'filters' => ['window' => $window, 'city_id' => $cityId ?: ''],
            'windows' => self::WINDOWS,
            'cities' => $this->cityOptions(),
            'overview' => $this->overview(),
            'acquisition_trend' => $this->registrationTrend($window, $since),
            'order_trend' => $this->orderTrend($window, $since, $cityId),
            'demand' => $this->demand($since, $cityId),
            'heatmap' => $this->heatmap($since, $cityId),
            'rfm' => $this->rfm(),
            'segments' => $this->segmentCounts(),
            'window' => $window,
        ];
    }

    // ─────────────────────────── اسکوپِ «مشتریِ اپ» ───────────────────────────

    private function hasBlock(): bool
    {
        return Schema::hasColumn('crm_customers', 'is_blocked');
    }

    private function hasVerified(): bool
    {
        return Schema::hasColumn('crm_customers', 'mobile_verified_at');
    }

    /** فیلترِ مشتریِ اپ: تأییدشده (OTP) + بدونِ بلاک + بدونِ حذف. */
    private function appCustomers($q, string $alias = 'crm_customers')
    {
        $q->whereNull("{$alias}.deleted_at");
        if ($this->hasBlock()) {
            $q->where(fn ($w) => $w->where("{$alias}.is_blocked", false)->orWhereNull("{$alias}.is_blocked"));
        }
        if ($this->hasVerified()) {
            $q->whereNotNull("{$alias}.mobile_verified_at");
        }

        return $q;
    }

    /** سفارش‌هایی که مشتری‌شان «مشتریِ اپ» است. */
    private function appOrders()
    {
        return $this->appCustomers(
            DB::table('crm_orders as o')->join('crm_customers as c', 'c.id', '=', 'o.customer_id'),
            'c'
        );
    }

    private function amountExpr(string $prefix = 'o.'): string
    {
        $cols = [];
        foreach (['price_customer', 'total_invoice', 'final_price'] as $c) {
            if (Schema::hasColumn('crm_orders', $c)) {
                $cols[] = "NULLIF({$prefix}{$c}, 0)";
            }
        }

        return $cols === [] ? '0' : 'COALESCE('.implode(', ', $cols).', 0)';
    }

    /** آمارِ هر مشتریِ اپِ دارایِ سفارش. */
    private function perCustomer()
    {
        $amount = $this->amountExpr('o.');

        return $this->appOrders()
            ->groupBy('o.customer_id')
            ->selectRaw("o.customer_id, COUNT(*) as freq, MAX(o.created_at) as last_at, SUM({$amount}) as monetary");
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function hourExpr(string $col): string
    {
        return $this->isSqlite() ? "CAST(strftime('%H', {$col}) AS INTEGER)" : "HOUR({$col})";
    }

    private function dowExpr(string $col): string
    {
        return $this->isSqlite() ? "(CAST(strftime('%w', {$col}) AS INTEGER) + 1)" : "DAYOFWEEK({$col})";
    }

    /** @return array<int, array{id:int,name:string}> */
    private function cityOptions(): array
    {
        if (! Schema::hasTable('crm_cities')) {
            return [];
        }

        return $this->appOrders()
            ->join('crm_cities', 'crm_cities.id', '=', 'o.city_id')
            ->selectRaw('crm_cities.id, crm_cities.name, COUNT(*) as c')
            ->groupBy('crm_cities.id', 'crm_cities.name')->orderByDesc('c')->limit(60)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])->all();
    }

    // ─────────────────────────── نمای کلی ───────────────────────────

    /** @return array<string, mixed> */
    private function overview(): array
    {
        $total = (int) $this->appCustomers(DB::table('crm_customers'))->count();
        $withOrders = (int) $this->appOrders()->distinct()->count('o.customer_id');
        $withOrders = min($withOrders, $total);

        $repeat = (int) DB::query()->fromSub($this->perCustomer(), 'pc')->where('pc.freq', '>=', 2)->count();

        $active = (int) $this->appOrders()
            ->where('o.created_at', '>=', now()->subDays(self::ACTIVE_DAYS)->toDateTimeString())
            ->distinct()->count('o.customer_id');

        return [
            'total_customers' => $total,
            'with_orders' => $withOrders,
            'without_orders' => max(0, $total - $withOrders),
            'conversion_rate' => $total > 0 ? round($withOrders / $total * 100, 1) : 0.0,
            'repeat' => $repeat,
            'repeat_rate' => $withOrders > 0 ? round($repeat / $withOrders * 100, 1) : 0.0,
            'active_customers' => $active,
        ];
    }

    // ─────────────────────────── روند (پنجره‌ای) ───────────────────────────

    /** روندِ ثبت‌نامِ اپ — بر اساسِ تاریخِ تأییدِ موبایل (ورودِ واقعی به اپ). */
    private function registrationTrend(int $window, Carbon $since): array
    {
        $col = $this->hasVerified() ? 'mobile_verified_at' : 'created_at';
        $q = $this->appCustomers(DB::table('crm_customers'))->where($col, '>=', $since->toDateTimeString());
        $daily = $q->selectRaw("DATE({$col}) as d, COUNT(*) as c")->groupBy('d')->pluck('c', 'd');

        return $this->bucketize($daily, $window, $since);
    }

    /** روندِ سفارشِ مشتریانِ اپ. */
    private function orderTrend(int $window, Carbon $since, int $cityId): array
    {
        $q = $this->appOrders()->where('o.created_at', '>=', $since->toDateTimeString())
            ->when($cityId > 0, fn ($qq) => $qq->where('o.city_id', $cityId));
        $daily = $q->selectRaw('DATE(o.created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        return $this->bucketize($daily, $window, $since);
    }

    /**
     * دانه‌بندیِ روزانه→ (روز/هفته/ماه) بر اساسِ طولِ پنجره.
     *
     * @return array{granularity:string, points: array<int, array{label:string,count:int}>}
     */
    private function bucketize($daily, int $window, Carbon $since): array
    {
        $granularity = $window <= 1 ? 'day' : ($window <= 3 ? 'week' : 'month');
        $buckets = [];

        if ($granularity === 'month') {
            for ($m = $since->copy()->startOfMonth(); $m <= now(); $m->addMonth()) {
                $sum = 0;
                foreach ($daily as $d => $c) {
                    if (str_starts_with((string) $d, $m->format('Y-m'))) {
                        $sum += (int) $c;
                    }
                }
                $buckets[] = ['label' => $this->jDate($m, 'Y/m'), 'count' => $sum];
            }
        } elseif ($granularity === 'week') {
            for ($w = $since->copy(); $w <= now(); $w->addDays(7)) {
                $end = $w->copy()->addDays(7);
                $sum = 0;
                foreach ($daily as $d => $c) {
                    $dt = Carbon::parse($d);
                    if ($dt >= $w && $dt < $end) {
                        $sum += (int) $c;
                    }
                }
                $buckets[] = ['label' => $this->jDate($w, 'm/d'), 'count' => $sum];
            }
        } else {
            for ($d = $since->copy(); $d <= now(); $d->addDay()) {
                $buckets[] = ['label' => $this->jDate($d, 'm/d'), 'count' => (int) ($daily[$d->format('Y-m-d')] ?? 0)];
            }
        }

        return ['granularity' => $granularity, 'points' => $buckets];
    }

    // ─────────────────────────── تقاضا (پنجره‌ای، اپ) ───────────────────────────

    /** @return array<string, mixed> */
    private function demand(Carbon $since, int $cityId): array
    {
        return [
            'top_devices' => $this->topBy('crm_devices', 'device_id', $since, $cityId),
            'top_brands' => $this->topBrands($since, $cityId),
            'top_cities' => $this->topBy('crm_cities', 'city_id', $since, $cityId),
            'status_funnel' => $this->statusFunnel($since, $cityId),
        ];
    }

    private function scopeWindow(Carbon $since, int $cityId)
    {
        return $this->appOrders()->where('o.created_at', '>=', $since->toDateTimeString())
            ->when($cityId > 0, fn ($qq) => $qq->where('o.city_id', $cityId));
    }

    /** @return array<int, array{name:string,count:int}> */
    private function topBy(string $table, string $fk, Carbon $since, int $cityId): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return $this->scopeWindow($since, $cityId)
            ->join($table, "{$table}.id", '=', "o.{$fk}")
            ->whereNotNull("o.{$fk}")
            ->selectRaw("{$table}.name as name, COUNT(*) as c")
            ->groupBy("{$table}.name")->orderByDesc('c')->limit(15)->get()
            ->map(fn ($r) => ['name' => (string) ($r->name ?: '—'), 'count' => (int) $r->c])->all();
    }

    /**
     * @return array{items: array<int, array{name:string,count:int}>, unspecified:int, unspecified_note:string}
     */
    private function topBrands(Carbon $since, int $cityId): array
    {
        $nullCount = (int) $this->scopeWindow($since, $cityId)->whereNull('o.brand_id')->count();

        $items = [];
        $unspecifiedNamed = 0;
        if (Schema::hasTable('crm_brands')) {
            $rows = $this->scopeWindow($since, $cityId)
                ->join('crm_brands', 'crm_brands.id', '=', 'o.brand_id')
                ->whereNotNull('o.brand_id')
                ->selectRaw('crm_brands.name as name, COUNT(*) as c')
                ->groupBy('crm_brands.name')->orderByDesc('c')->get();

            foreach ($rows as $r) {
                $name = (string) ($r->name ?: '—');
                if (in_array(trim($name), self::UNSPECIFIED_BRANDS, true)) {
                    $unspecifiedNamed += (int) $r->c;

                    continue;
                }
                $items[] = ['name' => $name, 'count' => (int) $r->c];
            }
            $items = array_slice($items, 0, 15);
        }

        return [
            'items' => $items,
            'unspecified' => $nullCount + $unspecifiedNamed,
            'unspecified_note' => 'سفارش‌های بدونِ برندِ مشخص (برندِ خالی یا «سایر»).',
        ];
    }

    /** @return array<int, array{label:string,badge:string,count:int}> */
    private function statusFunnel(Carbon $since, int $cityId): array
    {
        $rows = $this->scopeWindow($since, $cityId)
            ->selectRaw('o.status as status, COUNT(*) as c')->groupBy('o.status')->pluck('c', 'status');

        $out = [];
        foreach ($rows as $value => $count) {
            $s = OrderStatus::tryFrom((string) $value);
            $out[] = [
                'label' => $s?->label() ?? (string) $value,
                'badge' => $s?->badgeClass() ?? 'bg-gray-100 text-gray-800',
                'count' => (int) $count,
            ];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    // ─────────────────────────── ساعاتِ اوج (پنجره‌ای، اپ) ───────────────────────────

    /** @return array<string, mixed> */
    private function heatmap(Carbon $since, int $cityId): array
    {
        $rows = $this->scopeWindow($since, $cityId)
            ->selectRaw($this->dowExpr('o.created_at').' as dow, '.$this->hourExpr('o.created_at').' as h, COUNT(*) as c')
            ->groupBy('dow', 'h')->get();

        $order = [7 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه', 3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه'];
        $matrix = [];
        $totals = [];
        foreach ($order as $dow => $n) {
            $matrix[$dow] = array_fill(0, 24, 0);
            $totals[$dow] = 0;
        }
        $max = 0;
        foreach ($rows as $r) {
            $dow = (int) $r->dow;
            if (! isset($matrix[$dow])) {
                continue;
            }
            $matrix[$dow][(int) $r->h] = (int) $r->c;
            $totals[$dow] += (int) $r->c;
            $max = max($max, (int) $r->c);
        }

        $out = [];
        foreach ($order as $dow => $n) {
            $out[] = ['weekday' => $n, 'hours' => $matrix[$dow], 'total' => $totals[$dow]];
        }

        return ['rows' => $out, 'max' => $max];
    }

    // ─────────────────────────── RFM ───────────────────────────

    /** @return array<string, mixed> */
    private function rfm(): array
    {
        $now = now();
        $R = [];
        $F = [];
        $M = [];
        $customers = [];
        foreach ($this->perCustomer()->get() as $row) {
            $recency = $row->last_at ? (int) $now->diffInDays(Carbon::parse($row->last_at)) : 99999;
            $freq = (int) $row->freq;
            $monetary = (int) $row->monetary;
            $customers[] = [$recency, $freq, $monetary];
            $R[] = $recency;
            $F[] = $freq;
            $M[] = $monetary;
        }

        $n = count($customers);
        if ($n === 0) {
            return ['count' => 0, 'segments' => [], 'matrix' => $this->emptyMatrix(), 'thresholds' => []];
        }

        $rCuts = $this->quintileCuts($R);
        $fCuts = $this->quintileCuts($F);
        $mCuts = $this->quintileCuts($M);

        $matrix = $this->emptyMatrix();
        $segCounts = array_fill_keys(array_keys(self::RFM_LABELS), 0);

        foreach ($customers as [$recency, $freq, $monetary]) {
            $rScore = 6 - $this->scoreAsc($recency, $rCuts);
            $fScore = $this->scoreAsc($freq, $fCuts);
            $matrix[$rScore][$fScore]++;
            $segCounts[$this->segmentFor($rScore, $fScore)]++;
        }

        $segments = [];
        foreach (self::RFM_LABELS as $key => $label) {
            $segments[] = ['key' => $key, 'label' => $label, 'count' => $segCounts[$key]];
        }
        usort($segments, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'count' => $n,
            'segments' => $segments,
            'matrix' => $matrix,
            'thresholds' => ['recency_days' => $rCuts, 'frequency' => $fCuts, 'monetary' => $mCuts],
        ];
    }

    private function quintileCuts(array $values): array
    {
        sort($values);
        $n = count($values);
        $cuts = [];
        foreach ([0.2, 0.4, 0.6, 0.8] as $p) {
            $idx = (int) floor($p * ($n - 1));
            $cuts[] = $values[$idx] ?? end($values);
        }

        return $cuts;
    }

    private function scoreAsc($value, array $cuts): int
    {
        $score = 1;
        foreach ($cuts as $cut) {
            if ($value > $cut) {
                $score++;
            }
        }

        return max(1, min(5, $score));
    }

    private function segmentFor(int $r, int $f): string
    {
        $grid = [
            5 => ['new', 'potential_loyalist', 'potential_loyalist', 'loyal', 'champions'],
            4 => ['promising', 'potential_loyalist', 'potential_loyalist', 'loyal', 'champions'],
            3 => ['about_to_sleep', 'need_attention', 'need_attention', 'loyal', 'loyal'],
            2 => ['hibernating', 'hibernating', 'at_risk', 'at_risk', 'cant_lose'],
            1 => ['lost', 'lost', 'at_risk', 'cant_lose', 'cant_lose'],
        ];

        return $grid[$r][$f - 1] ?? 'need_attention';
    }

    /** @return array<int, array<int, int>> */
    private function emptyMatrix(): array
    {
        $m = [];
        for ($r = 1; $r <= 5; $r++) {
            $m[$r] = array_fill(1, 5, 0);
        }

        return $m;
    }

    // ─────────────────────────── سگمنت‌های اکشن‌پذیر ───────────────────────────

    private function segmentCounts(): array
    {
        return [
            'no_order' => (int) $this->noOrderQuery()->count(),
            'vip' => (int) DB::query()->fromSub($this->vipBase(), 'v')->count(),
            'at_risk' => (int) DB::query()->fromSub($this->atRiskBase(), 'a')->count(),
            'vip_limit' => self::VIP_LIMIT,
            'churn_days' => self::CHURN_DAYS,
        ];
    }

    /** مشتریانِ اپِ بدونِ هیچ سفارش. */
    public function noOrderQuery()
    {
        return $this->appCustomers(DB::table('crm_customers as c'), 'c')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('crm_orders as o')->whereColumn('o.customer_id', 'c.id'))
            ->select('c.id', 'c.first_name', 'c.mobile', 'c.created_at');
    }

    private function vipBase()
    {
        $amount = $this->amountExpr('o.');

        return $this->appCustomers(
            DB::table('crm_customers as c')->join('crm_orders as o', 'o.customer_id', '=', 'c.id'), 'c'
        )->groupBy('c.id', 'c.first_name', 'c.mobile')
            ->havingRaw("SUM({$amount}) > 0")
            ->selectRaw("c.id, c.first_name, c.mobile, COUNT(*) as freq, SUM({$amount}) as monetary");
    }

    public function vipQuery()
    {
        return DB::query()->fromSub($this->vipBase(), 'v')
            ->orderByDesc('v.monetary')->limit(self::VIP_LIMIT)
            ->select('v.id', 'v.first_name', 'v.mobile', 'v.freq', 'v.monetary');
    }

    private function atRiskBase()
    {
        $amount = $this->amountExpr('o.');

        return $this->appCustomers(
            DB::table('crm_customers as c')->join('crm_orders as o', 'o.customer_id', '=', 'c.id'), 'c'
        )->groupBy('c.id', 'c.first_name', 'c.mobile')
            ->havingRaw('MAX(o.created_at) < ?', [now()->subDays(self::CHURN_DAYS)->toDateTimeString()])
            ->selectRaw("c.id, c.first_name, c.mobile, COUNT(*) as freq, MAX(o.created_at) as last_at, SUM({$amount}) as monetary");
    }

    public function atRiskQuery()
    {
        return DB::query()->fromSub($this->atRiskBase(), 'a')
            ->orderByDesc('a.monetary')
            ->select('a.id', 'a.first_name', 'a.mobile', 'a.freq', 'a.last_at', 'a.monetary');
    }

    private function jDate(Carbon $c, string $format): string
    {
        try {
            return \Morilog\Jalali\Jalalian::fromCarbon($c)->format($format);
        } catch (\Throwable $e) {
            return $c->format($format);
        }
    }
}
