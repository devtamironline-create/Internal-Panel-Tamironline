<?php

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * فاز ۱ سیستم پیشنهاد هوشمند تخصیص تکنسین — Smart Suggestion.
 *
 * منطق:
 *   ۱) فیلتر hard: تکنسین فعال + تگ‌های منطقه/برند/دستگاه پر باشد و
 *      با سفارش match کند + ظرفیت پر نباشد.
 *   ۲) امتیازدهی روی متغیرهای ۶گانه با وزن‌های ثابت (جمع = ۱۰۰).
 *   ۳) خروجی مرتب‌شده با breakdown قابل نمایش در UI.
 *
 * تصمیم محصولی: تکنسین بدون تگ تخصص exclude می‌شود (option 5-ب) — این
 * اپراتور را وادار می‌کند پروفایل تکنسین‌ها را پُر کند، سیستم همیشه
 * دیتای دقیق دارد، و پیشنهادها قابل اعتماد می‌مانند.
 */
class TechnicianSuggestionService
{
    /** وزن‌های ثابت — هم‌سو با Roadmap محصولی فاز ۱. */
    public const WEIGHTS = [
        'open_orders'    => 30,  // سفارش باز کمتر = بهتر
        'debt'           => 25,  // بدهی کمتر = بهتر
        'satisfaction'   => 20,  // رضایت بالاتر = بهتر
        'cancel_rate'    => 10,  // نرخ کنسلی پایین‌تر = بهتر
        'recent_activity'=> 10,  // فعالیت اخیر = بهتر
        'response_speed' => 5,   // سرعت پاسخگویی بالاتر = بهتر
    ];

    /** آستانه‌های دسته‌بندی اعتبار پیشنهاد. */
    public const TIER_EXCELLENT = 80;
    public const TIER_GOOD      = 60;
    public const TIER_NORMAL    = 40;
    public const TIER_CAUTION   = 20;

    /**
     * @return Collection<int, object{technician:Technician, score:int, breakdown:array, tier:string, label:string, reasons:array}>
     */
    public function suggestForOrder(Order $order, int $limit = 5): Collection
    {
        // مرحله ۱ — فیلتر hard
        $candidates = $this->filterCandidates($order);

        // مرحله ۲ — امتیازدهی
        $scored = $candidates->map(fn (Technician $t) => $this->scoreTechnician($t, $order));

        // مرتب‌سازی نزولی + محدود به limit
        return $scored->sortByDesc('score')->take($limit)->values();
    }

    /** فیلتر اولیه: فعال + آماده سفارش + match تخصص + ظرفیت آزاد. */
    protected function filterCandidates(Order $order): Collection
    {
        $query = Technician::query()
            ->where('status', 'active')
            ->where('ready_for_delivery', true)
            ->with(['cities:id', 'regions:id,city_id', 'brands:id', 'devices:id']);

        // ظرفیت کلی نباید پر باشد — اگر max_order ست شده، نهایتاً به آن
        // محدود کنیم. شمارش سفارش‌های فعال در همان loop انجام می‌شود.
        $techs = $query->get();
        $activeStatuses = [
            OrderStatus::Coordinated->value,
            OrderStatus::Open->value,
            OrderStatus::New->value,
            OrderStatus::Suspended->value,
        ];
        $openCounts = Order::query()->realOrders()
            ->whereIn('status', $activeStatuses)
            ->whereIn('technician_id', $techs->pluck('id'))
            ->groupBy('technician_id')
            ->selectRaw('technician_id, COUNT(*) as cnt')
            ->pluck('cnt', 'technician_id');

        return $techs->filter(function (Technician $t) use ($order, $openCounts) {
            // ظرفیت
            $now = (int) ($openCounts[$t->id] ?? 0);
            $max = (int) ($t->max_order ?? 0);
            if ($max > 0 && $now >= $max) return false;

            // تطبیق تگ‌ها — option 5-ب: بدون تگ یعنی exclude.
            if ($order->city_id) {
                $cityIds = $t->cities->pluck('id');
                if ($cityIds->isEmpty() || ! $cityIds->contains($order->city_id)) return false;
            }
            // تطبیق منطقه — منطق سازگار با عقب:
            //   اگر سفارش منطقه دارد:
            //     - اگر تکنسین برای این شهر هیچ منطقه‌ای انتخاب نکرده،
            //       فرض می‌کنیم همه را پوشش می‌دهد (قبول)
            //     - اگر منطقه‌ای انتخاب کرده، باید منطقهٔ سفارش جزو
            //       انتخاب‌هایش باشد
            //   اگر سفارش منطقه ندارد، چک نمی‌کنیم.
            if ($order->region_id && $order->city_id) {
                $regionIds = $t->regions->pluck('id');
                if ($regionIds->isNotEmpty()) {
                    // تکنسین مناطق انتخابی دارد — حداقل یکی از مناطقش
                    // باید در شهر سفارش باشد، و منطقهٔ سفارش جزو آن‌ها.
                    $regionsInOrderCity = $t->regions
                        ->where('city_id', $order->city_id)
                        ->pluck('id');
                    if ($regionsInOrderCity->isNotEmpty()
                        && ! $regionsInOrderCity->contains($order->region_id)) {
                        return false;
                    }
                }
            }
            if ($order->brand_id) {
                $brandIds = $t->brands->pluck('id');
                if ($brandIds->isEmpty() || ! $brandIds->contains($order->brand_id)) return false;
            }
            if ($order->device_id) {
                $deviceIds = $t->devices->pluck('id');
                if ($deviceIds->isEmpty() || ! $deviceIds->contains($order->device_id)) return false;
            }

            // تطبیق نوع خدمت — اگر سفارش order_type دارد و تکنسین
            // service_types ست کرده، باید match شود. اگر تکنسین
            // service_types خالی/null دارد، رفتار قبلی حفظ می‌شود
            // (همه نوع را قبول می‌کند) — backward compatible.
            if ($order->order_type) {
                $techTypes = $t->service_types;
                if (is_array($techTypes) && ! empty($techTypes)) {
                    if (! in_array($order->order_type, $techTypes, true)) {
                        return false;
                    }
                }
            }

            // ذخیره برای استفاده در امتیازدهی
            $t->setAttribute('_now_orders', $now);
            return true;
        })->values();
    }

