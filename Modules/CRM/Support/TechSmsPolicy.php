<?php

namespace Modules\CRM\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;

/**
 * دو نگهبانِ ارسالِ پیامک به تکنسین: «یک‌بار برای هر رویداد» و «ساعتِ
 * مجاز».
 *
 * چرا لازم است: اپِ تکنسین پوشِ آنی ندارد و پیامک تنها کانالِ آنی است.
 * همین باعث می‌شود چند مسیرِ مختلف (اکشنِ اپراتور، پخشِ خودکار، دستورِ
 * زمان‌بندی) برای یک رویداد پیامک بزنند. بدونِ ضدِتکرار، تکنسین برای یک
 * سفارش چند پیامکِ یکسان می‌گیرد و اعتمادش به کانال از بین می‌رود.
 *
 * منبعِ حقیقتِ ضدِتکرار خودِ `crm_sms_logs` است — جدولِ جداگانه نساختیم
 * چون همان ردیفِ لاگ دقیقاً همان چیزی را می‌گوید که می‌خواهیم بدانیم:
 * آیا این (سفارش، رویداد، شماره) قبلاً ارسالِ موفق داشته است.
 */
final class TechSmsPolicy
{
    /** کلیدِ تنظیمات: ساعتِ شروع و پایانِ پنجرهٔ مجاز (۰..۲۳). */
    public const START_KEY = 'tech_sms_quiet_start';

    public const END_KEY = 'tech_sms_quiet_end';

    public const DEFAULT_START = 8;

    public const DEFAULT_END = 22;

    /**
     * رویدادهای فوری (🔴) — از پنجرهٔ ساعتِ مجاز مستثنا هستند.
     *
     * منطق: این دو رویداد مهلت‌دارند. اگر تا صبح نگه داشته شوند، خودِ
     * پیام بی‌معنا می‌شود — تکنسین مهلتی را از دست داده که پیامک قرار
     * بود جلویش را بگیرد.
     */
    public const URGENT = [
        SmsTrigger::OrderAssignedTech->value,
        SmsTrigger::TechSlaDue->value,
    ];

    /**
     * رویدادهایی که ضدِتکرار روی آن‌ها اعمال می‌شود، همراه با «کلیدِ
     * یکتایی». برای بیشترِ رویدادها کلید همان (سفارش + رویداد) است؛ ولی
     * تخصیص و یادآورِ مهلت می‌توانند قانوناً تکرار شوند و کلیدشان باید
     * دقیق‌تر باشد (تخصیصِ مجدد = رویدادِ جدید).
     */
    public const DEDUPED = [
        SmsTrigger::OrderAssignedTech->value,
        SmsTrigger::TechSlaWarning->value,
        SmsTrigger::TechSlaDue->value,
        SmsTrigger::TechOrderReturned->value,
        SmsTrigger::TechOrderCancelled->value,
    ];

    /**
     * آیا این پیامک همین حالا مجاز به ارسال است؟
     *
     * @param  string|null  $fingerprint  اثرِ انگشتِ رویداد؛ اگر بیاید،
     *                                    یکتایی روی همین رشته سنجیده می‌شود
     *                                    نه فقط (سفارش، رویداد).
     */
    public static function allows(
        Order $order,
        SmsTrigger $trigger,
        ?string $recipientMobile,
        ?string $fingerprint = null,
        ?CarbonImmutable $now = null
    ): bool {
        if (blank($recipientMobile)) {
            return false;
        }

        if (! self::withinWindow($trigger, $now)) {
            return false;
        }

        return ! self::alreadySent($order, $trigger, $recipientMobile, $fingerprint);
    }

    /**
     * رویدادهایی که پنجرهٔ ساعتِ مجاز رویشان اعمال می‌شود — همان جدولِ
     * نامهٔ تیمِ اپ.
     *
     * عمداً به همهٔ پیامک‌های تکنسین تعمیم نمی‌دهیم: «شارژ کیف‌پول» یا
     * «صدور فاکتور» رفتارِ جاافتاده‌ای دارند و ساکت‌کردنشان تغییرِ
     * ناخواستهٔ رفتارِ موجود است، نه خواستهٔ این نامه.
     */
    public const GOVERNED = [
        SmsTrigger::OrderAssignedTech->value,
        SmsTrigger::TechSlaWarning->value,
        SmsTrigger::TechSlaDue->value,
        SmsTrigger::TechOrderReturned->value,
    ];

    /** آیا ساعتِ فعلی داخلِ پنجرهٔ مجاز است (یا رویداد فوری/خارج از دامنه است)؟ */
    public static function withinWindow(SmsTrigger $trigger, ?CarbonImmutable $now = null): bool
    {
        if (! in_array($trigger->value, self::GOVERNED, true)) {
            return true;
        }

        if (in_array($trigger->value, self::URGENT, true)) {
            return true;
        }

        $start = self::hour(self::START_KEY, self::DEFAULT_START);
        $end = self::hour(self::END_KEY, self::DEFAULT_END);

        // پنجرهٔ خالی/کامل — یعنی محدودیتی نیست.
        if ($start === $end) {
            return true;
        }

        $hour = ($now ?? CarbonImmutable::now(config('app.timezone')))->hour;

        // پنجرهٔ عادی (۸ تا ۲۲) یا پنجرهٔ گذرنده از نیمه‌شب (۲۲ تا ۸).
        return $start < $end
            ? ($hour >= $start && $hour < $end)
            : ($hour >= $start || $hour < $end);
    }

    /** آیا قبلاً برای همین (سفارش، رویداد، شماره) ارسالِ موفق ثبت شده؟ */
    public static function alreadySent(
        Order $order,
        SmsTrigger $trigger,
        string $recipientMobile,
        ?string $fingerprint = null
    ): bool {
        if (! in_array($trigger->value, self::DEDUPED, true)) {
            return false;
        }

        $query = DB::table('crm_sms_logs')
            ->where('order_id', $order->id)
            ->where('trigger_key', $trigger->value)
            ->where('recipient_mobile', $recipientMobile)
            ->where('status', 'success');

        // اثرِ انگشت داخلِ body ذخیره می‌شود (پیوستِ انتهایی). بدونِ آن،
        // تخصیصِ مجددِ همان سفارش به همان تکنسین دیگر پیامک نمی‌گرفت.
        if ($fingerprint !== null) {
            $query->where('body', 'like', '%'.self::stamp($fingerprint).'%');
        }

        return $query->exists();
    }

    /**
     * اثرِ انگشتِ رویداد برای پیوست به بدنهٔ لاگ.
     *
     * عمداً یک برچسبِ کوتاه و ماشین‌خوان است، نه چیزی که به گیرنده ارسال
     * شود — فقط در ستونِ body لاگ می‌نشیند.
     */
    public static function stamp(string $fingerprint): string
    {
        return '[#'.$fingerprint.']';
    }

    /** اثرِ انگشتِ استانداردِ «این نوبتِ تخصیص» — با تخصیصِ مجدد عوض می‌شود. */
    public static function assignmentFingerprint(Order $order): string
    {
        return 'assign:'.($order->assigned_at?->getTimestamp() ?? 0);
    }

    /** اثرِ انگشتِ استانداردِ «این مهلت» — با تغییرِ وضعیت عوض می‌شود. */
    public static function slaFingerprint(Order $order): string
    {
        $deadline = SlaPolicy::deadlineFor($order);

        return 'sla:'.($order->status?->value ?? '').':'.($deadline?->getTimestamp() ?? 0);
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
        $raw = CrmSetting::get($key, null);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = (int) $raw;

        return ($value >= 0 && $value <= 23) ? $value : $default;
    }
}
