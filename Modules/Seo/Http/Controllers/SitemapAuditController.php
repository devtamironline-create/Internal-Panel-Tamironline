<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoableRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * خروجیِ کاملِ ممیزیِ Sitemap — CSVهایی که مستقیم از دیتابیس ساخته می‌شوند
 * تا تیمِ سئو Sitemap را از نو و اصولی طراحی کند.
 *
 *  - pages: هر ردیف یک URL از همهٔ انواعِ محتوا (شاملِ غیرفعال/پیش‌نویس/
 *    ریدایرکت‌شده — با ستونِ وضعیت و is_indexable).
 *  - redirects: old_url, new_url, status_code از جدولِ ریدایرکت‌ها.
 */
class SitemapAuditController extends Controller
{
    public function __construct(private readonly SeoableRegistry $registry) {}

    /** برچسبِ فارسیِ هر نوع + مسیرِ والد (برای ستون parent_url). */
    private const PARENT = [
        'article' => '/blog',
        'blog_topic' => '/blog',
        'brand_device' => 'device_mother', // محاسبه‌ای: صفحهٔ مادرِ دستگاه
        'brand' => '/brands',
        'device' => '/',
        'taxonomy' => '/faq',
        'forum_question' => '/forum',
        'page' => '/',
    ];

    public function pages(Request $request): StreamedResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(600);

        $base = rtrim(SeoSetting::siteUrl(), '/');
        // نگاشتِ ریدایرکت‌های exact: کلیدِ نرمالِ مسیر → مقصد (برای ستونِ redirect_to).
        $redirectMap = [];
        foreach (SeoRedirect::query()->where('match_type', 'exact')->get(['source', 'target', 'status_code', 'is_active']) as $r) {
            $redirectMap[$this->normPath((string) $r->source)] = [(string) $r->target, (int) $r->status_code, (bool) $r->is_active];
        }

        $headers = [
            'url', 'page_type', 'record_id', 'title', 'status', 'http_status',
            'canonical_url', 'meta_robots', 'is_indexable', 'created_at', 'updated_at',
            'redirect_to', 'parent_url', 'slug', 'template', 'is_active', 'language', 'image_url',
        ];

        $filename = 'tamironline-pages-'.date('Ymd-His').'.csv';

