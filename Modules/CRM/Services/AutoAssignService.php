<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderAssignmentLog;
use Modules\CRM\Support\AssignmentSettings;

/**
 * پخشِ خودکارِ سفارش‌های بدون تکنسین.
 *
 * ورودی: سفارش‌هایی که (الف) تکنسین ندارند، (ب) وضعیتشان نهایی نیست،
 * (ج) از زمانِ ثبتشان بیش از «مهلتِ انتظار» گذشته و (د) از سقفِ قدمت
 * عبور نکرده‌اند.
 *
 * چرا مهلتِ انتظار: گروه بر اساس «همان مشتری، همان آدرس، همان روز»
 * تعریف شده. اگر سفارشِ اول لحظهٔ ثبت پخش شود، سفارش دوم و سوم که چند
 * دقیقه بعد ثبت می‌شوند دیگر نمی‌توانند در یک نقشهٔ واحد کنار هم قرار
 * بگیرند. مهلت به تمام‌شدنِ نشستِ ثبتِ مشتری فرصت می‌دهد.
 *
 * برای سفارش‌هایی که دیرتر می‌رسند، قاعدهٔ چسبندگیِ برنامه‌ریز کار را
 * تمام می‌کند: اگر هم‌گروهی از قبل تکنسین دارد، همان نفر اولویتِ مطلق
 * می‌گیرد.
 */
final class AutoAssignService
{
    public function __construct(
        private readonly OrderGroupResolver $groups,
        private readonly TechnicianGroupPlanner $planner,
        private readonly OrderAssigner $assigner,
    ) {}

    /**
     * @return array{groups:int, assigned:int, skipped_low_score:int, unassignable:int, skipped_no_type:int}
     */
    public function run(bool $dryRun = false): array
    {
        $stats = [
            'groups' => 0, 'assigned' => 0, 'skipped_low_score' => 0,
            'unassignable' => 0, 'skipped_no_type' => 0,
        ];

        if (! AssignmentSettings::isAuto() && ! $dryRun) {
            return $stats;
        }

        $pending = $this->pendingOrders();

        // بدونِ «نوع خدمت» نمی‌شود گفت سفارش تعمیر است یا نصب، پس
        // نمی‌شود تکنسینِ درست را انتخاب کرد. این‌ها را برای اپراتور
        // می‌گذاریم و تعدادشان را گزارش می‌کنیم تا اگر روزی صفر نبود
        // بی‌صدا نماند.
        [$typed, $untyped] = $pending->partition(fn (Order $o) => filled($o->order_type));
        $stats['skipped_no_type'] = $untyped->count();
        $pending = $typed->values();

        if ($pending->isEmpty()) {
            return $stats;
        }

        $minScore = AssignmentSettings::minScore();

        foreach ($this->groups->groupBy($pending) as $orders) {
            $stats['groups']++;

            /** @var Order $first */
            $first = $orders->first();
            $preferred = $this->groups->assignedSiblingTechnicianIds($first);

            $plan = $this->planner->plan($orders->values(), $preferred, reserve: true);
            $groupIds = $orders->pluck('id')->all();
            $groupSize = count($groupIds);

            foreach ($plan->steps as $step) {
                // امتیاز پایین = سیستم مطمئن نیست؛ تصمیم را به اپراتور
                // واگذار می‌کنیم. چسبندگی و سابقهٔ همان دستگاه از این قاعده
                // مستثنا هستند: «همان تکنسینِ همان خانه/همان دستگاه» تصمیمِ
                // عملیاتیِ درستی است حتی اگر امتیازِ کیفی‌اش پایین باشد.
                $exempt = $step->sticky || $step->history !== null;
                if (! $exempt && $step->score < $minScore) {
                    $stats['skipped_low_score'] += $step->orders->count();

                    if (! $dryRun) {
                        $this->recordDecline($step, $minScore);
                    }

                    continue;
                }

                foreach ($step->orders as $order) {
                    if ($dryRun) {
                        $stats['assigned']++;

                        continue;
                    }

                    try {
                        $this->assigner->assign(
                            $order,
                            $step->technician,
                            self::modeFor($step),
                            null,
                            [
                                'score' => $step->score,
                                'breakdown' => $step->breakdown,
                                'reasons' => $step->reasons,
                                'history' => $step->history,
                                'device_priority' => $step->device_priority ?? 0,
                                'group_size' => $groupSize,
                                'covered_count' => $step->orders->count(),
                                'group_order_ids' => $groupIds,
                            ],
                        );
                        $stats['assigned']++;
                    } catch (\Throwable $e) {
                        Log::error('auto-assign failed', [
                            'order' => $order->id,
                            'technician' => $step->technician->id,
                            'err' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $stats['unassignable'] += $plan->unassignable->count();
        }

        return $stats;
    }

    /**
     * ثبتِ «نامزد داشتم ولی ندادم».
     *
     * بدونِ این، ردِ خودکار فقط یک شمارندهٔ زودگذر در خروجیِ کامند بود و
     * در پنل هیچ اثری نداشت — اپراتور می‌دید سفارش بدونِ تکنسین مانده،
     * لیستِ پیشنهاد هم چند کاندید نشان می‌داد، و هیچ‌جا نوشته نبود که
     * سیستم عمداً رد کرده و چرا.
     *
     * برای هر سفارش فقط *یک‌بار* ثبت می‌شود؛ پخش هر چند دقیقه اجرا
     * می‌شود و بدونِ این قید، لاگِ سفارش پر از ردیفِ تکراری می‌شد.
     */
    private function recordDecline(object $step, int $minScore): void
    {
        foreach ($step->orders as $order) {
            try {
                $already = OrderAssignmentLog::where('order_id', $order->id)
                    ->where('mode', 'skipped_low_score')
                    ->exists();

                if ($already) {
                    continue;
                }

                OrderAssignmentLog::create([
                    'order_id' => $order->id,
                    'technician_id' => null,
                    'mode' => 'skipped_low_score',
                    'score' => $step->score,
                    'breakdown' => $step->breakdown,
                    'reasons' => $step->reasons,
                    'note' => 'بهترین گزینه «'.($step->technician->firstname_tech ?: $step->technician->first_name)
                        .'» با امتیاز '.$step->score.' بود که از حداقلِ تنظیم‌شده ('.$minScore
                        .') کمتر است، پس تخصیص خودکار انجام نشد و تصمیم به اپراتور واگذار شد.',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::debug('auto-assign decline log failed', [
                    'order' => $order->id, 'err' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * حالتی که در لاگ ثبت می‌شود — به ترتیبِ همان اولویتی که باعثِ
     * انتخابِ این تکنسین شده.
     */
    public static function modeFor(object $step): string
    {
        return match (true) {
            $step->sticky => 'sticky',
            $step->history !== null => 'history',
            default => 'auto',
        };
    }

    /**
     * سفارش‌های واجدِ شرایطِ پخش خودکار.
     *
     * @return Collection<int, Order>
     */
    public function pendingOrders(): Collection
    {
        $finalStatuses = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $s) => $s->isFinal())
            ->map(fn (OrderStatus $s) => $s->value)
            ->all();

        return Order::query()->realOrders()
            ->whereNull('technician_id')
            ->whereNotIn('status', $finalStatuses)
            ->where('created_at', '<=', now()->subMinutes(AssignmentSettings::graceMinutes()))
            ->where('created_at', '>=', now()->subDays(AssignmentSettings::maxAgeDays()))
            ->orderBy('id')
            ->limit(AssignmentSettings::maxPerRun())
            ->get();
    }
}
