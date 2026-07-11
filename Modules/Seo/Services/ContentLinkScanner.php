<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoSetting;

/**
 * اسکنِ لینک از «محتوای authored در پنل» (نه HTMLِ رندرشدهٔ فرانت).
 *
 * چرا لازم است: کرالِ صفحه فقط لینک‌هایی را می‌بیند که در HTMLِ سمتِ سرورِ
 * صفحاتِ داخلِ sitemap باشند. لینک‌های داخلِ متنِ مقاله (که در پنل نوشته‌اید و
 * در DB ذخیره شده) ممکن است در آن HTML نباشند یا صفحه‌شان کرال نشود. این اسکنر
 * مستقیماً HTMLِ ذخیره‌شده را می‌خواند، پس هر لینکِ درون‌محتوایی — صرفِ‌نظر از
 * نحوهٔ رندرِ فرانت — پیدا می‌شود. خروجی در همان seo_links ذخیره و سپس مثلِ بقیه
 * توسط LinkGraphAnalyzer چک می‌شود.
 */
class ContentLinkScanner
{
    public function __construct(
        private readonly LinkExtractor $extractor,
        private readonly LinkGraphStore $store,
    ) {}

    /**
     * لینک‌های محتوای authored را برای یک run استخراج و ذخیره می‌کند.
     *
     * @return int تعدادِ کلِ لینک‌های استخراج‌شده
     */
    public function scan(int $runId): int
    {
        $siteHost = LinkExtractor::siteHost();
        $base = rtrim(SeoSetting::siteUrl(), '/');
        $total = 0;

        // ── مقالاتِ بلاگ (منبعِ اصلیِ لینک‌های درون‌محتوایی) ──
        \Modules\Site\Models\Article::query()
            ->where('is_published', true)
            ->select('id', 'slug', 'content')
            ->chunkById(200, function ($rows) use ($runId, $base, $siteHost, &$total) {
                foreach ($rows as $a) {
                    $html = (string) $a->content;
                    if (trim($html) === '') {
                        continue;
                    }
                    $sourceUrl = $base.'/blog/'.$a->slug;
                    $links = $this->extractor->extract($html, $sourceUrl, $siteHost);
                    $this->store->store($runId, $sourceUrl, $links);
                    $total += count($links);
                }
            });

        return $total;
    }
}
