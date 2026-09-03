<?php

namespace Modules\Site\Support;

use Modules\Site\Services\PageSectionService;

/**
 * سکشن‌های سطحِ شهر برای صفحاتِ هابِ شهری (city / services / brands) — این
 * صفحات به دستگاه/برندِ خاصی گره نمی‌خورند، پس یک «مجموعهٔ پیش‌فرضِ عمومی»
 * برمی‌گردانیم (SEO-024 §۲.۳.۶)، ولی عنوان‌ها با نامِ شهر و استان بومی‌سازی
 * می‌شوند تا صفحه از نظرِ سئو به همان شهر/استان مرتبط بماند.
 *
 * منبع:
 *   - videos           : ویدیوهای پیش‌فرضِ قالبِ «device» (placeholderهای عمومی).
 *   - forum_questions  : جدیدترین سوالاتِ منتشرشدهٔ انجمن.
 *   - related_articles : جدیدترین مقالاتِ منتشرشده (طبقِ خواستِ کارفرما «عمومی» می‌ماند).
 *
 * همه‌چیز best-effort است؛ هر خطا → آرایهٔ خالی تا صفحه نشکند.
 */
final class CityHubSections
{
    /**
     * @param  string|null  $city  نامِ شهرِ صفحه (برای بومی‌سازیِ عنوان).
     * @param  string|null  $province  نامِ استانِ صفحه (برای بومی‌سازیِ عنوان).
     * @return array<string, array<string, mixed>>
     */
    public static function all(?string $city = null, ?string $province = null): array
    {
        // پسوندِ «در مشهد، خراسان رضوی» — فقط وقتی شهر موجود است تا متنِ آویزان نماند.
        $c = trim((string) $city);
        $p = trim((string) $province);
        $suffix = $c === '' ? '' : ($p === '' ? " در {$c}" : " در {$c}، {$p}");

        return [
            'videos' => self::videos($suffix),
            'forum_questions' => self::forumQuestions($suffix),
            'related_articles' => self::relatedArticles(),
        ];
    }

    /** @return array<string, mixed> */
    private static function videos(string $suffix): array
    {
        $tpl = self::deviceTemplate();
        $items = [];
        try {
            $raw = (array) ($tpl['videos']['items'] ?? []);
            $items = array_values(array_map(fn (array $r) => [
                'title' => $r['title'] ?? null,
                'aparat_id' => $r['aparat_id'] ?? null,
                'youtube_id' => $r['youtube_id'] ?? null,
                'video_url' => $r['video_url'] ?? null,
                'description' => $r['description'] ?? null,
                'poster_url' => $r['poster_url'] ?? null,
                'upload_date' => VideoDate::iso($r),
            ], array_filter($raw, 'is_array')));
        } catch (\Throwable $e) {
            $items = [];
        }

        return [
            'title' => 'ویدیوهای آموزشیِ تعمیرات'.$suffix,
            'subtitle' => null,
            'items' => $items,
        ];
    }

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $forumItemsCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $articleItemsCache = null;

    /** @return array<string, mixed> */
    private static function forumQuestions(string $suffix): array
    {
        // آیتم‌ها عمومی‌اند و بین همهٔ شهرها یکسان؛ یک‌بار کوئری می‌شوند تا در
        // endpointِ فهرست (چند شهر) کوئریِ تکراری نزنیم. فقط عنوان per-city است.
        if (self::$forumItemsCache === null) {
            try {
                self::$forumItemsCache = ForumQuestionFeed::latest(5);
            } catch (\Throwable $e) {
                self::$forumItemsCache = [];
            }
        }

        return [
            'title' => 'پرسش‌های متداولِ تعمیرات'.$suffix,
            'subtitle' => null,
            'see_all_label' => null,
            'see_all_url' => '/forum',
            'items' => self::$forumItemsCache,
        ];
    }

    /** @return array<string, mixed> */
    private static function relatedArticles(): array
    {
        if (self::$articleItemsCache === null) {
            try {
                self::$articleItemsCache = RelatedArticles::latest(6);
            } catch (\Throwable $e) {
                self::$articleItemsCache = [];
            }
        }

        // مقالات طبقِ خواستِ کارفرما «آخرین مقالات» (عمومی) می‌ماند — بدونِ بومی‌سازی.
        return [
            'title' => 'آخرین مقالاتِ آموزشی',
            'subtitle' => null,
            'items' => self::$articleItemsCache,
        ];
    }

    /** @var array<string, mixed>|null کشِ درخواستی تا قالب یک‌بار لود شود. */
    private static ?array $deviceTemplateCache = null;

    /** @return array<string, mixed> */
    private static function deviceTemplate(): array
    {
        if (self::$deviceTemplateCache !== null) {
            return self::$deviceTemplateCache;
        }
        try {
            $svc = app(PageSectionService::class);
            self::$deviceTemplateCache = $svc->pageExists('device') ? $svc->loadForPublic('device', []) : [];
        } catch (\Throwable $e) {
            self::$deviceTemplateCache = [];
        }

        return self::$deviceTemplateCache;
    }
}
