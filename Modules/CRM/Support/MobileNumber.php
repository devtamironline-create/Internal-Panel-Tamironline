<?php

namespace Modules\CRM\Support;

/**
 * شمارهٔ موبایلِ ایران: نرمال‌سازی و اعتبارسنجی.
 *
 * چرا نرمال‌سازی و نه فقط یک regex: اپراتور شماره را از جاهای مختلف کپی می‌کند
 * و تقریباً هیچ‌وقت تمیز نیست — رقمِ فارسی («۰۹۱۲…»)، پیش‌شمارهٔ بین‌المللی
 * (`+98`)، فاصله یا خط‌تیره. اگر فقط سخت‌گیری کنیم، شماره‌های *درست* پشتِ‌سرِ هم
 * رد می‌شوند و اپراتور فکر می‌کند فرم خراب است.
 *
 * ولی بعد از تمیزکاری دیگر ارفاقی در کار نیست: خروجی باید دقیقاً ۱۱ رقم و با
 * `09` شروع شود. همین قاعده در ماژول Staff هم هست.
 */
final class MobileNumber
{
    /** قاعدهٔ اعتبارسنجی — بعد از نرمال‌سازی اعمال می‌شود. */
    public const PATTERN = '/^09[0-9]{9}$/';

    public const RULE = 'regex:'.self::PATTERN;

    public const MESSAGE = 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود (مثال: 09123456789).';

    /**
     * قاعدهٔ نرم‌تر برای فرم‌هایی که لید هم ثبت می‌کنند: مشتری ممکن است با
     * تلفنِ ثابت تماس بگیرد و اپراتور جز همان شماره چیزی ندارد. موبایل یا
     * ثابت با کدِ شهر — هر دو ۱۱ رقم و با ۰ شروع می‌شوند.
     */
    public const PHONE_PATTERN = '/^0[0-9]{10}$/';

    public const PHONE_RULE = 'regex:'.self::PHONE_PATTERN;

    public const PHONE_MESSAGE = 'شماره تماس باید ۱۱ رقم و با ۰ شروع شود (موبایل مثل 09123456789 یا ثابت با کد شهر مثل 02177612345).';

    /** @var array<string, string> */
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * تبدیل به شکلِ متعارف `09xxxxxxxxx`.
     *
     * اگر ورودی اصلاً شمارهٔ موبایل نباشد، هرچه رقم داشته باشد برمی‌گردد و
     * `isValid()` ردش می‌کند — این متد قضاوت نمی‌کند، فقط تمیز می‌کند.
     */
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/u', '', strtr((string) $value, self::DIGITS));

        if ($digits === '' || $digits === null) {
            return '';
        }

        // پیش‌شمارهٔ کشور به هر شکلی که نوشته شده باشد.
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }

        // «9123456789» — صفرِ ابتدایی هنگام کپی از جدول یا اکسل می‌افتد.
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public static function isValid(?string $value): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($value));
    }

    /** موبایل یا تلفنِ ثابت با کدِ شهر — همان قاعدهٔ PHONE_PATTERN. */
    public static function isValidPhone(?string $value): bool
    {
        return (bool) preg_match(self::PHONE_PATTERN, self::normalize($value));
    }
}
