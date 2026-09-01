<?php

namespace Modules\CRM\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Modules\CRM\Enums\OrderStatus;

/**
 * کستِ مقاومِ وضعیتِ سفارش.
 *
 * برخلافِ کستِ خامِ enum، مقادیرِ ناشناخته/قدیمی (مثلِ `repair_started` که
 * حذف شد ولی ممکن است هنوز در ردیف‌های قدیمیِ DB مانده باشد، به‌ویژه در
 * پنجرهٔ دیپلوی که کد پیش از migrate می‌رود) را به‌جای پرتابِ ValueError و
 * ۵۰۰ کردنِ کلِ درخواست، به نزدیک‌ترین وضعیتِ معتبر (Coordinated) نگاشت
 * می‌کند. مهاجرتِ داده مقادیر را در DB هم اصلاح می‌کند؛ این فقط تورِ ایمنی است.
 */
class OrderStatusCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?OrderStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof OrderStatus) {
            return $value;
        }

        return OrderStatus::tryFrom((string) $value) ?? OrderStatus::Coordinated;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof OrderStatus) {
            return $value->value;
        }

        return OrderStatus::tryFrom((string) $value)?->value ?? OrderStatus::Coordinated->value;
    }
}
