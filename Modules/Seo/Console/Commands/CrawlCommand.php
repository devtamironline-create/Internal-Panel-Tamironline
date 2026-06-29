<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;
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

        // شفافیتِ هدف: مانیتورینگ کدام سایت را کرال می‌کند + یک تستِ دسترسی.
        $target = SeoSetting::siteUrl();
        $this->line('هدفِ مانیتورینگ: '.$target);
        $panelHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($panelHost !== '' && strtolower((string) parse_url($target, PHP_URL_HOST)) === $panelHost) {
            $this->warn('⚠ هدف، hostِ پنل است! «تنظیمات سئو → canonical_base_url» را به سایتِ اصلیِ فرانت تغییر دهید.');
        }
        try {
            $probe = Http::timeout(15)->withHeaders(['User-Agent' => 'TamironlineSeoBot/1.0'])->get($target);
            $this->line('تستِ صفحهٔ اصلی: HTTP '.$probe->status().' ('.strlen($probe->body()).' بایت)');
            if ($probe->status() >= 400) {
                $this->warn('⚠ صفحهٔ اصلی '.$probe->status().' داد — اگر ۴۰۳/۵۰۳ است، WAF/کلودفلر ربات را بلاک کرده؛ User-Agent «TamironlineSeoBot» را در allowlist بگذارید.');
            }
        } catch (\Throwable $e) {
            $this->warn('⚠ صفحهٔ اصلی قابل‌دسترسی نیست از سرور: '.$e->getMessage());
        }

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
