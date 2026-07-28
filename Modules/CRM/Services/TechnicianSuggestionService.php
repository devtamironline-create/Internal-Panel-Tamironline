<?php

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\AssignmentSettings;

/**
 * فاز ۱ سیستم پیشنهاد هوشمند تخصیص تکنسین — Smart Suggestion.
 *
 * منطق:
 *   ۱) فیلتر hard: تکنسین فعال + تگ‌های منطقه/برند/دستگاه با سفارش match
 *      کند + ظرفیت پر نباشد.
 *   ۲) امتیازدهی روی متغیرهای ۶گانه با وزن‌های ثابت (جمع = ۱۰۰).
 *   ۳) خروجی مرتب‌شده با breakdown قابل نمایش در UI.
 *
 * توجه: امتیاز فقط به خودِ تکنسین وابسته است، نه به سفارش. سفارش صرفاً
 * در مرحلهٔ فیلتر hard نقش دارد. به همین دلیل برای یک گروه سفارش،
 * امتیازها یک‌بار محاسبه و بین همهٔ سفارش‌ها به اشتراک گذاشته می‌شوند
 * (نگاه کنید به TechnicianGroupPlanner).
 */
class TechnicianSuggestionService
{
    /**
     * وزن‌های پیش‌فرض — هم‌سو با Roadmap محصولی فاز ۱.
     *
     * مقدارِ مؤثر از تنظیمات پنل خوانده می‌شود (AssignmentSettings::weights)
     * و همیشه نرمال‌شده به جمعِ ۱۰۰ است؛ این ثابت فقط نقطهٔ شروع است.
     */
    public const WEIGHTS = [
        'open_orders' => 30,  // سفارش باز کمتر = بهتر
        'debt' => 25,  // بدهی کمتر = بهتر
        'satisfaction' => 20,  // رضایت بالاتر = بهتر
        'cancel_rate' => 10,  // نرخ کنسلی پایین‌تر = بهتر
        'recent_activity' => 10,  // فعالیت اخیر = بهتر
        'response_speed' => 5,   // سرعت پاسخگویی بالاتر = بهتر
    ];

    /** آستانه‌های دسته‌بندی اعتبار پیشنهاد. */
    public const TIER_EXCELLENT = 80;

    public const TIER_GOOD = 60;

    public const TIER_NORMAL = 40;

    public const TIER_CAUTION = 20;

    /** وضعیت‌هایی که «سفارش باز» محسوب می‌شوند. */
    public const ACTIVE_STATUSES = [
        'coordinated', 'open', 'new', 'suspended',
    ];

    /**
     * @return Collection<int, object{technician:Technician, score:int, breakdown:array, tier:string, label:string, reasons:array}>
     */
    public function suggestForOrder(Order $order, int $limit = 5): Collection
    {
        $pool = $this->candidatePool();
        $openCounts = $this->openCountsFor($pool);

        // مرحله ۱ — فیلتر hard
        $candidates = $pool->filter(
            fn (Technician $t) => $this->rejectionFor($t, $order, (int) ($openCounts[$t->id] ?? 0)) === null
        )->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        // مرحله ۲ — امتیازدهی
        $scored = $this->scoreAll($candidates, $openCounts);

        // مرتب‌سازی نزولی + محدود به limit
        return $scored->values()->sortByDesc('score')->take($limit)->values();
    }

    /**
     * استخرِ پایهٔ نامزدها — فعال، آمادهٔ دریافت سفارش، غیرمستثنا.
     * رکوردهای placeholder (مثل «سفارش کنسل شده») هرگز پیشنهاد نمی‌شوند.
     *
     * @return Collection<int, Technician>
     */
    public function candidatePool(bool $onlyReady = true): Collection
    {
        return Technician::query()
            ->where('status', 'active')
            ->when($onlyReady, fn ($q) => $q->where('ready_for_delivery', true))
            ->where('exclude_from_suggestions', false)
            ->with(['cities:id,is_active', 'regions:id,parent_city_id', 'brands:id', 'devices:id'])
            ->get();
    }

