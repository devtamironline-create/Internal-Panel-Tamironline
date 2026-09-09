<?php

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;

/**
 * تحلیلِ «باشگاه مشتریان» — با محوریتِ مشتریانِ «واقعی و فعالِ» اپ.
 *
 * «واقعی» = حذفِ حساب‌های soft-deleted و بلاک‌شده. متریک‌های سطحِ‌مشتری
 * (کل، تبدیل، خریدِ تکراری، VIP، ریزش، RFM) مادام‌العمرند؛ متریک‌های فعالیت
 * (روندِ ثبت‌نام/سفارش، ساعاتِ اوج، تقاضا) در «پنجره»ی انتخابی (۱/۳/۶/۱۲ ماه،
 * پیش‌فرض ۳) محاسبه می‌شوند. فقط می‌خوانَد؛ توابعِ MySQL به‌شکلِ driver-aware
 * اجرا می‌شوند تا روی SQLite (تست) هم کار کنند.
 */
class CustomerClubAnalytics
{
    /** پنجره‌های فعالیت (ماه). */
    public const WINDOWS = [1 => '۱ ماه', 3 => '۳ ماه', 6 => '۶ ماه', 12 => 'یک سال'];

    private const CHURN_DAYS = 90;

    private const VIP_LIMIT = 30;

    private const ACTIVE_DAYS = 90;

    /** برندهایی که «نامشخص» محسوب می‌شوند و در نمودارِ برند جدا شمرده می‌شوند. */
    private const UNSPECIFIED_BRANDS = ['سایر', 'متفرقه', 'نامشخص', 'سایر برندها'];

