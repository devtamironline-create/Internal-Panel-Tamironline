<?php

namespace Modules\CRM\Support;

use Carbon\CarbonImmutable;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;

/**
 * «مهلت تعیین وضعیت» — تکنسین برای هر وضعیت چقدر فرصت دارد.
 *
 * قواعد عملیاتی مصوب (ساعت):
 *   جدید / در انتظار هماهنگی   ۱ ساعت از تخصیص
 *   پاسخگو نیست                ۴۸ ساعت از تخصیص (نه از ورود به وضعیت)
 *   هماهنگ شده                 خودِ زمان مراجعهٔ توافق‌شده
 *   انتقال به تعمیرگاه         تخمین خود تکنسین، سقف ۱۴ روز
 *   معلق                       ۷۲ ساعت از ورود به وضعیت
 *   در انتظار قطعه             ۱۶۸ ساعت (۷ روز)
 *   در انتظار تأیید مشتری      ۲۴ ساعت
 *   شروع تعمیر                 ۱۲۰ ساعت (۵ روز)
 *   باز شده (انتقال تعمیرگاه)  ۳۳۶ ساعت (۱۴ روز) از ورود به وضعیت
 *
 * همهٔ اعداد از پنل ادمین قابل تغییرند (کلید crm_settings: app_sla_hours).
 * اگر مبنای زمانیِ لازم روی سفارش نباشد (مثلاً assigned_at خالی)، مهلت
 * null برمی‌گردد — یعنی «قابل محاسبه نیست»، نه «گذشته».
 */
final class SlaPolicy
{
    /** سقف تخمین تکنسین برای آماده‌شدن دستگاه (روز). */
    public const MAX_ESTIMATE_DAYS = 14;

    /** پیش‌فرضِ ساعت‌ها — کلیدها همان مقادیر OrderStatus هستند. */
    public const DEFAULT_HOURS = [
        'new' => 1,
        'awaiting_coordination' => 1,
        'no_answer' => 48,
        'suspended' => 72,
        'awaiting_part' => 168,
        'awaiting_customer_approval' => 24,
        'repair_started' => 120,
        'open' => 336,
        // «هماهنگ‌شده» که زمانِ مراجعه ثبت نشده — نباید بی‌مهلت بماند.
        'coordinated_no_visit' => 24,
    ];

    /** برچسبِ فارسیِ هر مهلت برای فرمِ تنظیمات. */
    public const HOUR_LABELS = [
        'new' => 'جدید — تماس اول با مشتری',
        'awaiting_coordination' => 'در انتظار هماهنگی',
        'no_answer' => 'مشتری پاسخگو نیست (از زمان تخصیص)',
        'suspended' => 'معلق / نامشخص',
        'awaiting_part' => 'در انتظار قطعه',
        'awaiting_customer_approval' => 'در انتظار تأیید مشتری',
        'repair_started' => 'شروع تعمیر',
        'open' => 'باز شده (انتقال به تعمیرگاه)',
        'coordinated_no_visit' => 'هماهنگ‌شده بدون زمانِ مراجعهٔ ثبت‌شده',
    ];

    private static ?array $hours = null;