    /**
     * تعداد سفارش‌های بازِ هر تکنسین — یک کوئری برای کل استخر.
     *
     * @param  Collection<int, Technician>  $techs
     * @return array<int, int>
     */
    public function openCountsFor(Collection $techs): array
    {
        $ids = $techs->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        return Order::query()->realOrders()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereIn('technician_id', $ids)
            ->groupBy('technician_id')
            ->selectRaw('technician_id, COUNT(*) as cnt')
            ->pluck('cnt', 'technician_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * امتیاز همهٔ تکنسین‌های داده‌شده — آمار و زمانِ پاسخ یک‌بار به‌صورت
     * دسته‌ای کوئری می‌شود (به‌جای N+1 قبلی).
     *
     * @param  Collection<int, Technician>  $techs
     * @param  array<int, int>  $openCounts
     * @return Collection<int, object> کلید = technician id
     */
    public function scoreAll(Collection $techs, array $openCounts): Collection
    {
        $ids = $techs->pluck('id')->all();
        $stats = $this->statsFor($ids);
        $response = $this->responseMinutesFor($techs);

        return $techs->mapWithKeys(function (Technician $t) use ($openCounts, $stats, $response) {
            $t->setAttribute('_now_orders', (int) ($openCounts[$t->id] ?? 0));

            return [$t->id => $this->scoreTechnician(
                $t,
                $stats[$t->id] ?? ['total' => 0, 'cancelled' => 0, 'last_assigned_at' => null],
                $response[$t->id] ?? null
            )];
        });
    }

    /**
     * اولین دلیل rejection یک تکنسین برای یک سفارش، یا null اگر قابل
     * پیشنهاد است. هم در فیلتر اصلی استفاده می‌شود هم در diagnose.
     *
     * Backward-compatible: اگر تکنسین برای cities/brands/devices هیچ
     * تگی ست نکرده باشد، فرض می‌کنیم پوشش کامل دارد. این false-negative
     * ها را کاهش می‌دهد اما ممکن است پیشنهاد عمومی‌تر باشد. اپراتور با
     * امتیاز پایین می‌تواند تشخیص دهد.
     */
    public function rejectionFor(Technician $t, Order $order, int $openCount): ?string
    {
        $max = (int) ($t->max_order ?? 0);
        if ($max > 0 && $openCount >= $max) {
            return 'capacity';
        }

        if ($order->city_id) {
            // فقط تگ شهرهای فعال — شهرهایی که به منطقه تبدیل شده‌اند
            // (is_active=false) نباید تکنسین را block کنند.
            $cityIds = $t->cities->where('is_active', true)->pluck('id');
            // تگ خالی = پوشش همه (backward-compatible).
            if ($cityIds->isNotEmpty() && ! $cityIds->contains($order->city_id)) {
                return 'city';
            }
        }
        // تطبیق منطقه — اگر تکنسین برای شهر سفارش منطقه‌ای انتخاب کرده
        // باشد، باید منطقهٔ سفارش جزو آن‌ها باشد. منطقه = ردیف فرزندِ
        // crm_cities (district_id روی سفارش، parent_city_id روی پوشش تکنسین).
        if ($order->district_id && $order->city_id) {
            $regionsInOrderCity = $t->regions
                ->where('parent_city_id', $order->city_id)
                ->pluck('id');
            if ($regionsInOrderCity->isNotEmpty()
                && ! $regionsInOrderCity->contains($order->district_id)) {
                return 'region';
            }
        }
        if ($order->brand_id) {
            $brandIds = $t->brands->pluck('id');
            if ($brandIds->isNotEmpty() && ! $brandIds->contains($order->brand_id)) {
                return 'brand';
            }
        }
        if ($order->device_id) {
            $deviceIds = $t->devices->pluck('id');
            if ($deviceIds->isNotEmpty() && ! $deviceIds->contains($order->device_id)) {
                return 'device';
            }
        }
        if ($order->order_type) {
            $techTypes = $t->service_types;
            if (is_array($techTypes) && ! empty($techTypes)
                && ! in_array($order->order_type, $techTypes, true)) {
                return 'service_type';
            }
        }

        return null;
    }

    /**
     * تشخیص: برای یک سفارش، چند تکنسین فعال هستند و هر کدام به چه دلیلی
     * رد شده‌اند. به اپراتور کمک می‌کند بفهمد چرا پیشنهادی نیست.
     *
     * خروجی:
     *   ['active_total' => N, 'accepted' => M, 'rejections' => [reason => count, ...]]
     */
    public function diagnoseForOrder(Order $order): array
    {
        $techs = $this->candidatePool(onlyReady: false);
        $openCounts = $this->openCountsFor($techs);

        $rejections = [];
        $accepted = 0;
        $notReady = 0;
        foreach ($techs as $t) {
            if (! $t->ready_for_delivery) {
                $notReady++;

                continue;
            }
            $reason = $this->rejectionFor($t, $order, (int) ($openCounts[$t->id] ?? 0));
            if ($reason === null) {
                $accepted++;
            } else {
                $rejections[$reason] = ($rejections[$reason] ?? 0) + 1;
            }
        }
        if ($notReady > 0) {
            $rejections['not_ready'] = $notReady;
        }

        return [
            'active_total' => $techs->count(),
            'accepted' => $accepted,
            'rejections' => $rejections,
        ];
    }

    /** وزن‌های مؤثرِ این درخواست — یک‌بار از تنظیمات خوانده می‌شود. */
    private ?array $activeWeights = null;

    /** @return array<string, int> */
    public function weights(): array
    {
        return $this->activeWeights ??= AssignmentSettings::weights();
    }

    /** امتیاز هر تکنسین — جمع ۶ بُعد با وزن‌های تنظیمات، خروجی int 0..100. */
    protected function scoreTechnician(Technician $t, array $stats, ?float $avgResponseMin): object
    {
        $weights = $this->weights();
        $breakdown = [];
        $reasons = [];

        // ─── ۱) سفارش‌های باز (30٪)
        $now = (int) ($t->_now_orders ?? 0);
        $max = (int) ($t->max_order ?? 0);
        if ($max > 0) {
            $openRatio = max(0, ($max - $now) / $max);  // 1 = خالی، 0 = پر
        } else {
            // بدون سقف: ۰ سفارش = ۱، ۱۰+ سفارش = ۰
            $openRatio = max(0, 1 - $now / 10);
        }
        $breakdown['open_orders'] = (int) round($openRatio * $weights['open_orders']);
        if ($now === 0) {
            $reasons[] = 'بدون سفارش باز';
        } elseif ($max > 0 && $now >= $max * 0.8) {
            $reasons[] = 'نزدیک به سقف ظرفیت';
        }

        // ─── ۲) بدهی (25٪)
        $balance = (int) ($t->wallet_balance ?? 0);
        $debt = max(0, -$balance);
        $maxDebt = (int) ($t->max_price ?? 0);
        if ($debt === 0) {
            $debtScore = 1.0;
        } elseif ($maxDebt > 0) {
            $debtScore = max(0, ($maxDebt - $debt) / $maxDebt);
        } else {
            // heuristic: ۵ میلیون آستانه
            $debtScore = max(0, 1 - $debt / 5_000_000);
        }
        $breakdown['debt'] = (int) round($debtScore * $weights['debt']);
        if ($debt === 0) {
            $reasons[] = 'بدون بدهی';
        } elseif ($maxDebt > 0 && $debt >= $maxDebt) {
            $reasons[] = '⚠ بدهی بحرانی';
        }

        // ─── ۳) رضایت مشتری (20٪) — دستی توسط ادمین
        $sat = $t->satisfaction_score !== null ? (float) $t->satisfaction_score : null;
        if ($sat !== null) {
            $satScore = max(0, min(1, $sat / 5));
        } else {
            // ست نشده — neutral midpoint
            $satScore = 0.5;
        }
        $breakdown['satisfaction'] = (int) round($satScore * $weights['satisfaction']);
        if ($sat !== null && $sat >= 4.5) {
            $reasons[] = 'رضایت عالی';
        }

        // ─── ۴) نرخ کنسلی (10٪)
        if ($stats['total'] >= 5) {
            $cancelRate = $stats['total'] > 0 ? $stats['cancelled'] / $stats['total'] : 0;
        } else {
            $cancelRate = 0; // تکنسین جدید — مزیت شک
        }
        $breakdown['cancel_rate'] = (int) round((1 - $cancelRate) * $weights['cancel_rate']);
        if ($cancelRate > 0.3) {
            $reasons[] = '⚠ نرخ کنسلی بالا';
        }

        // ─── ۵) فعالیت اخیر (10٪)
        $lastDays = $stats['last_assigned_at']
            ? Carbon::parse($stats['last_assigned_at'])->diffInDays(now())
            : 999;
        $activityRatio = max(0, 1 - $lastDays / 14); // افول در ۲ هفته
        $breakdown['recent_activity'] = (int) round($activityRatio * $weights['recent_activity']);
        if ($lastDays > 14) {
            $reasons[] = '⚠ بدون فعالیت اخیر';
        }

        // ─── ۶) سرعت پاسخگویی (5٪)
        // میانگین فاصلهٔ assigned_at تا اولین تغییر وضعیت توسط تکنسین
        if ($avgResponseMin === null) {
            $respScore = 0.5; // داده‌ای نداریم
        } elseif ($avgResponseMin < 60) {
            $respScore = 1.0;
        } elseif ($avgResponseMin < 240) {
            $respScore = 0.6;
        } elseif ($avgResponseMin < 1440) {
            $respScore = 0.2;
        } else {
            $respScore = 0;
        }
        $breakdown['response_speed'] = (int) round($respScore * $weights['response_speed']);

        $score = array_sum($breakdown);

        [$tier, $label] = $this->tierFor($score);

        return (object) [
            'technician' => $t,
            'score' => $score,
            'breakdown' => $breakdown,
            'tier' => $tier,
            'label' => $label,
            'reasons' => $reasons,
            'now_orders' => $now,
            'max_orders' => $max,
            'debt' => $debt,
            'cancel_rate_pct' => (int) round($cancelRate * 100),
            'last_activity_days' => $lastDays === 999 ? null : (int) $lastDays,
        ];
    }

    /**
     * آمار سفارش‌های چند تکنسین در یک کوئری — total, cancelled, last_assigned_at.
     *
     * @param  array<int, int>  $techIds
     * @return array<int, array{total:int, cancelled:int, last_assigned_at:?string}>
     */
    protected function statsFor(array $techIds): array
    {
        if (empty($techIds)) {
            return [];
        }

        return Order::query()->realOrders()
            ->whereIn('technician_id', $techIds)
            ->groupBy('technician_id')
            ->selectRaw('
                technician_id,
                COUNT(*) as total,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as cancelled,
                MAX(assigned_at) as last_assigned_at
            ', [OrderStatus::Cancelled->value, OrderStatus::Declined->value])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->technician_id => [
                'total' => (int) $row->total,
                'cancelled' => (int) $row->cancelled,
                'last_assigned_at' => $row->last_assigned_at,
            ]])
            ->all();
    }

