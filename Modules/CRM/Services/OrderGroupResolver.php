<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;

/**
 * تشخیصِ «گروهِ سفارش» — سفارش‌هایی که باید تا حدِ امکان به یک تکنسین
 * برسند تا مشتری در یک مراجعه سرویس بگیرد.
 *
 * تعریفِ گروه (تصمیم محصولی):
 *   همان مشتری + همان آدرس + همان روزِ تقویمی.
 *
 * چرا «همان روز»: مشتری معمولاً در یک نشست چند دستگاه را ثبت می‌کند.
 * بازکردنِ بازه به چند روز باعث می‌شود سفارش‌های رهاشدهٔ قدیمی هم وارد
 * گروه شوند و تکنسین را الکی قفل کنند.
 *
 * کلیدِ آدرس: اگر `address_id` پر باشد (فقط اپ موبایل آن را ست می‌کند)
 * از همان استفاده می‌شود؛ وگرنه از متنِ نرمال‌شدهٔ `address` به‌همراه
 * شهر و منطقه. اگر متنِ آدرس خالی باشد، فقط شهر/منطقه ملاک است — چون
 * سفارش‌های یک مشتری در یک روز و یک شهر به احتمال زیاد یک محل‌اند.
 */
final class OrderGroupResolver
{
    /**
     * هم‌گروه‌هایِ بدون‌تکنسینِ یک سفارش — شاملِ خودِ سفارش.
     *
     * @return Collection<int, Order>
     */
    public function siblingsOf(Order $order): Collection
    {
        if (! $order->customer_id || ! $order->created_at) {
            return collect([$order]);
        }

        $key = self::addressKey($order);

        $candidates = Order::query()->realOrders()
            ->whereNull('technician_id')
            ->where('customer_id', $order->customer_id)
            ->whereDate('created_at', $order->created_at->toDateString())
            ->whereNotIn('status', self::finalStatuses())
            ->with(['brand:id,name', 'device:id,name'])
            ->orderBy('id')
            ->get();

        $group = $candidates
            ->filter(fn (Order $o) => self::addressKey($o) === $key)
            ->values();

        // اگر خودِ سفارش (به هر دلیلی، مثلاً وضعیت نهایی) در نتیجه نبود،
        // دستی اضافه‌اش می‌کنیم تا فراخواننده همیشه سفارشِ جاری را ببیند.
        if (! $group->contains(fn (Order $o) => $o->id === $order->id)) {
            $group->prepend($order);
        }

        return $group->values();
    }

    /**
     * تکنسین‌هایی که به هم‌گروه‌هایِ *تخصیص‌داده‌شدهٔ* این سفارش خورده‌اند.
     * برای قاعدهٔ چسبندگی: سفارشِ چهارمِ همان آدرس باید به همان تکنسین
     * برود، نه یک نفر جدید.
     *
     * @return array<int, int> فهرست technician_id
     */
    public function assignedSiblingTechnicianIds(Order $order): array
    {
        if (! $order->customer_id || ! $order->created_at) {
            return [];
        }

        $key = self::addressKey($order);

        return Order::query()->realOrders()
            ->whereNotNull('technician_id')
            ->where('customer_id', $order->customer_id)
            ->whereKeyNot($order->id)
            ->whereDate('created_at', $order->created_at->toDateString())
            ->whereNotIn('status', self::finalStatuses())
            ->get(['id', 'technician_id', 'address_id', 'address', 'city_id', 'district_id'])
            ->filter(fn (Order $o) => self::addressKey($o) === $key)
            ->pluck('technician_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * دسته‌بندیِ یک مجموعه سفارش به گروه‌ها — برای jobِ تخصیص خودکار که
     * چند صد سفارش را یک‌جا پردازش می‌کند و نباید به‌ازای هر سفارش کوئری
     * جدا بزند.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<string, Collection<int, Order>>
     */
    public function groupBy(Collection $orders): Collection
    {
        return $orders->groupBy(function (Order $o) {
            $day = $o->created_at?->toDateString() ?? 'no-date';
            $customer = $o->customer_id ?: 'solo-'.$o->id;

            return $customer.'|'.$day.'|'.self::addressKey($o);
        });
    }

    /** کلیدِ یکتای آدرس برای مقایسه. */
    public static function addressKey(Order $order): string
    {
        if ($order->address_id) {
            return 'addr:'.$order->address_id;
        }

        $text = self::normalize((string) $order->address);
        $place = ((int) $order->city_id).':'.((int) $order->district_id);

        return $text === '' ? 'place:'.$place : 'text:'.$place.':'.$text;
    }

    /** وضعیت‌هایی که یعنی سفارش تمام‌شده و نباید وارد گروه شود. */
    private static function finalStatuses(): array
    {
        return collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $s) => $s->isFinal())
            ->map(fn (OrderStatus $s) => $s->value)
            ->all();
    }

    /**
     * نرمال‌سازیِ متنِ آدرس تا «خ. ولیعصر پ ۱۲» و «خیابان ولیعصر پلاک 12»
     * تا حدِ معقولی یکی شمرده شوند: یکسان‌سازیِ عربی/فارسی، تبدیل ارقام
     * فارسی به لاتین، حذف نیم‌فاصله و نشانه‌گذاری، و فشرده‌کردنِ فاصله‌ها.
     */
    private static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            ['ي', 'ك', 'ۀ', 'ة', 'أ', 'إ', 'آ', '‌', '‍', 'ـ'],
            ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', ' ', '', ''],
            $value
        );

        $value = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value
        );

        // نشانه‌گذاری و هرچه حرف/رقم نیست → فاصله
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return Str::lower(trim($value));
    }
}
