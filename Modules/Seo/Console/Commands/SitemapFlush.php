<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Seo\Services\SitemapBuilder;

/**
 * پاک‌کردنِ کشِ سمتِ سرورِ سایت‌مپ — تا درخواستِ بعدی، فایل‌ها از دیتابیس
 * بازساخته شوند. سازوکارِ به‌روزرسانیِ فوری «پس از انتشار/حذف/ویرایشِ محتوا»
 * (وگرنه کش هر ۶۰ دقیقه خودش تازه می‌شود).
 *
 *   php artisan seo:sitemap-flush          # فقط کشِ داغ (last-good حفظ می‌شود)
 *   php artisan seo:sitemap-flush --hard   # last-good را هم پاک می‌کند
 */
class SitemapFlush extends Command
{
    protected $signature = 'seo:sitemap-flush {--hard : نسخهٔ «آخرین سالم» را هم پاک کن}';

    protected $description = 'پاک‌کردنِ کشِ سایت‌مپ تا در درخواستِ بعدی از دیتابیس بازساخته شود';

    public function handle(): int
    {
        Cache::forget('seo:sitemap:spec-index:xml');

        $names = array_keys(SitemapBuilder::SPEC_FILES);
        foreach ($names as $name) {
            Cache::forget('seo:sitemap:spec:'.$name.':xml');
            if ($this->option('hard')) {
                Cache::forget('seo:sitemap:spec:'.$name.':last-good');
            }
        }

        $this->info('کشِ سایت‌مپ پاک شد ('.(count($names) + 1).' کلید'
            .($this->option('hard') ? ' + last-good' : '').'). درخواستِ بعدی از دیتابیس بازساخته می‌شود.');

        return self::SUCCESS;
    }
}
