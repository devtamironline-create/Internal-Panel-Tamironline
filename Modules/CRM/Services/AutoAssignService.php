<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
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
