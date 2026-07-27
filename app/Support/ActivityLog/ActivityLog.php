<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ثبتِ اکشن‌های مهم در فایل (کانالِ `activity`).
 *
 * هر رکورد یک خطِ JSON در storage/logs/activity/activity-YYYY-MM-DD.log است و
 * شاملِ «چه کسی، چه زمانی، چه کاری، روی چه رکوردی و چه چیزی تغییر کرد» می‌شود.
 *
 * قواعد:
 *  - نوشتنِ لاگ هرگز نباید جریانِ اصلی را بشکند (همه‌چیز داخل try/catch).
 *  - فیلدهای حساس (رمز، توکن، OTP) هرگز نوشته نمی‌شوند.
 *  - تغییرِ صرفِ timestamp «تغییر» محسوب نمی‌شود.
 */
class ActivityLog
{
    /** شمارندهٔ mute — تودرتو هم درست کار می‌کند. */
    private static int $muted = 0;

    /** ثبت را موقتاً خاموش می‌کند (برای عملیات دسته‌ای مثل ارسال کمپین/ایمپورت). */
    public static function mute(): void
    {
        self::$muted++;
    }

    public static function unmute(): void
    {
        self::$muted = max(0, self::$muted - 1);
    }

    /** اجرای یک بستار بدونِ ثبتِ فعالیت. */
    public static function withoutLogging(callable $callback): mixed
    {
        self::mute();
        try {
            return $callback();
        } finally {
            self::unmute();
        }
    }

    public static function isMuted(): bool
    {
        return self::$muted > 0;
    }

    /**
     * ثبتِ دستیِ یک اکشن (برای کارهایی که رویدادِ Eloquent ندارند —
     * مثلاً ارسال کمپین، خروجی گرفتن، تغییر دسترسی).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $action, string $description, array $payload = []): void
    {
        self::write([
            'action' => $action,
            'description' => $description,
        ] + $payload);
    }

    /** ثبتِ خودکارِ یک رویدادِ مدل. */
    public static function model(string $action, Model $model, ?array $changes = null): void
    {
        // عملیاتِ دسته‌ایِ کنسول (همگام‌سازی ووکامرس، seeder، migration، کران)
        // هزاران رکورد می‌سازد؛ پیش‌فرض فقط اکشنِ کاربرانِ پنل ثبت می‌شود.
        // (ثبت‌های صریح با record() از این قاعده مستثنا هستند.)
        if (app()->runningInConsole() && ! config('activity-log.log_console', false)) {
            return;
        }

        $class = $model::class;

        if (ActivityRegistry::isExcluded($class)) {
            return;
        }

        if ($action === 'updated') {
            $changes ??= self::changesOf($model);
            if ($changes === []) {
                return; // فقط timestamp عوض شده — رویدادِ بی‌معنا
            }
        }

        self::write([
            'action' => $action,
            'entity' => $class,
            'entity_label' => ActivityRegistry::label($class),
            'entity_id' => $model->getKey(),
            'entity_title' => ActivityRegistry::titleOf($model),
            'changes' => $changes,
        ]);
    }

    /**
     * تغییراتِ معنادارِ یک مدل: [field => ['old' => …, 'new' => …]].
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function changesOf(Model $model): array
    {
        $ignored = (array) config('activity-log.ignored_fields', []);
        $out = [];

        foreach ($model->getChanges() as $field => $new) {
            if (in_array($field, $ignored, true)) {
                continue;
            }
            // getRawOriginal (نه getOriginal): از اجرای accessor/cast جلوگیری
            // می‌کند — این کد روی همهٔ مدل‌های سیستم اجرا می‌شود.
            $out[$field] = [
                'old' => self::scalar($field, $model->getRawOriginal($field)),
                'new' => self::scalar($field, $new),
            ];
        }

        return $out;
    }

    /**
     * نوشتنِ نهایی در فایل — به‌همراهِ زمینهٔ درخواست (کاربر/IP/مسیر).
     *
     * @param  array<string, mixed>  $entry
     */
    private static function write(array $entry): void
    {
        if (! config('activity-log.enabled', true) || self::isMuted()) {
            return;
        }

        try {
            $user = self::currentUser();

            $context = array_filter([
                'action' => $entry['action'] ?? 'unknown',
                'description' => $entry['description'] ?? null,
                'entity' => $entry['entity'] ?? null,
                'entity_label' => $entry['entity_label'] ?? null,
                'entity_id' => $entry['entity_id'] ?? null,
                'entity_title' => $entry['entity_title'] ?? null,
                'changes' => ($entry['changes'] ?? null) ?: null,
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'user_type' => $user['type'],
                'ip' => self::clientIp(),
                'method' => request()?->method(),
                'path' => self::path(),
                'meta' => ($entry['meta'] ?? null) ?: null,
            ], fn ($v) => $v !== null && $v !== []);

            Log::channel('activity')->info($entry['action'] ?? 'activity', $context);
        } catch (\Throwable) {
            // لاگ هرگز نباید عملیات را متوقف کند.
        }
    }

    /** کاربرِ فعلی از هر guardِ فعال (پنل/تکنسین/مشتری). */
    private static function currentUser(): array
    {
        foreach (['web' => 'کاربر پنل', 'tech' => 'تکنسین', 'customer' => 'مشتری'] as $guard => $type) {
            try {
                $user = auth()->guard($guard)->user();
            } catch (\Throwable) {
                continue;
            }
            if ($user) {
                return [
                    'id' => $user->getKey(),
                    'name' => self::userName($user),
                    'type' => $type,
                ];
            }
        }

        return [
            'id' => null,
            'name' => app()->runningInConsole() ? 'سیستم (کنسول)' : 'مهمان',
            'type' => app()->runningInConsole() ? 'system' : 'guest',
        ];
    }

    private static function userName(object $user): string
    {
        foreach (['full_name', 'name', 'title'] as $attr) {
            $value = $user->{$attr} ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        $full = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));

        return $full !== '' ? $full : (string) ($user->mobile ?? 'کاربر');
    }

    private static function clientIp(): ?string
    {
        try {
            // REMOTE_ADDR مبناست چون trustProxies(*) هدرها را قابلِ جعل می‌کند.
            return request()?->server('REMOTE_ADDR') ?: request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function path(): ?string
    {
        try {
            if (app()->runningInConsole()) {
                return 'console';
            }

            return Str::limit((string) request()?->path(), 190, '');
        } catch (\Throwable) {
            return null;
        }
    }

    /** مقدارِ امن و کوتاه‌شده برای نوشتن در فایل. */
    private static function scalar(string $field, mixed $value): mixed
    {
        $redacted = (array) config('activity-log.redacted_fields', []);
        $lower = strtolower($field);
        foreach ($redacted as $needle) {
            if (str_contains($lower, strtolower($needle))) {
                return '••••••';
            }
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return Str::limit((string) $json, 300, '…');
        }

        return Str::limit(strip_tags((string) $value), 300, '…');
    }
}
