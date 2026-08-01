<?php

namespace Modules\Seo\Support;

/**
 * تورِ ایمنیِ نهاییِ طولِ Title و Description.
 *
 * قالب‌های سئو طوری نوشته شده‌اند که تقریباً همهٔ صفحات زیرِ حد بمانند، ولی
 * ترکیبِ نامِ بلند (مثلاً «ماشین لباسشویی الکترواستیل») چند کاراکتر رد می‌کند و
 * گوگل دُمِ عنوان را با «…» می‌بُرد. این کلاس همان چند مورد را می‌گیرد.
 *
 * قاعدهٔ اصلی: **سرِ عنوان قربانی نمی‌شود.** نامِ دستگاه و برند ابتدای عنوان‌اند
 * و کلِ ارزشِ سئویی همان‌جاست؛ چیزی که کوتاه می‌شود دُمِ توضیحیِ بعد از `|` است.
 * اگر بعد از برش چیزی از دُم نماند، جداکنندهٔ آویزان هم برداشته می‌شود.
 *
 * عمداً pure و بدونِ وابستگی به DB/config است تا واحد-تست‌پذیر بماند.
 */
final class MetaLength
{
    /** سقفِ عملیِ عنوان در نتایج گوگل. */
    public const TITLE_MAX = 60;

    /** سقفِ عملیِ توضیحات. */
    public const DESCRIPTION_MAX = 155;

    public static function title(?string $value, int $max = self::TITLE_MAX): string
    {
        $value = self::normalize($value);

        if ($value === '' || mb_strlen($value) <= $max) {
            return $value;
        }

        // سرِ عنوان = تا اولین `|`. اگر خودش از سقف بلندتر است، دُمی برای
        // قربانی‌کردن وجود ندارد و فقط همان روی مرزِ کلمه کوتاه می‌شود.
        $head = trim((string) explode('|', $value)[0]);
        if (mb_strlen($head) >= $max) {
            return self::clip($head, $max);
        }

        return self::clip($value, $max);
    }

    public static function description(?string $value, int $max = self::DESCRIPTION_MAX): string
    {
        return self::clip(self::normalize($value), $max);
    }

    /**
     * برش روی مرزِ کلمه — هرگز وسطِ کلمه.
     *
     * اگر در محدودهٔ مجاز هیچ فاصله‌ای نباشد (یک کلمهٔ بلندتر از سقف)، برشِ
     * سخت انجام می‌شود؛ بریدنِ ناقص از رد کردنِ سقف بهتر است.
     */
    private static function clip(string $value, int $max): string
    {
        if ($value === '' || mb_strlen($value) <= $max) {
            return self::tidy($value);
        }

        $cut = mb_substr($value, 0, $max);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return self::tidy($cut);
    }

    /** جداکنندهٔ آویزان بعد از برش («تعمیر یخچال ال‌جی |») بی‌معناست. */
    private static function tidy(string $value): string
    {
        return trim((string) preg_replace('/[\s|،؛,\-–—]+$/u', '', $value));
    }

    private static function normalize(?string $value): string
    {
        return trim((string) preg_replace('/[ \t]+/u', ' ', (string) $value));
    }
}
