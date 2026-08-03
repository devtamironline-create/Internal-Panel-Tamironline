<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Services\Najva\NajvaPushService;

/**
 * بازنویسیِ ادمین روی یک رویدادِ پوش.
 *
 * هر مقدارِ null یعنی «دست نخورده — از `PushEvent` بخوان». همین باعث
 * می‌شود بهبودِ بعدیِ متن‌های پیش‌فرض در کد به سایت‌هایی برسد که چیزی
 * تغییر نداده‌اند.
 */
class PushEventSetting extends Model
{
    protected $table = 'crm_push_event_settings';

    protected $fillable = [
        'event_key', 'is_enabled', 'title', 'body', 'ttl',
        'quiet_start', 'quiet_end', 'send_sms',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'send_sms' => 'boolean',
        'ttl' => 'integer',
        'quiet_start' => 'integer',
        'quiet_end' => 'integer',
    ];

    /** @var array<string, self|null> */
    private static array $cache = [];

    /**
     * تنظیماتِ ذخیره‌شدهٔ یک رویداد — یا null اگر ادمین دست نزده باشد.
     *
     * در طولِ یک درخواست کش می‌شود: شش رویداد × چند تکنسین یعنی همان
     * کوئری بارها تکرار می‌شد.
     */
    public static function lookup(PushEvent $event): ?self
    {
        if (array_key_exists($event->value, self::$cache)) {
            return self::$cache[$event->value];
        }

        try {
            $row = static::query()->where('event_key', $event->value)->first();
        } catch (\Throwable) {
            // جدول هنوز مهاجرت نشده — نباید ارسال را بشکند.
            $row = null;
        }

        return self::$cache[$event->value] = $row;
    }

    /** بین تست‌ها و بعد از ذخیرهٔ فرم لازم است. */
    public static function flushCache(): void
    {
        self::$cache = [];
    }

    // ───────────────────────── مقادیرِ مؤثر

    public static function enabled(PushEvent $event): bool
    {
        return self::lookup($event)?->is_enabled ?? true;
    }

    public static function titleTemplate(PushEvent $event): string
    {
        $custom = self::lookup($event)?->title;

        return filled($custom) ? $custom : $event->defaultTitle();
    }

    public static function bodyTemplate(PushEvent $event): string
    {
        $custom = self::lookup($event)?->body;

        return filled($custom) ? $custom : $event->defaultBody();
    }

    public static function ttl(PushEvent $event): int
    {
        $custom = self::lookup($event)?->ttl;

        return ($custom !== null && $custom > 0) ? $custom : $event->ttl();
    }

    /**
     * پنجرهٔ ساعتِ همین رویداد، یا null اگر باید از پنجرهٔ سراسری استفاده
     * شود.
     *
     * @return array{start:int, end:int}|null
     */
    public static function window(PushEvent $event): ?array
    {
        $row = self::lookup($event);

        if ($row === null || $row->quiet_start === null || $row->quiet_end === null) {
            return null;
        }

        return ['start' => $row->quiet_start, 'end' => $row->quiet_end];
    }

    // ───────────────────────── اعتبارسنجیِ قالب

    /**
     * متغیرهایی که در قالب آمده‌اند ولی برای این رویداد تعریف نشده‌اند.
     *
     * فرمِ پنل با همین هشدار می‌دهد؛ وگرنه ادمین `{customer_name}` را در
     * اطلاعیه می‌گذارد و روی گوشیِ تکنسین رشتهٔ خالی می‌بیند بی‌آنکه بفهمد
     * چرا.
     *
     * @return list<string>
     */
    public static function unknownVariables(PushEvent $event, ?string $template): array
    {
        if (blank($template)) {
            return [];
        }

        preg_match_all('/\{([a-z_]+)\}/', $template, $matches);

        return array_values(array_unique(array_diff($matches[1] ?? [], $event->variables())));
    }

    /** آیا قالب از سقفِ نجوا رد شده است؟ */
    public static function tooLong(string $field, ?string $template): bool
    {
        $max = $field === 'title' ? NajvaPushService::TITLE_MAX : NajvaPushService::BODY_MAX;

        return mb_strlen((string) $template) > $max;
    }
}
