<?php

namespace Modules\CustomerApp\Support;

use Modules\CRM\Enums\OrderStatus;

/**
 * نگاشت OrderStatus داخلی به رشته‌های مورد انتظار اپ فرانت موبایل.
 *
 * فرانت فقط این مقادیر را می‌شناسد (مطابق سند نیازمندی فرانت):
 *   pending, assigned, scheduled, in_progress, suspended,
 *   completed, cancelled, declined
 *
 * چون enum داخلی ما (new/coordinated/open/...) با CRM پنل و WP مشترک است،
 * این mapping را در یک‌جا متمرکز می‌کنیم تا UI پنل دست‌نخورده بماند ولی
 * API موبایل clean و معنادار باشد.
 *
 *   داخلی              فرانت             status_int (mobile)
 *   ───────────────    ──────────        ──────────
 *   new           →    pending           0
 *   coordinated   →    assigned          1
 *   open          →    scheduled         2
 *   suspended     →    suspended         3
 *   cancelled     →    cancelled         4
 *   completed     →    completed         5
 *   transit       →    in_progress       6   (mobile-friendly عدد — wp 10)
 *   returned      →    in_progress       7   (همراه با returned flag — wp 11)
 *   declined      →    declined          9   (mobile — wp 100)
 *
 * توجه: status_int مخصوص فرانت موبایل است. wpCode() در enum همان عدد WP
 * را برمی‌گرداند و اینجا تغییری نمی‌کند.
 */
final class OrderStatusMapper
{
    public static function toMobileString(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::New => 'pending',
            OrderStatus::Coordinated => 'assigned',
            OrderStatus::Open => 'scheduled',
            OrderStatus::Suspended => 'suspended',
            OrderStatus::Cancelled => 'cancelled',
            OrderStatus::Completed => 'completed',
            OrderStatus::Transit => 'in_progress',
            OrderStatus::Returned => 'in_progress',
            OrderStatus::Declined => 'declined',
        };
    }

    public static function toMobileInt(OrderStatus $status): int
    {
        return match ($status) {
            OrderStatus::New => 0,
            OrderStatus::Coordinated => 1,
            OrderStatus::Open => 2,
            OrderStatus::Suspended => 3,
            OrderStatus::Cancelled => 4,
            OrderStatus::Completed => 5,
            OrderStatus::Transit => 6,
            OrderStatus::Returned => 7,
            OrderStatus::Declined => 9,
        };
    }

    /**
     * آیا این وضعیت نهایی (terminal) است؟ — برای overlay «سفارش بسته شد»
     * در فرانت.
     */
    public static function isTerminal(OrderStatus $status): bool
    {
        return in_array($status, [
            OrderStatus::Completed,
            OrderStatus::Cancelled,
            OrderStatus::Declined,
        ], true);
    }
}
