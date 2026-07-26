<?php

namespace Modules\Seo\Contracts;

use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoSnapshot;

/**
 * قرارداد تأمین دادهٔ عملکرد سئو (کلیک/ایمپرشن/رتبه/لید).
 * فاز ۱: DatabaseSeoPerformanceProvider (snapshot های seed شده).
 * فازهای بعد: WindsorSearchConsoleProvider / WindsorGa4Provider.
 * کنترلر و UI نباید بدانند داده از کدام منبع می‌آید.
 */
interface SeoPerformanceProvider
{
    /** آخرین snapshot دورهٔ انتخابی (period_end <= تا تاریخ داده‌شده). */
    public function latestSnapshot(SeoProject $project, ?string $until = null): ?SeoSnapshot;

    /** snapshot دورهٔ قبل برای مقایسه — null یعنی «اولین بازه، مبنا». */
    public function previousSnapshot(SeoProject $project, SeoSnapshot $current): ?SeoSnapshot;

    /**
     * سری زمانی snapshot ها در یک بازه (برای نمودار/گزارش).
     *
     * @return \Illuminate\Support\Collection<int, SeoSnapshot>
     */
    public function series(SeoProject $project, string $from, string $to): \Illuminate\Support\Collection;
}
