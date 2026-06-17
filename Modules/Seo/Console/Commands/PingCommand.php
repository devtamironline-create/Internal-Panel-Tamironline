<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Modules\Seo\Services\IndexNowClient;
use Modules\Seo\Services\SiteCrawler;

/**
 * ارسال فوری URLها به موتورهای جستجو (IndexNow) و ping کردن sitemap.
 */
class PingCommand extends Command
{
    protected $signature = 'seo:ping {--urls= : لیست URL با کاما} {--all : ارسال همهٔ URLهای sitemap}';

    protected $description = 'Instant Indexing: ارسال URLها به IndexNow + ping سایت‌مپ';

    public function handle(IndexNowClient $indexNow, SiteCrawler $crawler): int
    {
        $indexNow->pingSitemaps();
        $this->info('sitemap به گوگل و بینگ ping شد.');

        if (! $indexNow->enabled()) {
            $this->warn('کلید IndexNow تنظیم نشده — ارسال URLها رد شد.');

            return self::SUCCESS;
        }

        $urls = [];
        if ($this->option('all')) {
            $urls = $crawler->collectUrls();
        } elseif ($this->option('urls')) {
            $urls = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('urls')))));
        }

        if ($urls === []) {
            $this->warn('URLی برای ارسال نبود (از --all یا --urls استفاده کنید).');

            return self::SUCCESS;
        }

        // IndexNow حداکثر ۱۰۰۰۰ URL در هر درخواست — دسته‌بندی.
        foreach (array_chunk($urls, 1000) as $chunk) {
            $ok = $indexNow->submit($chunk);
            $this->line(($ok ? '✓' : '✗').' ارسال '.count($chunk).' URL');
        }

        return self::SUCCESS;
    }
}
