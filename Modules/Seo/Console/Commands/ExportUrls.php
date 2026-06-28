<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Models\SeoSetting;
use Modules\Site\Models\Article;
use Modules\Site\Models\Forum\Question;

/**
 * خروجیِ کاملِ همه‌ی URLهای زنده‌ی سایتِ جدید به‌صورت CSV (برای SEO/مارکتینگ).
 *
 * ستون‌ها: url, type, slug, device_slug, brand_slug, title, updated_at
 * انواع: static | device | combo | brand | article | forum
 *
 * الگوهای URL همان چیزی است که روی سایتِ زنده هست (نه config سئو):
 *   device  : /services/{device_slug}
 *   combo   : /services/{device_slug}/{brand_slug}   (فقط comboهای فعال)
 *   brand   : /brands/{brand_slug}
 *   article : /blog/{slug}                            (فقط منتشرشده‌ها)
 *   forum   : /forum/{slug}                           (فقط approved + published)
 *
 *   php artisan seo:export-urls
 *   php artisan seo:export-urls --out=/path/to/urls.csv
 */
class ExportUrls extends Command
{
    protected $signature = 'seo:export-urls {--out= : مسیرِ خروجیِ CSV (پیش‌فرض: storage/app/seo-urls.csv)}';

    protected $description = 'خروجیِ CSV از همه‌ی URLهای زنده‌ی سایتِ جدید (static/device/combo/brand/article/forum)';

    /** فهرستِ مسیرهای ثابتِ فرانت. */
    private const STATIC_PATHS = [
        '/', '/services', '/brands', '/blog', '/contact', '/faq',
        '/guarantee', '/pricing', '/portfolio', '/application', '/bio', '/forum', '/login',
    ];

    public function handle(): int
    {
        $base = rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/');
        $this->info("دامنه‌ی canonical: {$base}");

        $out = (string) ($this->option('out') ?: storage_path('app/seo-urls.csv'));
        $fh = @fopen($out, 'w');
        if ($fh === false) {
            $this->error("نمی‌توان فایل را نوشت: {$out}");

            return self::FAILURE;
        }

        // BOM برای نمایشِ درستِ فارسی در Excel
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['url', 'type', 'slug', 'device_slug', 'brand_slug', 'title', 'updated_at']);

        $counts = ['static' => 0, 'device' => 0, 'combo' => 0, 'brand' => 0, 'article' => 0, 'forum' => 0];
        $write = function (array $row) use ($fh, &$counts) {
            fputcsv($fh, [
                $row['url'], $row['type'], $row['slug'] ?? '',
                $row['device_slug'] ?? '', $row['brand_slug'] ?? '',
                $row['title'] ?? '', $row['updated_at'] ?? '',
            ]);
            $counts[$row['type']]++;
        };

        // ── static ──
        foreach (self::STATIC_PATHS as $p) {
            $write(['url' => $base.$p, 'type' => 'static', 'slug' => ltrim($p, '/')]);
        }

        // ── devices (فعال) ──
        Device::query()->where('is_active', true)
            ->orderBy('slug')->cursor()->each(function (Device $d) use ($base, $write) {
                if ($this->noindex($d)) {
                    return;
                }
                $write([
                    'url' => $base.'/services/'.$d->slug,
                    'type' => 'device', 'slug' => $d->slug, 'device_slug' => $d->slug,
                    'title' => $d->name, 'updated_at' => $this->ts($d),
                ]);
            });

        // ── brands (فعال) ──
        Brand::query()->where('is_active', true)
            ->orderBy('slug')->cursor()->each(function (Brand $b) use ($base, $write) {
                if ($this->noindex($b)) {
                    return;
                }
                $write([
                    'url' => $base.'/brands/'.$b->slug,
                    'type' => 'brand', 'slug' => $b->slug, 'brand_slug' => $b->slug,
                    'title' => $b->name, 'updated_at' => $this->ts($b),
                ]);
            });

        // ── combos (DeviceBrandPageِ فعال) ──
        DeviceBrandPage::query()->where('is_active', true)
            ->with(['device:id,slug,name,is_active', 'brand:id,slug,name,is_active'])
            ->orderBy('id')->cursor()->each(function (DeviceBrandPage $p) use ($base, $write) {
                $d = $p->device;
                $b = $p->brand;
                if (! $d || ! $b || ! $d->is_active || ! $b->is_active) {
                    return; // جفت فقط وقتی زنده است که دستگاه و برند هر دو فعال باشند
                }
                $write([
                    'url' => $base.'/services/'.$d->slug.'/'.$b->slug,
                    'type' => 'combo', 'slug' => $d->slug.'/'.$b->slug,
                    'device_slug' => $d->slug, 'brand_slug' => $b->slug,
                    'title' => trim($d->name.' '.$b->name), 'updated_at' => $this->ts($p),
                ]);
            });

        // ── articles (منتشرشده) ──
        Article::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderBy('slug')->cursor()->each(function (Article $a) use ($base, $write) {
                if ($this->noindex($a)) {
                    return;
                }
                $write([
                    'url' => $base.'/blog/'.$a->slug,
                    'type' => 'article', 'slug' => $a->slug,
                    'title' => $a->title, 'updated_at' => $this->ts($a),
                ]);
            });

        // ── forum questions (approved + published) ──
        Question::query()->approved()
            ->orderBy('slug')->cursor()->each(function (Question $q) use ($base, $write) {
                if ($this->noindex($q)) {
                    return;
                }
                $write([
                    'url' => $base.'/forum/'.$q->slug,
                    'type' => 'forum', 'slug' => $q->slug,
                    'title' => $q->title, 'updated_at' => $this->ts($q),
                ]);
            });

        fclose($fh);

        $total = array_sum($counts);
        $this->newLine();
        $this->info("نوشته شد: {$out}");
        $this->table(['type', 'count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info("مجموع: {$total} URL");

        return self::SUCCESS;
    }

    /** آیا این مدل در متای سئو noindex خورده؟ (برای هم‌خوانی با sitemap) */
    private function noindex(object $model): bool
    {
        if (! method_exists($model, 'seoMeta')) {
            return false;
        }
        $meta = $model->seoMeta;

        return (bool) ($meta->robots_noindex ?? false);
    }

    private function ts(object $model): string
    {
        $val = $model->getAttribute('updated_at') ?? $model->getAttribute('created_at');

        return $val ? Carbon::parse($val)->toDateTimeString() : '';
    }
}
