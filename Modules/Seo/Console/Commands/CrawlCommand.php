<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Modules\Seo\Services\SiteCrawler;

/**
 * کرال و آدیت سئوی کل سایت از روی sitemap. به‌صورت زمان‌بندی‌شده (روزانه)
 * یا دستی اجرا می‌شود.
 */
class CrawlCommand extends Command
{
    protected $signature = 'seo:crawl {--limit= : حداکثر تعداد URL} {--cwv : اندازه‌گیری Core Web Vitals} {--source=scheduled}';

    protected $description = 'کرال و آدیت سئوی کل سایت (On-page audit + ذخیرهٔ تاریخچه)';

    public function handle(SiteCrawler $crawler): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $run = $crawler->run(
            (string) $this->option('source'),
            $limit,
            (bool) $this->option('cwv'),
            function (string $url, int $i, int $total) {
                $this->line("[{$i}/{$total}] {$url}");
            }
        );

        $this->info(sprintf(
            'کرال #%d تمام شد — بحرانی: %d، هشدار: %d، توجه: %d، میانگین امتیاز: %d',
            $run->id,
            $run->critical_count,
            $run->warning_count,
            $run->notice_count,
            $run->avg_score
        ));

        return self::SUCCESS;
    }
}