    /** برچسب‌های سگمنتِ RFM. */
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
            'acquisition_trend' => $this->trend('crm_customers', $window, $since, 0),
            'order_trend' => $this->trend('crm_orders', $window, $since, $cityId),
            'demand' => $this->demand($since, $cityId),
            'heatmap' => $this->heatmap($since, $cityId),
            'rfm' => $this->rfm(),
            'segments' => $this->segmentCounts(),
            'window' => $window,
        ];
    }

    // ─────────────────────────── کمک‌های عمومی ───────────────────────────

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

    private function amountExpr(string $prefix = ''): string
    {
        $cols = [];
        foreach (['price_customer', 'total_invoice', 'final_price'] as $c) {
            if (Schema::hasColumn('crm_orders', $c)) {
                $cols[] = "NULLIF({$prefix}{$c}, 0)";
            }
        }

        return $cols === [] ? '0' : 'COALESCE('.implode(', ', $cols).', 0)';
    }

    private function hasBlock(): bool
    {
        return Schema::hasColumn('crm_customers', 'is_blocked');
    }

    /** فیلترِ «مشتریِ واقعی»: نه حذف‌شده، نه بلاک‌شده. */
    private function realCustomers($q, string $alias = 'crm_customers')
    {
        $q->whereNull("{$alias}.deleted_at");
        if ($this->hasBlock()) {
            $q->where(fn ($w) => $w->where("{$alias}.is_blocked", false)->orWhereNull("{$alias}.is_blocked"));
        }

        return $q;
    }

    /** آمارِ هر مشتریِ واقعیِ دارایِ سفارش: تعداد، آخرین سفارش، مجموعِ مبلغ. */
    private function perCustomer()
    {
        $amount = $this->amountExpr('o.');

        return $this->realCustomers(
            DB::table('crm_orders as o')->join('crm_customers as c', 'c.id', '=', 'o.customer_id'),
            'c'
        )
            ->whereNotNull('o.customer_id')
            ->groupBy('o.customer_id')
            ->selectRaw("o.customer_id, COUNT(*) as freq, MAX(o.created_at) as last_at, SUM({$amount}) as monetary");
    }

    /** @return array<int, array{id:int,name:string}> */
    private function cityOptions(): array
    {
        if (! Schema::hasTable('crm_cities')) {
            return [];
        }

        return DB::table('crm_orders')
            ->join('crm_cities', 'crm_cities.id', '=', 'crm_orders.city_id')
            ->selectRaw('crm_cities.id, crm_cities.name, COUNT(*) as c')
            ->groupBy('crm_cities.id', 'crm_cities.name')->orderByDesc('c')->limit(60)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])->all();
    }

    // ─────────────────────────── نمای کلی (مادام‌العمر) ───────────────────────────

    /** @return array<string, mixed> */
    private function overview(): array
    {
        $total = (int) $this->realCustomers(DB::table('crm_customers'))->count();

        $withOrders = (int) $this->realCustomers(
            DB::table('crm_orders as o')->join('crm_customers as c', 'c.id', '=', 'o.customer_id'), 'c'
        )->distinct()->count('o.customer_id');
        $withOrders = min($withOrders, $total);

        // خریدِ تکراری = مشتریانی که حداقل ۲ سفارش دارند.
        $repeat = (int) DB::query()->fromSub($this->perCustomer(), 'pc')->where('pc.freq', '>=', 2)->count();

        // فعال و واقعی = مشتریِ واقعی که در ۹۰ روزِ اخیر سفارش داده.
        $active = (int) $this->realCustomers(
            DB::table('crm_orders as o')->join('crm_customers as c', 'c.id', '=', 'o.customer_id'), 'c'
        )->where('o.created_at', '>=', now()->subDays(self::ACTIVE_DAYS)->toDateTimeString())
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

    /**
     * روندِ شمارشِ رکوردها در پنجره، با دانه‌بندیِ مناسبِ طول پنجره:
     * ۱ ماه → روزانه، ۳ ماه → هفتگی، ۶/۱۲ ماه → ماهانه.
     *
     * @return array{granularity:string, points: array<int, array{label:string,count:int}>}
     */
    private function trend(string $table, int $window, Carbon $since, int $cityId): array
    {
        $q = DB::table($table)->where('created_at', '>=', $since->toDateTimeString());
        if ($table === 'crm_customers') {
            $this->realCustomers($q);
        } elseif ($cityId > 0) {
            $q->where('city_id', $cityId);
        }
        $daily = $q->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        $granularity = $window <= 1 ? 'day' : ($window <= 3 ? 'week' : 'month');
        $buckets = [];

        if ($granularity === 'month') {
            $start = $since->copy()->startOfMonth();
            for ($m = $start->copy(); $m <= now(); $m->addMonth()) {
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
        } else { // day
            for ($d = $since->copy(); $d <= now(); $d->addDay()) {
                $buckets[] = ['label' => $this->jDate($d, 'm/d'), 'count' => (int) ($daily[$d->format('Y-m-d')] ?? 0)];
            }
        }

        return ['granularity' => $granularity, 'points' => $buckets];
    }

    // ─────────────────────────── تقاضا (پنجره‌ای) ───────────────────────────

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

    private function scopeWindow($q, Carbon $since, int $cityId)
    {
        return $q->where('crm_orders.created_at', '>=', $since->toDateTimeString())
            ->when($cityId > 0, fn ($qq) => $qq->where('crm_orders.city_id', $cityId));
    }

    /** @return array<int, array{name:string,count:int}> */
    private function topBy(string $table, string $fk, Carbon $since, int $cityId): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return $this->scopeWindow(DB::table('crm_orders'), $since, $cityId)
            ->join($table, "{$table}.id", '=', "crm_orders.{$fk}")
            ->whereNotNull("crm_orders.{$fk}")
            ->selectRaw("{$table}.name as name, COUNT(*) as c")
            ->groupBy("{$table}.name")->orderByDesc('c')->limit(15)->get()
            ->map(fn ($r) => ['name' => (string) ($r->name ?: '—'), 'count' => (int) $r->c])->all();
    }

    /**
     * برندها با تفکیکِ «نامشخص»: برندِ بدون مقدار (null) یا برندهایی مثلِ «سایر»
     * جدا شمرده می‌شوند تا نمودارِ برند واقعی و خوانا بماند.
     *
     * @return array{items: array<int, array{name:string,count:int}>, unspecified:int, unspecified_note:string}
     */
    private function topBrands(Carbon $since, int $cityId): array
    {
        $hasBrands = Schema::hasTable('crm_brands');

        // «نامشخص» = سفارشِ بدونِ برند (null) + برندهای عمومی (سایر/متفرقه/…).
        $nullCount = (int) $this->scopeWindow(DB::table('crm_orders'), $since, $cityId)
            ->whereNull('brand_id')->count();

        $items = [];
        $unspecifiedNamed = 0;
        if ($hasBrands) {
            $rows = $this->scopeWindow(DB::table('crm_orders'), $since, $cityId)
                ->join('crm_brands', 'crm_brands.id', '=', 'crm_orders.brand_id')
                ->whereNotNull('crm_orders.brand_id')
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
            'unspecified_note' => 'سفارش‌های بدونِ برندِ مشخص (برندِ خالی یا «سایر») — معمولاً ورودیِ قدیمی/سینک یا ثبتِ تلفنی.',
        ];
    }

    /** @return array<int, array{label:string,badge:string,count:int}> */
    private function statusFunnel(Carbon $since, int $cityId): array
    {
        $rows = $this->scopeWindow(DB::table('crm_orders'), $since, $cityId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

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

    // ─────────────────────────── ساعاتِ اوج (پنجره‌ای) ───────────────────────────

    /** @return array<string, mixed> */
    private function heatmap(Carbon $since, int $cityId): array
    {
        $rows = $this->scopeWindow(DB::table('crm_orders'), $since, $cityId)
            ->selectRaw($this->dowExpr('created_at').' as dow, '.$this->hourExpr('created_at').' as h, COUNT(*) as c')
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

    // ─────────────────────────── RFM (مادام‌العمر، ماتریسِ کامل) ───────────────────────────

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
            $rScore = 6 - $this->scoreAsc($recency, $rCuts); // recency کمتر = بهتر
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
            'thresholds' => [
                'recency_days' => $rCuts,
                'frequency' => $fCuts,
                'monetary' => $mCuts,
            ],
        ];
    }

    /** نقاطِ برشِ چارک‌پنجم (۲۰/۴۰/۶۰/۸۰٪) از آرایهٔ مقادیر. */
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

    /** امتیازِ صعودی ۱..۵ بر اساسِ نقاطِ برش (بزرگ‌تر = امتیازِ بیشتر). */
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

    /** نگاشتِ استانداردِ شبکهٔ R×F (۱..۵) به سگمنت. */
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

    /** @return array<int, array<int, int>> ماتریسِ ۵×۵ صفر (R 1..5 × F 1..5). */
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

    /** مشتریانِ واقعیِ بدونِ هیچ سفارش. */
    public function noOrderQuery()
    {
        return $this->realCustomers(DB::table('crm_customers as c'), 'c')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('crm_orders as o')->whereColumn('o.customer_id', 'c.id'))
            ->select('c.id', 'c.first_name', 'c.mobile', 'c.created_at');
    }

    /** پایهٔ VIP: هر مشتریِ واقعیِ دارایِ سفارش با مجموعِ مبلغ. */
    private function vipBase()
    {
        $amount = $this->amountExpr('o.');

        return $this->realCustomers(
            DB::table('crm_customers as c')->join('crm_orders as o', 'o.customer_id', '=', 'c.id'), 'c'
        )->groupBy('c.id', 'c.first_name', 'c.mobile')
            ->havingRaw("SUM({$amount}) > 0")
            ->selectRaw("c.id, c.first_name, c.mobile, COUNT(*) as freq, SUM({$amount}) as monetary");
    }

    /** VIP = ۳۰ مشتریِ بابیشترین مجموعِ مبلغِ خرید. */
    public function vipQuery()
    {
        return DB::query()->fromSub($this->vipBase(), 'v')
            ->orderByDesc('v.monetary')->limit(self::VIP_LIMIT)
            ->select('v.id', 'v.first_name', 'v.mobile', 'v.freq', 'v.monetary');
    }

    /** پایهٔ ریزش: مشتریِ واقعیِ دارایِ سفارش که آخرین سفارشش > CHURN_DAYS پیش بوده. */
    private function atRiskBase()
    {
        $amount = $this->amountExpr('o.');

        return $this->realCustomers(
            DB::table('crm_customers as c')->join('crm_orders as o', 'o.customer_id', '=', 'c.id'), 'c'
        )->groupBy('c.id', 'c.first_name', 'c.mobile')
            ->havingRaw('MAX(o.created_at) < ?', [now()->subDays(self::CHURN_DAYS)->toDateTimeString()])
            ->selectRaw("c.id, c.first_name, c.mobile, COUNT(*) as freq, MAX(o.created_at) as last_at, SUM({$amount}) as monetary");
    }

    /** مشتریانِ در خطرِ ریزش (۹۰ روز بدونِ سفارش) — پرارزش‌ها اول. */
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