    /**
     * میانگین زمان پاسخ چند تکنسین در یک کوئری.
     * فاصله از assigned_at تا اولین OrderStatusLog که by آن تکنسین است.
     *
     * TIMESTAMPDIFF مخصوص MySQL است؛ روی درایورهای دیگر (تست‌ها) کوئری
     * می‌شکند و چون این بُعد فقط ۵٪ وزن دارد، خطا را بلعیده و مقدار خنثی
     * برمی‌گردانیم تا امتیازدهی از کار نیفتد.
     *
     * @param  Collection<int, Technician>  $techs
     * @return array<int, float>
     */
    protected function responseMinutesFor(Collection $techs): array
    {
        $userIds = $techs->pluck('user_id', 'id')->filter()->all();
        if (empty($userIds)) {
            return [];
        }

        try {
            $rows = DB::table('crm_orders as o')
                ->join('crm_technicians as t', 't.id', '=', 'o.technician_id')
                ->join('crm_order_status_logs as l', function ($join) {
                    $join->on('l.order_id', '=', 'o.id')
                        ->on('l.changed_by', '=', 't.user_id');
                })
                ->whereIn('o.technician_id', array_keys($userIds))
                ->whereNotNull('o.assigned_at')
                ->whereColumn('l.created_at', '>', 'o.assigned_at')
                ->groupBy('o.technician_id', 'o.id')
                ->selectRaw('o.technician_id as tech_id, TIMESTAMPDIFF(MINUTE, MIN(o.assigned_at), MIN(l.created_at)) as mins')
                ->get();
        } catch (\Throwable $e) {
            Log::debug('responseMinutesFor skipped', ['err' => $e->getMessage()]);

            return [];
        }

        $buckets = [];
        foreach ($rows as $row) {
            $mins = (int) $row->mins;
            if ($mins <= 0) {
                continue;
            }
            $id = (int) $row->tech_id;
            // سقف ۵۰ نمونه به‌ازای هر تکنسین — مثل نسخهٔ قبلی.
            if (count($buckets[$id] ?? []) >= 50) {
                continue;
            }
            $buckets[$id][] = $mins;
        }

        return array_map(fn (array $v) => array_sum($v) / count($v), $buckets);
    }

    /** دسته‌بندی tier از روی score 0..100. */
    protected function tierFor(int $score): array
    {
        return match (true) {
            $score >= self::TIER_EXCELLENT => ['excellent', 'پیشنهاد ویژه'],
            $score >= self::TIER_GOOD => ['good',      'مناسب'],
            $score >= self::TIER_NORMAL => ['normal',    'قابل بررسی'],
            $score >= self::TIER_CAUTION => ['caution',   'احتیاط'],
            default => ['blocked',   'غیرقابل توصیه'],
        };
    }
}