    /** @return array<string, int> */
    public static function hours(): array
    {
        if (self::$hours !== null) {
            return self::$hours;
        }

        $stored = CrmSetting::getJson('app_sla_hours', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $out = [];
        foreach (self::DEFAULT_HOURS as $key => $default) {
            $value = (int) ($stored[$key] ?? $default);
            $out[$key] = $value > 0 ? $value : $default;
        }

        return self::$hours = $out;
    }

    public static function saveHours(array $values): void
    {
        $clean = [];
        foreach (self::DEFAULT_HOURS as $key => $default) {
            $value = (int) ($values[$key] ?? $default);
            // سقف ۳۰ روز — عددِ بزرگ‌تر یعنی عملاً بی‌مهلت و اشتباهِ تایپی.
            $clean[$key] = max(1, min(720, $value));
        }

        CrmSetting::setJson('app_sla_hours', $clean);
        self::$hours = null;
    }

    /**
     * مهلتِ تصمیمِ این سفارش، یا null اگر وضعیت مهلت ندارد / مبنای زمانی
     * موجود نیست.
     */
    public static function deadlineFor(Order $order): ?CarbonImmutable
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        // هر وضعیتی که در match زیر نیاید خودبه‌خود بی‌مهلت است — از جمله
        // transit («ایاب و ذهاب»): آن یک وضعیتِ بستنِ سفارش است (مراجعه شده،
        // تعمیری نبوده، فقط هزینهٔ ایاب و ذهاب) و هیچ تصمیمی از تکنسین
        // نمی‌خواهد. estimated_ready_at قدیمیِ باقی‌مانده روی این سفارش‌ها
        // نباید مهلت بسازد — اپ روی مهلتِ گذشته قفلِ تمام‌صفحه می‌گذارد.
        if (! $status) {
            return null;
        }

        $hours = self::hours();

        return match ($status) {
            // فاز هماهنگی: مبنا لحظهٔ تخصیص است، نه ورود به وضعیت — وگرنه
            // تکنسینی که مدام وضعیت را جابه‌جا می‌کند مهلتش تازه می‌شود.
            // fallback به status_changed_at (که همیشه پر است): سفارش‌هایی
            // که از مسیرهای قدیمیِ بدونِ assigned_at تخصیص گرفته‌اند نباید
            // از SLA نامرئی بمانند.
            OrderStatus::New, OrderStatus::AwaitingCoordination => self::offset(
                $order->assigned_at ?? $order->status_changed_at, $hours[$status->value] ?? 1
            ),
            OrderStatus::NoAnswer => self::offset(
                $order->assigned_at ?? $order->status_changed_at, $hours['no_answer']
            ),

            // زمانِ توافق‌شده با مشتری خودش مهلت است؛ اگر ثبت نشده،
            // «هماهنگ‌شده» نباید بی‌مهلت بماند.
            OrderStatus::Coordinated => self::at($order->visit_scheduled_at)
                ?? self::offset($order->status_changed_at, $hours['coordinated_no_visit']),

            OrderStatus::Suspended => self::offset($order->status_changed_at, $hours['suspended']),
            OrderStatus::AwaitingPart => self::at($order->estimated_ready_at)
                ?? self::offset($order->status_changed_at, $hours['awaiting_part']),
            OrderStatus::AwaitingCustomerApproval => self::offset(
                $order->status_changed_at, $hours['awaiting_customer_approval']
            ),
            OrderStatus::RepairStarted => self::offset($order->status_changed_at, $hours['repair_started']),

            // قاعدهٔ عملیاتی جدید: «باز شده» (انتقال به تعمیرگاه) ۱۴ روز از
            // ورود به وضعیت مهلت دارد — قبلاً بی‌مهلت بود.
            OrderStatus::Open => self::offset($order->status_changed_at, $hours['open']),

            default => null,
        };
    }

    /** آیا مهلت گذشته است؟ */
    public static function isOverdue(Order $order, ?CarbonImmutable $now = null): bool
    {
        $deadline = self::deadlineFor($order);

        return $deadline !== null && $deadline->isBefore($now ?? CarbonImmutable::now());
    }

    /** بیشترین تاریخی که تکنسین می‌تواند به‌عنوان تخمین انتخاب کند. */
    public static function maxEstimateDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(self::MAX_ESTIMATE_DAYS)->endOfDay();
    }

    private static function offset(mixed $base, int $hours): ?CarbonImmutable
    {
        $at = self::at($base);

        return $at?->addHours($hours);
    }

    private static function at(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** پاک‌کردن حافظه — برای تست‌ها. */
    public static function flush(): void
    {
        self::$hours = null;
    }
}
