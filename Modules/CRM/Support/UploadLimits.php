<?php

namespace Modules\CRM\Support;

/**
 * سقفِ *واقعیِ* آپلود — آنچه PHP اجازه می‌دهد، نه آنچه قانونِ اعتبارسنجی
 * ادعا می‌کند.
 *
 * مسئله‌ای که این کلاس حل می‌کند: قاعدهٔ `max:30720` می‌گفت «۳۰ مگابایت
 * قبول است»، ولی اگر `upload_max_filesize` سرور مثلاً ۲ مگابایت باشد،
 * PHP فایل را پیش از رسیدن به لاراول دور می‌اندازد. آن‌وقت قاعدهٔ
 * `uploaded` می‌شکند و تکنسین پیامِ بی‌فایدهٔ «آپلود فیلد device img۱
 * ناموفق بود» را می‌بیند — بدون اینکه بفهمد عکسش بزرگ بوده و باید
 * کوچکش کند.
 *
 * پس سقفِ اعتبارسنجی باید کمینهٔ «سقفِ برنامه» و «سقفِ PHP» باشد و پیامِ
 * خطا باید عددِ واقعی را بگوید.
 */
final class UploadLimits
{
    /** سقفی که برنامه دوست دارد (کیلوبایت) — عکسِ دوربینِ موبایل. */
    public const APP_MAX_KB = 30720;

    /** سقفِ مؤثر برای یک فایل، به کیلوبایت. */
    public static function maxFileKb(): int
    {
        $php = self::bytes(ini_get('upload_max_filesize'));
        $post = self::bytes(ini_get('post_max_size'));

        // post_max_size کلِ درخواست را می‌بندد، پس اگر کوچک‌تر باشد
        // خودش سقفِ واقعیِ فایل است.
        $ceiling = min(
            $php > 0 ? $php : PHP_INT_MAX,
            $post > 0 ? $post : PHP_INT_MAX
        );

        if ($ceiling === PHP_INT_MAX) {
            return self::APP_MAX_KB;
        }

        return max(1, min(self::APP_MAX_KB, (int) floor($ceiling / 1024)));
    }

    /** قاعدهٔ آمادهٔ اعتبارسنجی برای یک تصویرِ اختیاری. */
    public static function imageRule(bool $required = false): string
    {
        return ($required ? 'required' : 'nullable').'|image|max:'.self::maxFileKb();
    }

    /** پیامِ فارسیِ خطای حجم — با عددِ واقعی، تا تکنسین بداند چه کند. */
    public static function tooLargeMessage(): string
    {
        return 'حجم عکس بیشتر از حد مجاز است. حداکثر '.self::humanMax()
            .' — عکس را با کیفیت کمتر بگیرید یا فشرده کنید.';
    }

    /**
     * همان پیام برای قاعدهٔ `uploaded`.
     *
     * این قاعده وقتی می‌شکند که PHP خودش فایل را رد کرده باشد؛ در عملْ
     * تقریباً همیشه یعنی حجم. پیامِ پیش‌فرضِ لاراول («آپلود ناموفق بود»)
     * هیچ راهنمایی‌ای به کاربر نمی‌دهد.
     */
    public static function failedMessage(): string
    {
        return 'آپلود عکس انجام نشد. معمولاً یعنی حجم عکس از حد مجاز سرور ('
            .self::humanMax().') بیشتر بوده؛ با کیفیت کمتر دوباره تلاش کنید.';
    }

    /** سقف به شکلِ خواناى فارسی — مگابایت اگر بزرگ‌تر از ۱ مگ باشد. */
    public static function humanMax(): string
    {
        $kb = self::maxFileKb();

        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' مگابایت'
            : $kb.' کیلوبایت';
    }

    /**
     * آیا کلِ درخواست از post_max_size رد شده؟
     *
     * در این حالت PHP کلِ بدنه را دور می‌اندازد: هم $_POST خالی است هم
     * $_FILES، ولی هدرِ Content-Length بزرگ است. بدونِ این تشخیص،
     * اعتبارسنجی خطاهای گمراه‌کنندهٔ «این فیلد الزامی است» می‌دهد در حالی
     * که کاربر همه را پر کرده بود.
     */
    public static function requestExceededPostMax(): bool
    {
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length <= 0) {
            return false;
        }

        $post = self::bytes(ini_get('post_max_size'));

        return $post > 0 && $length > $post && empty($_POST) && empty($_FILES);
    }

    /** «۲M» / «8M» / «1G» → بایت. */
    public static function bytes(mixed $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
