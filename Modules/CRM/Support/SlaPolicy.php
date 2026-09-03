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
 *   پاسخگو نیست                ۲۴ ساعت از تخصیص (نه از ورود به وضعیت)
 *   هماهنگ شده                 خودِ زمان مراجعهٔ توافق‌شده
 *   معلق                       ۷۲ ساعت از ورود به وضعیت
 *   در انتظار قطعه             ۱۲۰ ساعت (۵ روز)
 *   در انتظار تأیید مشتری      ۲۴ ساعت
 *   باز شده (انتقال تعمیرگاه)  ۲۴۰ ساعت (۱۰ روز) از ورود به وضعیت
 *
 * همهٔ اعداد از پنل ادمین قابل تغییرند (کلید crm_settings: app_sla_hours).
 * اگر مبنای زمانیِ لازم روی سفارش نباشد (مثلاً assigned_at خالی)، مهلت
 * null برمی‌گردد — یعنی «قابل محاسبه نیست»، نه «گذشته».
 */
final class SlaPolicy
{
    /** سقف تخمین تکنسین برای «چند روز برای رفع مشکل» (روز). */
    public const MAX_ESTIMATE_DAYS = 6;

    /** سقف انتخابِ «زمانِ مراجعه» در سفارشِ عادی (روز). */
    public const MAX_VISIT_DAYS = 5;

    /** سقف انتخابِ «زمانِ مراجعه» در سفارشِ بازگشتی (روز). */
    public const MAX_RETURN_VISIT_DAYS = 3;

    /**
     * طولِ بازهٔ زمانیِ مراجعه (ساعت). بازه‌ها همگی ۳ساعته‌اند
     * (۹–۱۲، ۱۲–۱۵، ۱۵–۱۸، ۱۸–۲۱) و فقط «شروعِ» بازه در visit_scheduled_at
     * ذخیره می‌شود؛ برای SLA پایانِ بازه ملاک است، پس این را اضافه می‌کنیم.
     */
    public const VISIT_WINDOW_HOURS = 3;

    /** پیش‌فرضِ ساعت‌ها — کلیدها همان مقادیر OrderStatus هستند. */
    public const DEFAULT_HOURS = [
        'new' => 1,
        'awaiting_coordination' => 1,
        'no_answer' => 24,   // ۱ روز (قبلاً ۴۸)
        'suspended' => 72,
        'awaiting_part' => 120, // ۵ روز (قبلاً ۷)
        'awaiting_customer_approval' => 24,
        'open' => 240,       // ۱۰ روز (قبلاً ۱۴)
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

        // «اجبار به تعیینِ وضعیت» توسطِ ادمین: روی سفارشِ غیرنهایی، مهلت را
        // «گذشته» می‌کنیم تا اپِ تکنسین (مثلِ حالتِ مهلتِ گذشته) قفلِ
        // تمام‌صفحه شود. با اولین تعیینِ وضعیتِ تکنسین، خودِ این پرچم پاک
        // می‌شود. (اگر ستون نباشد، force_review نال است و اثری ندارد.)
        if ($order->force_review && ! $status->isFinal()) {
            return CarbonImmutable::now()->subHour();
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

            // زمانِ توافق‌شده با مشتری خودش مهلت است — و ملاک، «پایانِ» بازهٔ
            // مراجعه است نه شروعش (بازهٔ ۱۲–۱۵ یعنی تا ۱۵ فرصت هست، تأخیر از
            // ۱۵ حساب می‌شود). visit_scheduled_at شروعِ بازه است، پس طولِ بازه
            // را اضافه می‌کنیم. اگر زمان ثبت نشده، «هماهنگ‌شده» بی‌مهلت نماند.
            OrderStatus::Coordinated => self::at($order->visit_scheduled_at)?->addHours(self::VISIT_WINDOW_HOURS)
                ?? self::offset($order->status_changed_at, $hours['coordinated_no_visit']),

            OrderStatus::Suspended => self::offset($order->status_changed_at, $hours['suspended']),
            OrderStatus::AwaitingPart => self::at($order->estimated_ready_at)
                ?? self::offset($order->status_changed_at, $hours['awaiting_part']),
            OrderStatus::AwaitingCustomerApproval => self::offset(
                $order->status_changed_at, $hours['awaiting_customer_approval']
            ),

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

    /**
     * بیشترین تاریخی که تکنسین می‌تواند به‌عنوان «زمانِ مراجعه» انتخاب کند —
     * سفارشِ بازگشتی سقفِ کوتاه‌تری دارد.
     */
    public static function maxVisitDate(bool $isReturn = false): CarbonImmutable
    {
        $days = $isReturn ? self::MAX_RETURN_VISIT_DAYS : self::MAX_VISIT_DAYS;

        return CarbonImmutable::now()->addDays($days)->endOfDay();
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
