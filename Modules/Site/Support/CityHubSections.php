<?php

namespace Modules\Site\Support;

use Modules\Site\Services\PageSectionService;

/**
 * سکشن‌های سطحِ شهر برای صفحاتِ هابِ شهری (city / services / brands) — این
 * صفحات به دستگاه/برندِ خاصی گره نمی‌خورند، پس یک «مجموعهٔ پیش‌فرضِ عمومی»
 * برمی‌گردانیم (SEO-024 §۲.۳.۶): ویدیوها، پرسش‌های انجمن، مقالاتِ مرتبط —
 * دقیقاً در همان شکلِ سکشن‌های صفحاتِ دستگاه تا فرانت با همان کامپوننت‌ها
 * رِندرشان کند.
 *
 * منبع:
 *   - videos           : ویدیوهای پیش‌فرضِ قالبِ «device» (placeholderهای عمومی).
 *   - forum_questions  : جدیدترین سوالاتِ منتشرشدهٔ انجمن.
 *   - related_articles : جدیدترین مقالاتِ منتشرشده.
 *
 * همه‌چیز best-effort است؛ هر خطا → آرایهٔ خالی تا صفحه نشکند.
 */
final class CityHubSections
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'videos' => self::videos(),
            'forum_questions' => self::forumQuestions(),
            'related_articles' => self::relatedArticles(),
        ];
    }

    /** @return array<string, mixed> */
    private static function videos(): array
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
            'title' => $tpl['videos']['title'] ?? 'ویدیوهای آموزشی',
            'subtitle' => $tpl['videos']['subtitle'] ?? null,
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private static function forumQuestions(): array
    {
        $tpl = self::deviceTemplate();
        $items = [];
        try {
            $items = ForumQuestionFeed::latest(5);
        } catch (\Throwable $e) {
            $items = [];
        }

        return [
            'title' => $tpl['forum_questions']['title'] ?? 'پرسش‌های پرتکرار',
            'subtitle' => $tpl['forum_questions']['subtitle'] ?? null,
            'see_all_label' => $tpl['forum_questions']['see_all_label'] ?? null,
            'see_all_url' => '/forum',
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private static function relatedArticles(): array
    {
        $tpl = self::deviceTemplate();
        $items = [];
        try {
            $items = RelatedArticles::latest(6);
        } catch (\Throwable $e) {
            $items = [];
        }

        return [
            'title' => $tpl['related_articles']['title'] ?? 'مقالات مرتبط',
            'subtitle' => $tpl['related_articles']['subtitle'] ?? null,
            'items' => $items,
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
