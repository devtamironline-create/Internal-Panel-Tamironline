<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Carbon;
use Modules\Seo\Models\SeoSetting;

/**
 * ساختِ دادهٔ sitemap از روی رجیستری نوع‌محتوا. آیتم‌های noindex یا
 * منتشرنشده حذف می‌شوند تا sitemap با ایندکس‌پذیریِ واقعی هم‌خوان بماند.
 */
class SitemapBuilder
{
    /** سقفِ استانداردِ موتورهای جستجو برای هر فایلِ sitemap. */
    public const MAX_URLS_PER_FILE = 50000;

    public function __construct(private readonly SeoableRegistry $registry) {}

    /**
     * آیتم‌های یک نوع برای یک «صفحه» (chunk) — برای تقسیمِ sitemapهای بزرگ.
     *
     * @return list<array{loc:string,lastmod:?string,changefreq:string,priority:string}>
     */
    public function urlsForTypePaged(string $type, int $page): array
    {
        $all = $this->urlsForType($type);

        return array_slice($all, (max(1, $page) - 1) * self::MAX_URLS_PER_FILE, self::MAX_URLS_PER_FILE);
    }

    /** تعدادِ فایل‌های sitemap لازم برای یک نوع (≥۱). */
    public function chunkCountForType(string $type): int
    {
        $total = count($this->urlsForType($type));

        return max(1, (int) ceil($total / self::MAX_URLS_PER_FILE));
    }

    /** نوع‌هایی که باید در sitemap بیایند. */
    public function sitemapTypes(): array
    {
        return array_keys(array_filter(
            $this->registry->all(),
            fn ($cfg) => ($cfg['sitemap'] ?? false) === true
        ));
    }

    /**
     * آیتم‌های یک نوع برای <urlset>.
     *
     * @return list<array{loc:string,lastmod:?string,changefreq:string,priority:string}>
     */
    public function urlsForType(string $type): array
    {
        $cfg = $this->registry->config($type);
        if (! $cfg || ($cfg['sitemap'] ?? false) !== true) {
            return [];
        }

        // صفحاتِ ترکیبیِ دستگاه+برند ردیفِ مستقیمِ مدل ندارند؛ از combo-manager
        // (DeviceBrandPageِ فعال) ساخته می‌شوند.
        if (($cfg['resolver'] ?? null) === 'brand_device') {
            return $this->brandDeviceUrls($cfg);
        }

        /** @var class-string $modelClass */
        $modelClass = $cfg['model'];
        $query = $modelClass::query();
        if (method_exists($modelClass, 'seoMeta')) {
            $query->with('seoMeta');
        }

        $priority = $this->numeric($cfg['priority'] ?? config('seo.sitemap.priority', 0.7));
        $changefreq = (string) ($cfg['changefreq'] ?? config('seo.sitemap.changefreq', 'weekly'));
        $publishedCol = $cfg['published'] ?? null;

        $out = [];
        foreach ($query->cursor() as $model) {
            if (! $this->isIndexable($model, $publishedCol)) {
                continue;
            }
            $out[] = [
                'loc' => $this->absoluteUrl($this->registry->pathFor($type, $model)),
                'lastmod' => $this->lastmod($model),
                'changefreq' => $changefreq,
                'priority' => number_format($priority, 1),
            ];
        }

        return $out;
    }

    /**
     * URLهای صفحاتِ ترکیبیِ فعال: فقط DeviceBrandPageهایی که ادمین is_active کرده
     * و هم دستگاه و هم برندشان فعال است → /services/{deviceSlug}/{brandSlug}.
     *
     * @param  array<string, mixed>  $cfg
     * @return list<array{loc:string,lastmod:?string,changefreq:string,priority:string}>
     */
    private function brandDeviceUrls(array $cfg): array
    {
        $priority = $this->numeric($cfg['priority'] ?? config('seo.sitemap.priority', 0.7));
        $changefreq = (string) ($cfg['changefreq'] ?? config('seo.sitemap.changefreq', 'weekly'));
        $pattern = (string) ($cfg['url'] ?? '/services/{device}/{brand}');

        $out = [];
        $rows = \Modules\CRM\Models\DeviceBrandPage::query()
            ->where('is_active', true)
            ->with(['device:id,slug,is_active,updated_at', 'brand:id,slug,is_active,updated_at'])
            ->cursor();

        foreach ($rows as $page) {
            $device = $page->device;
            $brand = $page->brand;
            if (! $device || ! $brand || ! $device->is_active || ! $brand->is_active) {
                continue;
            }
            $path = str_replace(['{device}', '{brand}'], [$device->slug, $brand->slug], $pattern);
            $out[] = [
                'loc' => $this->absoluteUrl($path),
                'lastmod' => $this->lastmod($page),
                'changefreq' => $changefreq,
                'priority' => number_format($priority, 1),
            ];
        }

        return $out;
    }

