<?php

namespace Modules\Seo\Support;

use Modules\Seo\Models\SeoSetting;

/**
 * سیاستِ داده‌محورِ ایندکس (نقشهٔ ایندکس Search Console):
 *
 *  - برندهای نحیف: فقط برندهای داخلِ whitelist (تنظیمِ brand_index_whitelist)
 *    index می‌شوند؛ بقیه noindex,follow می‌گیرند و از sitemap-brands حذف می‌شوند.
 *    whitelistِ خالی = سیاست خاموش (همهٔ برندها مثل قبل index).
 *  - سوالاتِ کم‌بازدهِ انجمن: زیرِ آستانهٔ forum_index_min_views (پیش‌فرض ۴۰ بازدید)
 *    noindex,follow و خارج از sitemap-forum. مقدارِ 0 = سیاست خاموش.
 *
 * override صریحِ ادمین روی seo_meta (robots_noindex) همیشه بر این سیاست مقدم است.
 */
final class IndexingPolicy
{
    public const BRAND_WHITELIST_KEY = 'brand_index_whitelist';

    public const FORUM_MIN_VIEWS_KEY = 'forum_index_min_views';

    public const FORUM_MIN_VIEWS_DEFAULT = 40;

    /** memo در طول یک request — سایت‌مپ برای هر برند صدا می‌زند. */
    private static ?array $brandWhitelist = null;

    private static bool $brandLoaded = false;

    private static ?int $forumMinViews = null;

    /**
     * whitelist برندهای ایندکس‌شونده (slugهای نرمال‌شده) یا null اگر سیاست خاموش است.
     *
     * @return list<string>|null
     */
    public static function brandWhitelist(): ?array
    {
        if (self::$brandLoaded) {
            return self::$brandWhitelist;
        }
        self::$brandLoaded = true;

        $raw = (string) (SeoSetting::get(self::BRAND_WHITELIST_KEY) ?? '');
        $slugs = preg_split('/[\s,،]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $slugs = array_values(array_unique(array_map(
            fn ($s) => mb_strtolower(trim($s, "/ \t")),
            $slugs
        )));

        return self::$brandWhitelist = ($slugs === [] ? null : $slugs);
    }

    /** آیا صفحهٔ این برند باید index شود؟ (whitelist خالی → بله). */
    public static function brandIndexable(?string $slug): bool
    {
        $whitelist = self::brandWhitelist();
        if ($whitelist === null) {
            return true;
        }

        return in_array(mb_strtolower(trim((string) $slug)), $whitelist, true);
    }

    /** آستانهٔ حداقل بازدید برای ایندکسِ سوالِ انجمن (0 = خاموش). */
    public static function forumMinViews(): int
    {
        if (self::$forumMinViews !== null) {
            return self::$forumMinViews;
        }

        $raw = SeoSetting::get(self::FORUM_MIN_VIEWS_KEY);
        $val = ($raw === null || trim($raw) === '') ? self::FORUM_MIN_VIEWS_DEFAULT : (int) $raw;

        return self::$forumMinViews = max(0, $val);
    }

    /** آیا سوالِ انجمن با این تعداد بازدید ایندکس‌پذیر است؟ */
    public static function forumIndexable(int $viewCount): bool
    {
        $min = self::forumMinViews();

        return $min === 0 || $viewCount >= $min;
    }

    /** ریست memo — فقط برای تست. */
    public static function flush(): void
    {
        self::$brandWhitelist = null;
        self::$brandLoaded = false;
        self::$forumMinViews = null;
    }
}
