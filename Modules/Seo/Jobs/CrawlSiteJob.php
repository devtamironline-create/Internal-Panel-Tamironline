<?php

namespace Modules\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Seo\Services\SiteCrawler;

/**
 * اجرای کرال کامل سایت در پس‌زمینه (برای دکمهٔ «کرال جدید» در پنل).
 */
class CrawlSiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        private readonly string $source = 'manual',
        private readonly ?int $limit = null,
        private readonly bool $cwv = false,
    ) {}

    public function handle(SiteCrawler $crawler): void
    {
        $crawler->run($this->source, $this->limit, $this->cwv);
    }
}