    /**
     * فهرست sitemapهای هر نوع برای <sitemapindex>.
     *
     * @return list<array{type:string,loc:string,lastmod:?string}>
     */
    public function index(): array
    {
        $out = [];
        $now = Carbon::now()->toAtomString();
        foreach ($this->sitemapTypes() as $type) {
            $chunks = $this->chunkCountForType($type);
            if ($chunks <= 1) {
                $out[] = [
                    'type' => $type,
                    'loc' => $this->absoluteUrl('/v1/seo/sitemap/'.$type.'.xml'),
                    'lastmod' => $now,
                ];

                continue;
            }
            // نوعِ بزرگ → چند فایلِ {type}-{page}.xml
            for ($p = 1; $p <= $chunks; $p++) {
                $out[] = [
                    'type' => $type,
                    'loc' => $this->absoluteUrl('/v1/seo/sitemap/'.$type.'-'.$p.'.xml'),
                    'lastmod' => $now,
                ];
            }
        }

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | لایهٔ منطبق‌بر‌اسپکِ سئو (v2) — کنارِ index()/urlsForType قدیمی
    |--------------------------------------------------------------------------
    | ساختارِ درخواستیِ سئو: sitemap.xml (index) → sitemaps/sitemap-{name}.xml.
    | تفاوت‌ها با نسخهٔ قدیمی:
    |   - نام‌گذاری و مسیرِ اسپک (sitemap-pages, sitemap-service-brands, …)
    |   - بدونِ changefreq / priority / lastmod
    |   - فیلترِ canonical-self (اگر canonical به URLِ دیگری اشاره کند حذف می‌شود)
    |   - sitemap-pages شاملِ هاب‌ها/صفحاتِ ثابتِ اصلی هم هست
    */

    /** نگاشتِ نامِ فایلِ سایت‌مپ (اسپک) → نوعِ داخلیِ رجیستری. ترتیب = ترتیبِ index. */
    public const SPEC_FILES = [
        'pages' => 'page',
        'services' => 'device',
        'service-brands' => 'brand_device',
        'brands' => 'brand',
        'articles' => 'article',
        'blog-topics' => 'blog_topic',
        'faq-taxonomies' => 'taxonomy',
        'forum' => 'forum_question',
    ];

    /** هاب‌ها/صفحاتِ ثابتِ اصلی که باید در sitemap-pages باشند (بندِ ۵ اسپک). */
    public const STATIC_PATHS = [
        '/', '/services', '/brands', '/blog', '/faq', '/forum', '/about', '/guarantee', '/pricing',
    ];

    /**
     * آیتم‌های <sitemapindex> با نام‌گذاری و مسیرِ اسپک. همهٔ ۸ فایل معرفی می‌شوند.
     *
     * @return list<array{loc:string}>
     */
    public function specIndex(): array
    {
        $out = [];
        foreach (array_keys(self::SPEC_FILES) as $name) {
            $out[] = ['loc' => $this->absoluteUrl('/sitemaps/sitemap-'.$name.'.xml')];
        }

        return $out;
    }

    /**
     * URLهای یک فایلِ سایت‌مپِ اسپک (فقط <loc>؛ بدونِ lastmod/changefreq/priority).
     *
     * @return list<array{loc:string}>
     */
    public function specUrls(string $name): array
    {
        if (! array_key_exists($name, self::SPEC_FILES)) {
            return [];
        }

        return $name === 'pages'
            ? $this->specPageUrls()
            : $this->specTypeUrls(self::SPEC_FILES[$name]);
    }

    /** آیا نامِ فایل معتبر است؟ (برای ۴۰۴ در کنترلر). */
    public function specFileExists(string $name): bool
    {
        return array_key_exists($name, self::SPEC_FILES);
    }

    /**
     * URLهای یک نوعِ مدل‌محور با شرایطِ اسپک: منتشرشده/فعال، بدونِ noindex،
     * و canonical به خودِ صفحه (نه URLِ دیگر).
     *
     * @return list<array{loc:string}>
     */
    private function specTypeUrls(string $type): array
    {
        $cfg = $this->registry->config($type);
        if (! $cfg) {
            return [];
        }

        // صفحاتِ ترکیبیِ دستگاه×برند — canonical همیشه به خودشان است (بدونِ override).
        if (($cfg['resolver'] ?? null) === 'brand_device') {
            return array_map(fn ($u) => ['loc' => $u['loc']], $this->brandDeviceUrls($cfg));
        }

        /** @var class-string $modelClass */
        $modelClass = $cfg['model'];
        $query = $modelClass::query();
        if (method_exists($modelClass, 'seoMeta')) {
            $query->with('seoMeta');
        }
        $publishedCol = $cfg['published'] ?? null;

        $out = [];
        foreach ($query->cursor() as $model) {
            if (! $this->isIndexable($model, $publishedCol)) {
                continue;
            }
            $loc = $this->absoluteUrl($this->registry->pathFor($type, $model));
            if ($this->canonicalConflicts($model, $loc)) {
                continue; // canonical به URLِ دیگری اشاره می‌کند → از سایت‌مپ حذف
            }
            $out[] = ['loc' => $loc];
        }

        return $out;
    }

    /**
     * صفحاتِ فایلِ «pages»: هاب‌ها/صفحاتِ ثابتِ اصلی + صفحاتِ CMS (site_pages)
     * که در فهرستِ ثابت نیستند. dedupe بر اساسِ مسیرِ نرمال‌شده.
     *
     * @return list<array{loc:string}>
     */
    private function specPageUrls(): array
    {
        $out = [];
        $seen = [];

        foreach (self::STATIC_PATHS as $path) {
            $loc = $this->absoluteUrl($path);
            $seen[$this->pathKey($loc)] = true;
            $out[] = ['loc' => $loc];
        }

        foreach ($this->specTypeUrls('page') as $u) {
            $key = $this->pathKey($u['loc']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $u;
        }

        return $out;
    }

    /** آیا canonicalِ ثبت‌شده با URLِ خودِ صفحه فرق دارد؟ (بدونِ override → خیر). */
    private function canonicalConflicts(object $model, string $url): bool
    {
        $meta = method_exists($model, 'seoMeta') ? $model->seoMeta : null;
        $canonical = ($meta && filled($meta->canonical)) ? trim((string) $meta->canonical) : '';
        if ($canonical === '') {
            return false;
        }

        return rtrim($canonical, '/') !== rtrim($url, '/');
    }

    /** کلیدِ dedupe: مسیرِ بدونِ اسلشِ انتهایی. */
    private function pathKey(string $url): string
    {
        return rtrim($url, '/');
    }

    private function isIndexable(object $model, ?string $publishedCol): bool
    {
        // آیتمی که در متای خودش noindex خورده، از sitemap حذف می‌شود.
        $meta = method_exists($model, 'seoMeta') ? $model->seoMeta : null;
        if ($meta && $meta->robots_noindex) {
            return false;
        }

        if ($publishedCol) {
            $val = $model->getAttribute($publishedCol);

            return $val !== null && $val !== '' && Carbon::parse($val)->lessThanOrEqualTo(now());
        }

        // مدل‌هایی که ستون is_active دارند (برند/دستگاه/تاکسونومی).
        if (array_key_exists('is_active', $model->getAttributes())) {
            return (bool) $model->getAttribute('is_active');
        }

        return true;
    }

    private function lastmod(object $model): ?string
    {
        $val = $model->getAttribute('updated_at') ?? $model->getAttribute('created_at');

        return $val ? Carbon::parse($val)->toAtomString() : null;
    }

    private function numeric($v): float
    {
        return is_numeric($v) ? (float) $v : 0.7;
    }

    private function absoluteUrl(string $path): string
    {
        $base = SeoSetting::siteUrl();

        // percent-encode هر segment (برای slugهای فارسی) تا <loc> معتبر بماند؛
        // «/» حفظ می‌شود و ASCII بدونِ تغییر می‌ماند.
        $path = '/'.ltrim($path, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $base.$encoded;
    }
}
