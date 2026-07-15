<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;
use Modules\Site\Models\Page;

/**
 * خروجیِ CSV از «صفحاتِ ثابت و هاب‌های اصلیِ سایت» با ستون‌های موردِ نیازِ سئو
 * برای ساختِ sitemap. این صفحات (خانه، لیستِ خدمات، لیستِ برندها، بلاگ، انجمن،
 * سؤالات، درباره، گارانتی، تعرفه) صفحهٔ ایندکسِ فرانت‌اند (Next.js) هستند و
 * لزوماً ردیفِ مستقیمِ دیتابیس ندارند؛ به همین دلیل در خروجیِ مدل‌محورِ sitemap
 * دیده نمی‌شوند. این کامند آن‌ها را جداگانه فهرست می‌کند.
 *
 * ستون‌ها (دقیقاً طبقِ درخواستِ سئو):
 *   url, http_status, canonical_url, meta_robots, is_indexable, is_active, updated_at
 *
 * منابعِ داده:
 *   - اگر برای slug یک ردیفِ Page در site_pages باشد → is_active/updated_at/متا از همان.
 *   - اگر هاب فقط مسیرِ فرانت باشد (بدونِ ردیفِ DB) → is_active=true و updated_at خالی
 *     (چون هیچ ردیفی نیست که lastmod از آن بیاید — باید از تاریخِ دیپلوی/محتوای فرانت آید).
 *
 *   php artisan seo:export-static-pages
 *   php artisan seo:export-static-pages --probe        # http_status واقعی با درخواستِ زنده
 *   php artisan seo:export-static-pages --out=/path/static-pages.csv
 */
class ExportStaticPages extends Command
{
    protected $signature = 'seo:export-static-pages
        {--out= : مسیرِ خروجیِ CSV (پیش‌فرض: storage/app/seo-static-pages.csv)}
        {--probe : بررسیِ زندهٔ http_status هر URL با درخواستِ HTTP}';

    protected $description = 'خروجیِ CSV از صفحاتِ ثابت/هاب‌های اصلیِ سایت با ستون‌های سئو (url,http_status,canonical_url,meta_robots,is_indexable,is_active,updated_at)';

    /**
     * فهرستِ مسیرهای ثابت و هابِ اصلی + slugِ متناظرِ احتمالی در site_pages.
     * path => slug (برای جست‌وجوی ردیفِ Page). «home» قراردادِ خانه است.
     *
     * @var array<string, string>
     */
    private const STATIC_PAGES = [
        '/' => 'home',
        '/services' => 'services',
        '/brands' => 'brands',
        '/blog' => 'blog',
        '/faq' => 'faq',
        '/forum' => 'forum',
        '/about' => 'about',
        '/guarantee' => 'guarantee',
        '/pricing' => 'pricing',
    ];

    public function handle(): int
    {
        $base = rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/');
        $probe = (bool) $this->option('probe');
        $this->info("دامنه‌ی canonical: {$base}");
        if ($probe) {
            $this->warn('حالتِ probe فعال است — http_status هر URL به‌صورتِ زنده بررسی می‌شود (ممکن است کند باشد).');
        }

        $out = (string) ($this->option('out') ?: storage_path('app/seo-static-pages.csv'));
        $fh = @fopen($out, 'w');
        if ($fh === false) {
            $this->error("نمی‌توان فایل را نوشت: {$out}");

            return self::FAILURE;
        }

        // BOM برای نمایشِ درستِ فارسی در Excel
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['url', 'http_status', 'canonical_url', 'meta_robots', 'is_indexable', 'is_active', 'updated_at']);

        // ردیف‌های Page مرتبط را یک‌جا می‌خوانیم (به‌همراهِ متا).
        $pages = Page::query()
            ->whereIn('slug', array_values(self::STATIC_PAGES))
            ->with('seoMeta')
            ->get()
            ->keyBy('slug');

        $rows = [];
        foreach (self::STATIC_PAGES as $path => $slug) {
            $url = $base.$path;
            $page = $pages->get($slug);

            $meta = $page?->seoMeta;
            $isActive = $page ? (bool) $page->is_published : true; // هابِ فرانت همیشه فعال
            $canonical = $meta && filled($meta->canonical) ? $meta->canonical : $url;
            $robots = $this->robotsString($meta);
            $noindex = $meta ? (bool) $meta->robots_noindex : false;
            $isIndexable = $isActive && ! $noindex;
            $updatedAt = $page && $page->updated_at ? Carbon::parse($page->updated_at)->toDateTimeString() : '';

            $status = '';
            if ($probe) {
                $status = (string) $this->probeStatus($url);
            }

            $row = [
                'url' => $url,
                'http_status' => $status,
                'canonical_url' => $canonical,
                'meta_robots' => $robots,
                'is_indexable' => $isIndexable ? 'true' : 'false',
                'is_active' => $isActive ? 'true' : 'false',
                'updated_at' => $updatedAt,
            ];
            fputcsv($fh, array_values($row));
            $rows[] = array_merge($row, ['source' => $page ? 'db_page' : 'frontend_route']);
        }

        fclose($fh);

        $this->newLine();
        $this->info("نوشته شد: {$out}");
        $this->table(
            ['url', 'http_status', 'is_indexable', 'is_active', 'updated_at', 'source'],
            array_map(fn ($r) => [$r['url'], $r['http_status'] ?: '—', $r['is_indexable'], $r['is_active'], $r['updated_at'] ?: '—', $r['source']], $rows)
        );
        $this->info('مجموع: '.count($rows).' صفحه');
        if (! $probe) {
            $this->comment('نکته: http_status خالی است. برای پرکردنِ آن با بررسیِ زنده، دوباره با --probe اجرا کنید.');
        }

        return self::SUCCESS;
    }

    /** رشتهٔ meta robots از فلگ‌های SeoMeta؛ پیش‌فرضِ صفحهٔ عادی: index,follow. */
    private function robotsString(?object $meta): string
    {
        if (! $meta) {
            return 'index,follow';
        }
        $parts = [];
        $parts[] = $meta->robots_noindex ? 'noindex' : 'index';
        $parts[] = $meta->robots_nofollow ? 'nofollow' : 'follow';
        foreach ([
            'robots_noarchive' => 'noarchive',
            'robots_nosnippet' => 'nosnippet',
            'robots_noimageindex' => 'noimageindex',
        ] as $col => $token) {
            if (! empty($meta->{$col})) {
                $parts[] = $token;
            }
        }

        return implode(',', $parts);
    }

    /** بررسیِ زندهٔ کدِ وضعیتِ HTTP یک URL (بدونِ throw). */
    private function probeStatus(string $url): int|string
    {
        try {
            $resp = Http::timeout(10)->withOptions(['allow_redirects' => false])->get($url);

            return $resp->status();
        } catch (\Throwable $e) {
            return 'ERR';
        }
    }
}
