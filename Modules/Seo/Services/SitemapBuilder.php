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
        $base = rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/');

        // percent-encode هر segment (برای slugهای فارسی) تا <loc> معتبر بماند؛
        // «/» حفظ می‌شود و ASCII بدونِ تغییر می‌ماند.
        $path = '/'.ltrim($path, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $base.$encoded;
    }
}
