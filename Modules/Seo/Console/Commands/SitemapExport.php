<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Modules\Seo\Services\SitemapBuilder;

/**
 * خروجیِ CSV از همهٔ URLهای نهاییِ سایت‌مپِ منطبق‌بر‌اسپک + شمارشِ هر فایل.
 * برای کنترلِ نهایی (بندِ ۷ و ۱۰): تعدادِ هر فایل و تطبیق با لیستِ تأییدشده.
 *
 * ستون‌های CSV: sitemap_file, in_index, url
 *
 *   php artisan seo:sitemap-export
 *   php artisan seo:sitemap-export --out=/path/sitemap-urls.csv
 */
class SitemapExport extends Command
{
    protected $signature = 'seo:sitemap-export {--out= : مسیرِ CSV (پیش‌فرض: storage/app/sitemap-urls.csv)}';

    protected $description = 'خروجیِ CSV از URLهای نهاییِ سایت‌مپ + شمارشِ هر فایل (کنترلِ نهایی)';

    public function handle(SitemapBuilder $builder): int
    {
        $out = (string) ($this->option('out') ?: storage_path('app/sitemap-urls.csv'));
        $fh = @fopen($out, 'w');
        if ($fh === false) {
            $this->error("نمی‌توان فایل را نوشت: {$out}");

            return self::FAILURE;
        }

        fwrite($fh, "\xEF\xBB\xBF"); // BOM برای Excel
        fputcsv($fh, ['sitemap_file', 'in_index', 'url']);

        $counts = [];
        $seenGlobal = [];
        $dupWarnings = 0;

        foreach (array_keys(SitemapBuilder::SPEC_FILES) as $name) {
            $inIndex = in_array($name, SitemapBuilder::INDEX_FILES, true) ? 'yes' : 'no';
            $urls = $builder->specUrls($name);
            $counts[$name] = count($urls);

            foreach ($urls as $u) {
                $loc = $u['loc'];
                // کنترلِ URLِ تکراری بینِ فایل‌ها (بندِ ۸ اسپک)
                if (isset($seenGlobal[$loc])) {
                    $dupWarnings++;
                }
                $seenGlobal[$loc] = true;

                fputcsv($fh, ['sitemap-'.$name.'.xml', $inIndex, $loc]);
            }
        }

        fclose($fh);

        $this->info("نوشته شد: {$out}");
        $rows = [];
        foreach ($counts as $name => $c) {
            $rows[] = [
                'sitemap-'.$name.'.xml',
                in_array($name, SitemapBuilder::INDEX_FILES, true) ? 'در index' : '—',
                $c,
            ];
        }
        $this->table(['file', 'index', 'url_count'], $rows);
        $this->info('مجموعِ URLها: '.count($seenGlobal).' (یکتا)');
        if ($dupWarnings > 0) {
            $this->warn("هشدار: {$dupWarnings} URLِ تکراری بینِ فایل‌ها دیده شد.");
        } else {
            $this->info('هیچ URLِ تکراری بینِ فایل‌ها نیست ✓');
        }

        return self::SUCCESS;
    }
}
