<?php

namespace Modules\CRM\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';                 // پیش‌نویس
    case Pending = 'pending';             // در انتظار تخصیص
    case Assigned = 'assigned';           // تخصیص‌یافته به تکنسین
    case InProgress = 'in_progress';      // در حال انجام
    case Completed = 'completed';         // تکمیل شده
    case Delivered = 'delivered';         // تحویل داده شده
    case Returned = 'returned';           // برگشت خورده
    case Cancelled = 'cancelled';         // لغو شده
    case Declined = 'declined';           // رد شده

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Pending => 'در انتظار تخصیص',
            self::Assigned => 'تخصیص‌یافته',
            self::InProgress => 'در حال انجام',
            self::Completed => 'تکمیل شده',
            self::Delivered => 'تحویل داده شده',
            self::Returned => 'برگشت خورده',
            self::Cancelled => 'لغو شده',
            self::Declined => 'رد شده',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800',
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Assigned => 'bg-blue-100 text-blue-800',
            self::InProgress => 'bg-indigo-100 text-indigo-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Delivered => 'bg-emerald-100 text-emerald-800',
            self::Returned => 'bg-orange-100 text-orange-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::Declined => 'bg-red-200 text-red-900',
        };
    }

    /** لیست گزینه‌ها برای dropdown */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