    /** امتیاز هر تکنسین — جمع ۶ بُعد با وزن‌های ثابت، خروجی int 0..100. */
    protected function scoreTechnician(Technician $t, Order $order): object
    {
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
        $breakdown['open_orders'] = (int) round($openRatio * self::WEIGHTS['open_orders']);
        if ($now === 0) $reasons[] = 'بدون سفارش باز';
        elseif ($max > 0 && $now >= $max * 0.8) $reasons[] = 'نزدیک به سقف ظرفیت';

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
        $breakdown['debt'] = (int) round($debtScore * self::WEIGHTS['debt']);
        if ($debt === 0) $reasons[] = 'بدون بدهی';
        elseif ($maxDebt > 0 && $debt >= $maxDebt) $reasons[] = '⚠ بدهی بحرانی';

        // ─── ۳) رضایت مشتری (20٪) — option 2-ب: دستی توسط ادمین
        $sat = $t->satisfaction_score !== null ? (float) $t->satisfaction_score : null;
        if ($sat !== null) {
            $satScore = max(0, min(1, $sat / 5));
        } else {
            // ست نشده — neutral midpoint
            $satScore = 0.5;
        }
        $breakdown['satisfaction'] = (int) round($satScore * self::WEIGHTS['satisfaction']);
        if ($sat !== null && $sat >= 4.5) $reasons[] = 'رضایت عالی';

        // ─── ۴) نرخ کنسلی (10٪)
        $stats = $this->orderStats($t->id);
        if ($stats['total'] >= 5) {
            $cancelRate = $stats['total'] > 0 ? $stats['cancelled'] / $stats['total'] : 0;
        } else {
            $cancelRate = 0; // تکنسین جدید — مزیت شک
        }
        $breakdown['cancel_rate'] = (int) round((1 - $cancelRate) * self::WEIGHTS['cancel_rate']);
        if ($cancelRate > 0.3) $reasons[] = '⚠ نرخ کنسلی بالا';

        // ─── ۵) فعالیت اخیر (10٪)
        $lastDays = $stats['last_assigned_at']
            ? Carbon::parse($stats['last_assigned_at'])->diffInDays(now())
            : 999;
        $activityRatio = max(0, 1 - $lastDays / 14); // افول در ۲ هفته
        $breakdown['recent_activity'] = (int) round($activityRatio * self::WEIGHTS['recent_activity']);
        if ($lastDays > 14) $reasons[] = '⚠ بدون فعالیت اخیر';

        // ─── ۶) سرعت پاسخگویی (5٪)
        // میانگین فاصلهٔ assigned_at تا اولین تغییر وضعیت توسط تکنسین
        $avgResponseMin = $this->avgResponseMinutes($t->id);
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
        $breakdown['response_speed'] = (int) round($respScore * self::WEIGHTS['response_speed']);

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

    /** آمار سفارش‌های تکنسین — total, cancelled, last_assigned_at. */
    protected function orderStats(int $techId): array
    {
        $row = Order::query()->realOrders()
            ->where('technician_id', $techId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as cancelled,
                MAX(assigned_at) as last_assigned_at
            ", [OrderStatus::Cancelled->value, OrderStatus::Declined->value])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'last_assigned_at' => $row->last_assigned_at ?? null,
        ];
    }

    /**
     * میانگین زمان پاسخ تکنسین به سفارش‌های تخصیص‌داده‌شده.
     * فاصله از assigned_at تا اولین OrderStatusLog که by این تکنسین است.
     */
    protected function avgResponseMinutes(int $techId): ?float
    {
        $tech = Technician::find($techId);
        if (! $tech || ! $tech->user_id) return null;

        // فقط سفارش‌هایی که تکنسین وضعیت‌شان را عوض کرده
        // o.assigned_at را MIN می‌گیریم تا با ONLY_FULL_GROUP_BY MySQL
        // سازگار بماند (per-order یکتاست پس MIN/MAX/ANY_VALUE یکسان است).
        $rows = \DB::select("
            SELECT TIMESTAMPDIFF(MINUTE, MIN(o.assigned_at), MIN(l.created_at)) AS mins
            FROM crm_orders o
            INNER JOIN crm_order_status_logs l ON l.order_id = o.id AND l.changed_by = ?
            WHERE o.technician_id = ?
              AND o.assigned_at IS NOT NULL
              AND l.created_at > o.assigned_at
            GROUP BY o.id
            LIMIT 50
        ", [$tech->user_id, $techId]);

        if (empty($rows)) return null;
        $values = array_filter(array_map(fn ($r) => (int) $r->mins, $rows), fn ($v) => $v > 0);
        if (empty($values)) return null;

        return array_sum($values) / count($values);
    }

    /** دسته‌بندی tier از روی score 0..100. */
    protected function tierFor(int $score): array
    {
        return match (true) {
            $score >= self::TIER_EXCELLENT => ['excellent', 'پیشنهاد ویژه'],
            $score >= self::TIER_GOOD      => ['good',      'مناسب'],
            $score >= self::TIER_NORMAL    => ['normal',    'قابل بررسی'],
            $score >= self::TIER_CAUTION   => ['caution',   'احتیاط'],
            default                        => ['blocked',   'غیرقابل توصیه'],
        };
    }
}
