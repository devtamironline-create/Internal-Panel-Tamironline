<?php

namespace Modules\Site\Support;

/**
 * قالبِ Titleِ سئوی مقالات: «{عنوانِ کوتاهِ مقاله} | تعمیرآنلاین».
 *
 * مقالاتِ واردشده از وردپرس، Titleهای طولانی دارند (پسوندهای تکراری مثل
 * «- تعمیرآنلاین | اپلیکیشن تخصصی خدمات» و «+ راه حل تعمیر آن») که از حدِ
 * استانداردِ سئو (~۶۰ کاراکتر) رد می‌شوند. این هلپر پسوندهای اضافه را پاک
 * می‌کند، طولِ نهایی را محدود می‌کند و برندِ «تعمیرآنلاین» را یک‌بار در انتها
 * می‌گذارد. idempotent است: اگر ورودی از قبل تمیز باشد، خروجی تغییری نمی‌کند.
 *
 * فقط برای Titleِ سئو (تگ <title>) است؛ عنوانِ داخلِ صفحه (H1 = article.title)
 * دست‌نخورده می‌ماند.
 */
final class ArticleSeoTitle
{
    public const BRAND = 'تعمیرآنلاین';

    /** حداکثر طولِ کلِ Title (کاراکتر). */
    public const MAX = 65;

    /** عبارت‌های اضافه‌ای که باید از Title حذف شوند. */
    private const JUNK = [
        'اپلیکیشن تخصصی خدمات',
        'خدمات تخصصی',
        'راه حل تعمیر آن',
        'راه‌حل تعمیر آن',
        'راهکار تعمیر آن',
        'راه حل تعمیرآن',
    ];

    public static function build(?string $rawTitle, int $max = self::MAX): string
    {
        $suffix = ' | '.self::BRAND;
        $base = self::cleanBase((string) $rawTitle);

        if ($base === '') {
            // اگر بعد از پاک‌سازی چیزی نماند، از خودِ ورودی (بریده) استفاده کن.
            $base = trim(mb_substr(trim((string) $rawTitle), 0, $max - mb_strlen($suffix)));
        }

        $budget = $max - mb_strlen($suffix);
        if (mb_strlen($base) > $budget) {
            $base = self::truncateWords($base, $budget);
        }

        return $base.$suffix;
    }

    /** پاک‌سازیِ عنوان: حذفِ برندِ انتهایی، عبارت‌های اضافه و جداکننده‌های لبه‌ای. */
    private static function cleanBase(string $title): string
    {
        // برندِ «تعمیرآنلاین» یا «تعمیر آنلاین» (با/بدون نیم‌فاصله).
        $brand = 'تعمیر\x{200c}?\s*آنلاین';

        // ۱) از اولین جداکننده‌ای که برند بعدش آمده تا انتها را قطع کن.
        $title = preg_replace('/\s*[|\-–—•]\s*(?:'.$brand.').*$/u', '', $title) ?? $title;
        // ۲) برندِ انتهاییِ بدونِ جداکننده را هم بردار.
        $title = preg_replace('/\s*(?:'.$brand.')\s*$/u', '', $title) ?? $title;

        // ۳) عبارت‌های اضافهٔ مشخص را هرجا که باشند حذف کن.
        $title = str_replace(self::JUNK, '', $title);

        // ۴) بازمانده‌ی «+ تعمیر آن» در انتها.
        $title = preg_replace('/\s*\+\s*تعمیر\s*آن\s*$/u', '', $title) ?? $title;

        // ۵) جداکننده/اتصال‌های اضافیِ ابتدا و انتها + فشرده‌سازیِ فاصله‌ها.
        $title = preg_replace('/\s*[+\-–—|•]\s*$/u', '', $title) ?? $title;
        $title = preg_replace('/^\s*[+\-–—|•]\s*/u', '', $title) ?? $title;
        $title = preg_replace('/\s{2,}/u', ' ', $title) ?? $title;

        return trim($title);
    }

    /** بریدن روی مرزِ کلمه تا در بودجهٔ کاراکتری جا شود (بدونِ «...»). */
    private static function truncateWords(string $s, int $budget): string
    {
        $cut = mb_substr($s, 0, $budget);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($budget * 0.5)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut);
    }
}
