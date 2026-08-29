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

        // صفحاتِ سئوِ شهری — مسیرِ کاملِ pathِ هر صفحهٔ منتشرشده.
        if (($cfg['resolver'] ?? null) === 'city_page') {
            return $this->cityPageUrls($cfg);
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
     * URLهای صفحاتِ سئوِ شهریِ منتشرشده (SEO-024). مسیرِ کامل در ستونِ path
     * است؛ فقط status=published + published_at غیرخالی وارد می‌شوند.
     *
     * @param  array<string, mixed>  $cfg
     * @return list<array{loc:string,lastmod:?string,changefreq:string,priority:string}>
     */
    private function cityPageUrls(array $cfg): array
    {
        $priority = $this->numeric($cfg['priority'] ?? config('seo.sitemap.priority', 0.7));
        $changefreq = (string) ($cfg['changefreq'] ?? config('seo.sitemap.changefreq', 'weekly'));

        $out = [];
        $rows = \Modules\CRM\Models\CityPage::query()->published()->with('seoMeta')->cursor();

        foreach ($rows as $page) {
            // صفحه‌ای که در پنلِ سئو noindex خورده از sitemap حذف می‌شود.
            if ($page->seoMeta && $page->seoMeta->robots_noindex) {
                continue;
            }
            $out[] = [
                'loc' => $this->absoluteUrl((string) $page->path),
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

    /**
     * نگاشتِ نامِ فایلِ سایت‌مپ → نوعِ داخلیِ رجیستری.
     * نام‌گذاری طبقِ «نامهٔ نقشهٔ ایندکس» (تیر ۱۴۰۵): core / services-devices /
     * services-combo / brands / forum / blog-1 / blog-2.
     * نام‌های قدیمی به‌عنوانِ alias نگه داشته شده‌اند (GSC ممکن است هنوز fetch کند)
     * ولی در index معرفی نمی‌شوند.
     */
    public const SPEC_FILES = [
        // نام‌گذاریِ جدید (نامهٔ نقشهٔ ایندکس)
        'core' => 'page',
        'local' => 'city_page',
        'services-devices' => 'device',
        'services-combo' => 'brand_device',
        'brands' => 'brand',
        'forum' => 'forum_question',
        'blog-1' => 'article',
        'blog-2' => 'article',
        // aliasهای قدیمی — فقط برای سازگاری؛ در INDEX_FILES نیستند.
        'pages' => 'page',
        'services' => 'device',
        'service-brands' => 'brand_device',
        'articles' => 'article',
        'blog-topics' => 'blog_topic',
        'faq-taxonomies' => 'taxonomy',
    ];

    /**
     * فایل‌هایی که در sitemap.xml اصلی معرفی می‌شوند — ۷ فایلِ نامهٔ نقشهٔ ایندکس،
     * تا نرخِ ایندکسِ هر بخش در Search Console جدا پایش شود.
     * blog-topics / faq-taxonomies عمداً معرفی نمی‌شوند تا کیفیتِ محتوای
     * مستقل‌شان بررسی شود (کدشان می‌ماند و از مسیرِ نام‌دار سرو می‌شوند).
     */
    public const INDEX_FILES = [
        'core', 'local', 'services-devices', 'services-combo', 'brands', 'forum', 'blog-1', 'blog-2',
    ];

    /** هاب‌ها/صفحاتِ ثابتِ اصلی که باید در sitemap-core باشند. */
    public const STATIC_PATHS = [
        '/', '/services', '/brands', '/blog', '/faq', '/forum', '/about', '/contact', '/guarantee', '/pricing',
    ];

    /** پیشوندهای مسیر که تحتِ هیچ شرایطی نباید وارد سایت‌مپ شوند (blacklist صریح). */
    public const BLACKLIST_PREFIXES = [
        '/login', '/register', '/profile', '/dashboard', '/admin', '/api',
        '/orders', '/payment', '/otp', '/search', '/filter',
    ];

    /** قطعاتِ مسیرِ archive/pagination که باید حذف شوند. */
    public const BLACKLIST_SEGMENTS = ['/page/', '/tag/', '/author/', '/archive/'];

    /**
     * آیتم‌های <sitemapindex> — فعلاً فقط فایل‌های فازِ اول (INDEX_FILES).
     *
     * @return list<array{loc:string}>
     */
    public function specIndex(): array
    {
        $out = [];
        foreach (self::INDEX_FILES as $name) {
            $out[] = ['loc' => $this->absoluteUrl('/sitemaps/sitemap-'.$name.'.xml')];
        }

        return $out;
    }

    /**
     * URLهای یک فایلِ سایت‌مپِ اسپک (فقط <loc>؛ بلاگ/فروم lastmod واقعی دارند).
     *
     * @return list<array{loc:string,lastmod?:string}>
     */
    public function specUrls(string $name): array
    {
        if (! array_key_exists($name, self::SPEC_FILES)) {
            return [];
        }

        if ($name === 'core' || $name === 'pages') {
            return $this->specPageUrls();
        }

        // بلاگ به دو فایل تقسیم می‌شود (blog-1 نیمهٔ اول، blog-2 نیمهٔ دوم بر اساسِ id)
        // تا نرخِ ایندکسِ مقالات در GSC در دو دستهٔ قابلِ‌پایش دیده شود.
        if ($name === 'blog-1' || $name === 'blog-2') {
            $all = $this->specTypeUrls('article');
            $half = (int) ceil(count($all) / 2);

            return $name === 'blog-1'
                ? array_slice($all, 0, $half)
                : array_slice($all, $half);
        }

        return $this->specTypeUrls(self::SPEC_FILES[$name]);
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
            $out = [];
            foreach ($this->brandDeviceUrls($cfg) as $u) {
                if ($this->passesGuard($u['loc'])) {
                    $out[] = ['loc' => $u['loc']];
                }
            }

            return $out;
        }

        // صفحاتِ سئوِ شهریِ منتشرشده.
        if (($cfg['resolver'] ?? null) === 'city_page') {
            $out = [];
            foreach ($this->cityPageUrls($cfg) as $u) {
                if ($this->passesGuard($u['loc'])) {
                    $out[] = ['loc' => $u['loc']];
                }
            }

            return $out;
        }

        /** @var class-string $modelClass */
        $modelClass = $cfg['model'];
        $query = $modelClass::query();
        if (method_exists($modelClass, 'seoMeta')) {
            $query->with('seoMeta');
        }

        // فورَم: فقط پرسش‌های منتشر+تأییدشده که حداقل یک «پاسخِ تخصصیِ تأییدشده»
        // دارند + بالای آستانهٔ بازدید (سیاستِ ایندکس — سوالاتِ کم‌بازده حذف).
        if ($type === 'forum_question') {
            $query->approved()
                ->whereHas('approvedAnswers', fn ($q) => $q->where('is_expert_reply', true));
            $minViews = \Modules\Seo\Support\IndexingPolicy::forumMinViews();
            if ($minViews > 0) {
                $query->where('view_count', '>=', $minViews);
            }
        }

        // بلاگ: ترتیبِ قطعی بر اساسِ id تا تقسیمِ blog-1/blog-2 پایدار بماند.
        if ($type === 'article') {
            $query->orderBy('id');
        }

        $publishedCol = $cfg['published'] ?? null;

        $out = [];
        foreach ($query->cursor() as $model) {
            if (! $this->isIndexable($model, $publishedCol)) {
                continue;
            }
            // برندهای نحیف (خارج از whitelistِ ایندکس) هم از سایت‌مپ حذف می‌شوند
            // تا سایت‌مپ با robotsِ per-page هم‌خوان بماند.
            if ($type === 'brand'
                && ! \Modules\Seo\Support\IndexingPolicy::brandIndexable((string) $model->getAttribute('slug'))) {
                continue;
            }
            $loc = $this->absoluteUrl($this->registry->pathFor($type, $model));
            if ($this->canonicalConflicts($model, $loc)) {
                continue; // canonical به URLِ دیگری اشاره می‌کند → از سایت‌مپ حذف
            }
            if (! $this->passesGuard($loc)) {
                continue; // مسیرِ blacklist / پارامتردار / fragment / archive
            }
            $entry = ['loc' => $loc];
            // lastmod فقط جایی که مقدارِ واقعی داریم (نامهٔ نقشهٔ ایندکس: تاریخِ
            // ساختگی بدتر از نبودن است):
            //  - فورَم: updated_at (با هر پاسخ/ویرایش تغییر می‌کند)
            //  - بلاگ: updated_at اگر بعد از created_at ویرایش شده؛ وگرنه published_at
            //    (تاریخِ واقعیِ انتشار — نه تاریخِ یکسانِ import).
            if ($type === 'forum_question' && $model->getAttribute('updated_at')) {
                $entry['lastmod'] = $model->getAttribute('updated_at')->toDateString();
            }
            if ($type === 'article' && ($lm = $this->articleLastmod($model))) {
                $entry['lastmod'] = $lm;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * lastmod واقعیِ مقاله: اگر بعد از ساخت ویرایش شده updated_at؛ وگرنه
     * published_at (تاریخِ واقعیِ WP). اگر هیچ‌کدام نبود، null → تگ حذف می‌شود.
     */
    private function articleLastmod(object $model): ?string
    {
        $updated = $model->getAttribute('updated_at');
        $created = $model->getAttribute('created_at');
        if ($updated && $created && Carbon::parse($updated)->greaterThan(Carbon::parse($created))) {
            return Carbon::parse($updated)->toDateString();
        }

        $published = $model->getAttribute('published_at');

        return $published ? Carbon::parse($published)->toDateString() : null;
    }

    /**
     * گاردِ سراسری: هیچ URLی با query/fragment، پیشوندِ blacklist، یا قطعهٔ
     * archive/pagination نباید وارد سایت‌مپ شود. مکملِ فیلترِ موجودیتِ DB.
     */
    private function passesGuard(string $loc): bool
    {
        // query string یا fragment → حذف
        if (str_contains($loc, '?') || str_contains($loc, '#')) {
            return false;
        }

        $path = parse_url($loc, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return true;
        }
        $path = '/'.ltrim(rawurldecode($path), '/');
        $lower = mb_strtolower($path);

        foreach (self::BLACKLIST_PREFIXES as $prefix) {
            if ($lower === $prefix || str_starts_with($lower, $prefix.'/')) {
                return false;
            }
        }
        foreach (self::BLACKLIST_SEGMENTS as $seg) {
            if (str_contains($lower, $seg)) {
                return false;
            }
        }

        return true;
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
            if (! $this->passesGuard($loc)) {
                continue;
            }
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
