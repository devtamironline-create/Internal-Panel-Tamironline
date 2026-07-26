<?php

namespace Modules\Seo\Contracts;

/**
 * قرارداد تحقیق کلمات کلیدی (حجم جستجو/سختی). فاز ۱ پیاده‌سازی ندارد؛
 * فاز بعد: WindsorSemrushProvider.
 */
interface KeywordResearchProvider
{
    /**
     * @param  list<string>  $keywords
     * @return array<string, array{search_volume: ?int, difficulty: ?int}>
     */
    public function metrics(array $keywords): array;
}
