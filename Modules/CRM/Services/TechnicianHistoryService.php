<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\AssignmentSettings;

/**
 * «تکنسینِ سابقهٔ همان دستگاه» — اگر همین مشتری همین دستگاه را قبلاً
 * تعمیر کرده و کار انجام شده، سفارش بعدی به همان تکنسین برگردد.
 *
 * دلیل محصولی: مشتری با تکنسینی که دستگاهش را می‌شناسد راحت‌تر است و
 * تکنسین هم سابقهٔ تعمیر قبلی را می‌داند؛ کیفیت و سرعت بالا می‌رود.
 *
 * قاعده باطل می‌شود اگر روی همان دستگاهِ همان مشتری سابقهٔ «برگشتی
 * گارانتی» وجود داشته باشد — یعنی تعمیر قبلی نگرفته و فرستادنِ دوبارهٔ
 * همان نفر تکرارِ همان نتیجه است.
 *
 * توجه: این سرویس فقط «کاندید» معرفی می‌کند. قیدهای عملیاتی (ظرفیت،
 * آمادهٔ دریافت بودن، تگ‌های شهر/برند/دستگاه) را برنامه‌ریز با همان
 * rejectionFor() همیشگی بررسی می‌کند؛ اگر تکنسین سابق رد شود، منطق
 * عادی جای آن را می‌گیرد.
 */
final class TechnicianHistoryService
{
    /**
     * نگاشتِ «شناسهٔ سفارش ← تکنسینِ سابق» برای مجموعه‌ای از سفارش‌ها.
     * دو کوئری برای کل مجموعه می‌زند، نه به‌ازای هر سفارش.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{technician_id:int, order_id:int, order_code:?string, months:int}>
     */
    public function mapFor(Collection $orders): array
    {
        if (! AssignmentSettings::historyEnabled() || $orders->isEmpty()) {
            return [];
        }

        $targets = $orders->filter(fn (Order $o) => $o->device_id && self::customerKey($o) !== null);
        if ($targets->isEmpty()) {
            return [];
        }

        $past = $this->pastOrders($targets);
        if ($past->isEmpty()) {
            return [];
        }

        $cutoff = now()->subMonths(AssignmentSettings::historyMonths());

        // گروه‌بندی سوابق بر اساس «مشتری + دستگاه»
        $byKey = [];
        foreach ($past as $row) {
            $key = self::historyKey($row);
            if ($key === null) {
                continue;
            }
            $byKey[$key][] = $row;
        }

        $result = [];
        $wanted = [];

        foreach ($targets as $order) {
            $key = self::historyKey($order);
            $rows = $byKey[$key] ?? null;
            if (! $rows) {
                continue;
            }

            // سابقهٔ برگشتی گارانتی روی این دستگاه = قاعده خاموش.
            foreach ($rows as $row) {
                if (self::statusOf($row) === OrderStatus::Returned->value) {
                    continue 2;
                }
            }

            $best = null;
            foreach ($rows as $row) {
                if ((int) $row->id === (int) $order->id) {
                    continue;
                }
                if (self::statusOf($row) !== OrderStatus::Completed->value) {
                    continue;
                }
                $at = $row->completed_at ?? $row->created_at;
                if (! $at || $at->lt($cutoff)) {
                    continue;
                }
                if ($best === null || $at->gt($best['at'])) {
                    $best = ['row' => $row, 'at' => $at];
                }
            }

            if ($best === null) {
                continue;
            }

            $technicianId = (int) $best['row']->technician_id;
            $wanted[$technicianId] = true;
            $result[(int) $order->id] = [
                'technician_id' => $technicianId,
                'order_id' => (int) $best['row']->id,
                'order_code' => $best['row']->order_code,
                'months' => (int) $best['at']->diffInMonths(now()),
            ];
        }

        if (empty($result)) {
            return [];
        }

        // فقط تکنسین‌هایی که هنوز فعال و قابل پیشنهادند.
        $usable = Technician::query()
            ->whereIn('id', array_keys($wanted))
            ->where('status', 'active')
            ->where('exclude_from_suggestions', false)
            ->pluck('id')
            ->flip();

        return array_filter($result, fn (array $hit) => $usable->has($hit['technician_id']));
    }

    /** نسخهٔ تک‌سفارشی — برای نمایش در پنل. */
    public function previousTechnicianFor(Order $order): ?array
    {
        return $this->mapFor(collect([$order]))[$order->id] ?? null;
    }

    /**
     * سوابقِ «انجام‌شده» و «برگشتی گارانتی» همهٔ مشتری/دستگاه‌های هدف.
     *
     * @param  Collection<int, Order>  $targets
     * @return Collection<int, Order>
     */
    private function pastOrders(Collection $targets): Collection
    {
        $deviceIds = $targets->pluck('device_id')->filter()->unique()->values()->all();
        $customerIds = $targets->pluck('customer_id')->filter()->unique()->values()->all();
        $mobiles = $targets->whereNull('customer_id')
            ->pluck('customer_mobile')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($deviceIds) || (empty($customerIds) && empty($mobiles))) {
            return collect();
        }

        return Order::query()->realOrders()
            ->whereNotNull('technician_id')
            ->whereIn('device_id', $deviceIds)
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Returned->value])
            ->where(function ($q) use ($customerIds, $mobiles) {
                if (! empty($customerIds)) {
                    $q->whereIn('customer_id', $customerIds);
                }
                if (! empty($mobiles)) {
                    $q->orWhereIn('customer_mobile', $mobiles);
                }
            })
            ->orderByDesc('id')
            // سقف ایمنی: مشتریِ پرسابقه نباید کل حافظه را پر کند.
            ->limit(500)
            ->get(['id', 'order_code', 'customer_id', 'customer_mobile', 'device_id',
                'technician_id', 'status', 'completed_at', 'created_at']);
    }

    /** کلیدِ «مشتری + دستگاه». */
    private static function historyKey(Order $order): ?string
    {
        $customer = self::customerKey($order);

        return ($customer === null || ! $order->device_id)
            ? null
            : $customer.'|d:'.$order->device_id;
    }

    /**
     * شناسهٔ مشتری — ترجیحاً customer_id؛ برای ثبت‌های مهمان که این ستون
     * خالی است، شمارهٔ موبایلِ نرمال‌شده.
     */
    private static function customerKey(Order $order): ?string
    {
        if ($order->customer_id) {
            return 'c:'.$order->customer_id;
        }

        $mobile = preg_replace('/\D+/', '', (string) $order->customer_mobile);
        if ($mobile === '' || $mobile === null) {
            return null;
        }

        return 'm:'.$mobile;
    }

    /** مقدار خامِ وضعیت (cast ممکن است enum برگرداند). */
    private static function statusOf(Order $order): string
    {
        $status = $order->status;

        return $status instanceof OrderStatus ? $status->value : (string) $status;
    }
}