        return response()->stream(function () use ($headers, $base, $redirectMap) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM برای اکسل فارسی
            fputcsv($out, $headers);

            foreach ($this->registry->all() as $type => $cfg) {
                // device_brand_page همان URLهای brand_device را می‌سازد (نسخهٔ
                // ادمینِ ویرایش) — برای جلوگیری از URLِ تکراری در خروجی رد می‌شود.
                if ($type === 'device_brand_page') {
                    continue;
                }
                if (($cfg['resolver'] ?? null) === 'brand_device') {
                    $this->streamBrandDevice($out, $type, $cfg, $base, $redirectMap);

                    continue;
                }
                $this->streamType($out, $type, $cfg, $base, $redirectMap);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function redirects(): StreamedResponse
    {
        $filename = 'tamironline-redirects-'.date('Ymd-His').'.csv';

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['old_url', 'new_url', 'status_code', 'match_type', 'is_active']);
            foreach (SeoRedirect::query()->orderBy('id')->cursor() as $r) {
                fputcsv($out, [$r->source, $r->target, $r->status_code, $r->match_type, $r->is_active ? 'active' : 'inactive']);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  resource  $out
     * @param  array<string,mixed>  $cfg
     * @param  array<string,array{0:string,1:int,2:bool}>  $redirectMap
     */
    private function streamType($out, string $type, array $cfg, string $base, array $redirectMap): void
    {
        $modelClass = $cfg['model'] ?? null;
        if (! $modelClass || ! class_exists($modelClass)) {
            return;
        }

        $query = $modelClass::query();
        if (method_exists($modelClass, 'seoMeta')) {
            $query->with('seoMeta');
        }
        if (in_array($modelClass, [\Modules\Site\Models\Article::class], true) && method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        $titleAttr = $cfg['title_attr'] ?? 'title';
        $slugCol = $cfg['slug'] ?? 'slug';
        $publishedCol = $cfg['published'] ?? null;
        $template = (string) ($cfg['default_schema'] ?? $type);

        foreach ($query->cursor() as $m) {
            $path = $this->normPath($this->registry->pathFor($type, $m));
            $url = $base.$this->encode($path);
            $meta = method_exists($m, 'seoMeta') ? $m->seoMeta : null;

            [$isActive, $status] = $this->statusOf($m, $publishedCol);
            $indexable = $this->indexable($m, $meta, $publishedCol);
            $redir = $redirectMap[$path] ?? null;

            fputcsv($out, [
                $url,
                $type,
                $m->getKey(),
                $this->clean((string) ($m->getAttribute($titleAttr) ?? '')),
                $status,
                $redir && $redir[2] ? $redir[1] : 200,
                $meta && $meta->canonical ? $meta->canonical : $url,
                $indexable ? 'index, follow' : 'noindex, follow',
                $indexable ? 'yes' : 'no',
                $this->dt($m->getAttribute('created_at')),
                $this->dt($m->getAttribute('updated_at')),
                $redir && $redir[2] ? $redir[0] : '',
                $this->parentUrl($type, $m, $base),
                (string) ($m->getAttribute($slugCol) ?? ''),
                $template,
                $isActive ? 'yes' : 'no',
                'fa',
                $this->imageUrl($m, $meta, $base),
            ]);
        }
    }

    /**
     * صفحاتِ ترکیبیِ دستگاه+برند — از DeviceBrandPageهای فعال (مسیرِ
     * /services/{device}/{brand}).
     *
     * @param  resource  $out
     * @param  array<string,mixed>  $cfg
     * @param  array<string,array{0:string,1:int,2:bool}>  $redirectMap
     */
    private function streamBrandDevice($out, string $type, array $cfg, string $base, array $redirectMap): void
    {
        $rows = \Modules\CRM\Models\DeviceBrandPage::query()
            ->with(['device:id,slug,name,is_active', 'brand:id,slug,name,is_active', 'seoMeta'])
            ->get();

        foreach ($rows as $p) {
            $device = $p->device;
            $brand = $p->brand;
            if (! $device || ! $brand) {
                continue;
            }
            $path = $this->normPath('/services/'.$device->slug.'/'.$brand->slug);
            $url = $base.$this->encode($path);
            $meta = $p->seoMeta;

            $isActive = (bool) $p->is_active && (bool) $device->is_active && (bool) $brand->is_active;
            $indexable = $isActive && ! ($meta && $meta->robots_noindex);
            $redir = $redirectMap[$path] ?? null;

            fputcsv($out, [
                $url, $type, $p->getKey(),
                $this->clean(trim(($device->name ?? '').' '.($brand->name ?? ''))),
                $isActive ? 'active' : 'inactive',
                $redir && $redir[2] ? $redir[1] : 200,
                $meta && $meta->canonical ? $meta->canonical : $url,
                $indexable ? 'index, follow' : 'noindex, follow',
                $indexable ? 'yes' : 'no',
                $this->dt($p->created_at), $this->dt($p->updated_at),
                $redir && $redir[2] ? $redir[0] : '',
                $base.'/services/'.$device->slug,
                $device->slug.'/'.$brand->slug,
                (string) ($cfg['default_schema'] ?? 'Service'),
                $isActive ? 'yes' : 'no', 'fa',
                $this->imageUrl($p, $meta, $base),
            ]);
        }
    }

    /** @return array{0:bool,1:string} [is_active, status] */
    private function statusOf(object $m, ?string $publishedCol): array
    {
        // soft-deleted → حذف‌شده
        if (method_exists($m, 'trashed') && $m->trashed()) {
            return [false, 'deleted'];
        }
        if ($publishedCol) {
            $val = $m->getAttribute($publishedCol);
            $published = $val instanceof \DateTimeInterface ? $val <= now() : (bool) $val;
            // ستونِ is_published جدا (مثلِ Page/Article)
            if ($m->getAttribute('is_published') !== null) {
                $published = (bool) $m->getAttribute('is_published') && ($val === null || $published);
            }

            return [$published, $published ? 'published' : 'draft'];
        }
        if (array_key_exists('is_active', $m->getAttributes())) {
            $active = (bool) $m->getAttribute('is_active');

            return [$active, $active ? 'active' : 'inactive'];
        }
        // وضعیتِ رشته‌ای (مثلِ forum: pending/approved)
        $s = $m->getAttribute('status');
        if ($s !== null) {
            $ok = in_array($s, ['approved', 'active', 'published'], true);

            return [$ok, (string) $s];
        }

        return [true, 'active'];
    }

    private function indexable(object $m, $meta, ?string $publishedCol): bool
    {
        if ($meta && $meta->robots_noindex) {
            return false;
        }
        [$isActive] = $this->statusOf($m, $publishedCol);

        return $isActive;
    }

    private function parentUrl(string $type, object $m, string $base): string
    {
        $p = self::PARENT[$type] ?? '/';

        return $base.$p;
    }

    private function imageUrl(object $m, $meta, string $base): string
    {
        if ($meta && $meta->og_image) {
            return (string) $meta->og_image;
        }
        foreach (['thumbnail', 'image', 'cover', 'icon', 'logo'] as $col) {
            $v = $m->getAttribute($col);
            if (is_string($v) && $v !== '') {
                return str_starts_with($v, 'http') ? $v : $base.'/'.ltrim($v, '/');
            }
        }

        return '';
    }

    private function normPath(string $path): string
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: $path);

        return '/'.trim($path, '/');
    }

    private function encode(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function dt($v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }

        return (string) ($v ?? '');
    }

    private function clean(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($s)));
    }
}
