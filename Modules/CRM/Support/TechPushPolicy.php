<?php

namespace Modules\CRM\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\PushLog;

/**
 * دو نگهبانِ ارسالِ پوش: «یک‌بار برای هر رویداد» و «ساعتِ مجاز» — همان دو
 * قاعده‌ای که `TechSmsPolicy` برای پیامک اعمال می‌کند.
 *
 * چرا کلاسِ جدا و نه استفادهٔ مستقیم از `TechSmsPolicy`: منبعِ حقیقتِ
 * ضدِتکرار فرق می‌کند. پیامک از `crm_sms_logs` می‌خواند و پوش از
 * `crm_push_logs`؛ اگر یکی باشند، تکنسینی که پیامکِ تخصیص گرفته دیگر پوش
 * نمی‌گیرد — در حالی که سند صریح می‌گوید رویدادِ ۱ باید از «هر دو» کانال
 * برود.
 *
 * ولی اثرِ انگشت‌ها عمداً مشترک‌اند: هر دو کانال باید یک تعریف از «این
 * همان تخصیصِ قبلی است» داشته باشند، وگرنه گزارشِ مقایسه‌ای بی‌معنا
 * می‌شود.
 */
final class TechPushPolicy
{
    /** کلیدِ تنظیمات — جدا از پیامک، تا ادمین بتواند مستقل تنظیم کند. */
    public const START_KEY = 'tech_push_quiet_start';

    public const END_KEY = 'tech_push_quiet_end';

    public const DEFAULT_START = 8;

    public const DEFAULT_END = 22;

    /**
     * رویدادهایی که پنجرهٔ ساعتِ مجاز رویشان اعمال می‌شود.
     *
     * فقط همان دو موردی که سند نام برد (اطلاعیه و پیامِ کارشناس). بقیه
     * مهلت‌دار یا واکنشی‌اند: اعلانی که تا صبح نگه داشته شود، خودش
     * بی‌معنا می‌شود.
     */
    public const GOVERNED = [
        PushEvent::Announcement->value,
        PushEvent::OperatorMessage->value,
    ];

    /** رویدادهایی که ضدِتکرار رویشان اعمال می‌شود. */
    public const DEDUPED = [
        PushEvent::OrderAssigned->value,
        PushEvent::OrderStatusChanged->value,
        PushEvent::OrderReturned->value,
        PushEvent::SlaDue->value,
    ];

    /**
     * آیا این پوش همین حالا مجاز است؟
     *
     * @param  string|null  $fingerprint  اثرِ انگشتِ این نوبتِ رویداد؛ اگر
     *                                    بیاید، یکتایی روی همین رشته سنجیده
     *                                    می‌شود نه فقط (تکنسین، رویداد).
     */
    public static function allows(
        PushEvent $event,
        int $technicianId,
        ?int $orderId = null,
        ?string $fingerprint = null,
        ?CarbonImmutable $now = null
    ): bool {
        if (! self::withinWindow($event, $now)) {
            return false;
        }

        return ! self::alreadySent($event, $technicianId, $orderId, $fingerprint);
    }

    /** آیا ساعتِ فعلی داخلِ پنجرهٔ مجاز است (یا رویداد خارج از دامنه است)؟ */
    public static function withinWindow(PushEvent $event, ?CarbonImmutable $now = null): bool
    {
        if (! in_array($event->value, self::GOVERNED, true)) {
            return true;
        }

        $start = self::hour(self::START_KEY, self::DEFAULT_START);
        $end = self::hour(self::END_KEY, self::DEFAULT_END);

        // پنجرهٔ خالی یعنی محدودیتی نیست.
        if ($start === $end) {
            return true;
        }

        $hour = ($now ?? CarbonImmutable::now(config('app.timezone')))->hour;

        // پنجرهٔ عادی (۸ تا ۲۲) یا پنجرهٔ گذرنده از نیمه‌شب (۲۲ تا ۸).
        return $start < $end
            ? ($hour >= $start && $hour < $end)
            : ($hour >= $start || $hour < $end);
    }

    /** آیا قبلاً برای همین (تکنسین، رویداد، اثرِ انگشت) ارسالِ موفق ثبت شده؟ */
    public static function alreadySent(
        PushEvent $event,
        int $technicianId,
        ?int $orderId = null,
        ?string $fingerprint = null
    ): bool {
        if (! in_array($event->value, self::DEDUPED, true)) {
            return false;
        }

        $query = DB::table('crm_push_logs')
            ->where('technician_id', $technicianId)
            ->where('event_key', $event->value)
            ->whereIn('status', [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED]);

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        if ($fingerprint !== null) {
            $query->where('fingerprint', $fingerprint);
        }

        return $query->exists();
    }

    // ───────────────────────── اثرِ انگشت‌ها
    //
    // به `TechSmsPolicy` واگذار می‌شوند تا دو کانال یک تعریف داشته باشند.

    /** «این نوبتِ تخصیص» — با تخصیصِ مجدد عوض می‌شود. */
    public static function assignmentFingerprint(Order $order): string
    {
        return TechSmsPolicy::assignmentFingerprint($order);
    }

    /** «این مهلت» — با تغییرِ وضعیت عوض می‌شود. */
    public static function slaFingerprint(Order $order): string
    {
        return TechSmsPolicy::slaFingerprint($order);
    }

    /**
     * «این تغییرِ وضعیت» — وضعیت به‌تنهایی کافی نیست: سفارشی که از
     * «هماهنگ شده» به «باز» و دوباره به «هماهنگ شده» برگردد، رویدادِ
     * تازه‌ای است و باید اعلان بگیرد.
     */
    public static function statusFingerprint(Order $order): string
    {
        return 'status:'.($order->status?->value ?? '')
            .':'.($order->status_changed_at?->getTimestamp() ?? 0);
    }

    /** @return array{start:int, end:int} */
    public static function window(): array
    {
        return [
            'start' => self::hour(self::START_KEY, self::DEFAULT_START),
            'end' => self::hour(self::END_KEY, self::DEFAULT_END),
        ];
    }

    public static function saveWindow(int $start, int $end): void
    {
        CrmSetting::set(self::START_KEY, (string) max(0, min(23, $start)));
        CrmSetting::set(self::END_KEY, (string) max(0, min(23, $end)));
    }

    private static function hour(string $key, int $default): int
    {
        try {
            $raw = CrmSetting::get($key, null);
        } catch (\Throwable) {
            return $default;
        }

        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = (int) $raw;

        return ($value >= 0 && $value <= 23) ? $value : $default;
    }
}
