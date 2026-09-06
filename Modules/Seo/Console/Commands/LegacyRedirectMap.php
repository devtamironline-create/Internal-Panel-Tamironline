<?php

namespace Modules\Seo\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Models\SeoNotFound;
use Modules\Seo\Models\SeoRedirect;
use Modules\Site\Models\Article;

/**
 * پکیجِ دادهٔ «تعیین‌تکلیفِ مسیرهای خدمات و ریدایرکت‌های قدیمی» برای تیمِ فرانت.
 *
 * فقط‌خواندنی. خروجی:
 *   • جدولِ ریدایرکتِ اسلاگ‌های قدیمیِ *-repair از لاگِ ۴۰۴ → مقصدِ کنونیکالِ
 *     منتشرشده (تفکیکِ device/brand از روی اسلاگ‌های واقعیِ دیتابیس، نه regexِ کور).
 *   • وضعیتِ دو کامبویِ خاص (فیلیپس/لورچ).
 *   • منبعِ لینک‌های داخلیِ CMS به کامبوهای ناموجود.
 *
 *   php artisan seo:legacy-redirect-map
 *   php artisan seo:legacy-redirect-map --out=/path/legacy-redirects.csv
 *   php artisan seo:legacy-redirect-map --all   # همهٔ *-repairها، نه فقط لاگِ ۴۰۴
 */
class LegacyRedirectMap extends Command
{
    protected $signature = 'seo:legacy-redirect-map {--out= : مسیرِ CSV (پیش‌فرض: storage/app/legacy-redirects.csv)} {--all : علاوه بر لاگِ ۴۰۴، همهٔ اسلاگ‌های *-repairِ قابلِ‌تولید را هم بده} {--write : ردیف‌های status=published را به‌شکلِ idempotent در جدولِ seo_redirects ثبت/به‌روزرسانی کن}';

    protected $description = 'دادهٔ ریدایرکتِ قدیمی + وضعیتِ کامبوها + لینک‌های CMS برای تیمِ فرانت (فقط‌خواندنی)';

    public function handle(): int
    {
        // ── نقشهٔ کامبوهای منتشرشده: کلید {a}-{b} و {b}-{a} → /services/{device}/{brand} ──
        $publishedMap = [];
        $publishedSet = [];
        DeviceBrandPage::query()->where('is_active', true)
            ->with(['device:id,slug,is_active', 'brand:id,slug,is_active'])
            ->chunk(500, function ($rows) use (&$publishedMap, &$publishedSet) {
                foreach ($rows as $p) {
                    $d = $p->device;
                    $b = $p->brand;
                    if (! $d || ! $b || ! $d->is_active || ! $b->is_active) {
                        continue;
                    }
                    $dest = "/services/{$d->slug}/{$b->slug}";
                    $publishedMap[$b->slug.'-'.$d->slug] = $dest;
                    $publishedMap[$d->slug.'-'.$b->slug] = $dest;
                    $publishedSet[$d->slug.'/'.$b->slug] = true;
                }
            });

        $deviceSlugs = Device::query()->pluck('slug')->filter()->values()->all();
        $brandSlugs = Brand::query()->pluck('slug')->filter()->values()->all();
        // بلندترین‌ها اول — تا اسلاگِ چندکلمه‌ای (side-by-side, wall-mounted-boiler) درست جدا شود.
        usort($deviceSlugs, fn ($a, $b) => strlen($b) <=> strlen($a));
        usort($brandSlugs, fn ($a, $b) => strlen($b) <=> strlen($a));
        $deviceSet = array_flip($deviceSlugs);
        $brandSet = array_flip($brandSlugs);

        // ── منبعِ اسلاگ‌ها: لاگِ ۴۰۴ (و اختیاراً همهٔ *-repairهای قابلِ‌تولید) ──
        $legacySlugs = [];
        SeoNotFound::query()->whereNull('ignored_at')->orderByDesc('hits')
            ->chunk(500, function ($rows) use (&$legacySlugs) {
                foreach ($rows as $r) {
                    $path = parse_url((string) $r->uri, PHP_URL_PATH) ?? (string) $r->uri;
                    $path = '/'.ltrim($path, '/');
                    if (preg_match('#^/services/([a-z0-9-]+-repair)/?$#', $path, $m)) {
                        $legacySlugs[$m[1]] = (int) $r->hits;
                    }
                }
            });

        if ($this->option('all')) {
            // همهٔ ترکیب‌های منتشرشده به‌شکلِ {brand}-{device}-repair (الگوی وردپرس).
            foreach ($publishedSet as $pair => $_) {
                [$d, $b] = explode('/', $pair);
                $legacySlugs[$b.'-'.$d.'-repair'] ??= 0;
            }
        }

        // ── حلِ هر اسلاگ ──
        $rowsOut = [];
        foreach ($legacySlugs as $slug => $hits) {
            $core = preg_replace('/-repair$/', '', $slug);
            $dest = $publishedMap[$core] ?? null;
            $status = 'unresolved';

            if ($dest !== null) {
                $status = 'published';
            } else {
                // تلاش برای تفکیکِ device/brand حتی اگر منتشر نشده (برای گزارش، نه ریدایرکت).
                [$d, $b] = $this->split($core, $deviceSet, $brandSet);
                if ($d && $b) {
                    $dest = "/services/{$d}/{$b}";
                    $status = isset($publishedSet[$d.'/'.$b]) ? 'published' : 'valid-but-unpublished';
                }
            }

            $rowsOut[] = [
                'source' => "/services/{$slug}",
                'destination' => $dest ?? '—',
                'status' => $status,
                'hits' => $hits,
            ];
        }

        // مرتب: unresolved/unpublished اول (نیازِ تصمیم)، سپس بر اساسِ hits.
        usort($rowsOut, fn ($a, $b) => [$a['status'] === 'published' ? 1 : 0, -$a['hits']] <=> [$b['status'] === 'published' ? 1 : 0, -$b['hits']]);

        // ── CSV ──
        $out = (string) ($this->option('out') ?: storage_path('app/legacy-redirects.csv'));
        $fh = @fopen($out, 'w');
        if ($fh === false) {
            $this->error("نمی‌توان نوشت: {$out}");

            return self::FAILURE;
        }
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['source', 'destination', 'status', 'hits']);
        foreach ($rowsOut as $r) {
            fputcsv($fh, [$r['source'], $r['destination'], $r['status'], $r['hits']]);
        }
        fclose($fh);

        $this->info('CSV نوشته شد: '.$out.' ('.count($rowsOut).' مسیر)');
        $this->table(['source', 'destination', 'status', 'hits'],
            array_map(fn ($r) => [$r['source'], $r['destination'], $r['status'], $r['hits']], array_slice($rowsOut, 0, 40)));
        if (count($rowsOut) > 40) {
            $this->line('… '.(count($rowsOut) - 40).' ردیفِ دیگر در CSV.');
        }

        // ── بخش ۲: ثبت/گزارشِ ریدایرکت‌های تأییدشده در seo_redirects (فقط published) ──
        $published = array_values(array_filter($rowsOut, fn ($r) => $r['status'] === 'published'));
        if ($this->option('write')) {
            $this->writeRedirects($published);
        }
        $this->newLine();
        $this->info('── جدولِ ریدایرکت‌های تأییدشده در seo_redirects (بخش ۲ نامهٔ فرانت) ──');
        $seoRows = [];
        foreach ($published as $r) {
            $rec = SeoRedirect::query()->where('source', $r['source'])->first();
            $seoRows[] = [
                $r['source'],
                $r['destination'],
                $rec->match_type ?? 'exact',
                (string) ($rec->status_code ?? 301),
                $rec ? ($rec->is_active ? 'منتشرشده (is_active=1)' : 'غیرفعال (is_active=0)') : 'در seo_redirects نیست',
            ];
        }
        $this->table(['source', 'destination', 'match_type', 'status_code', 'وضعیتِ انتشار'], $seoRows);
        if (! $this->option('write')) {
            $this->line('برای ثبتِ خودکارِ این ردیف‌ها در جدول: همین دستور را با --write اجرا کنید (idempotent، بدونِ رکوردِ تکراری).');
        }

        // ── بخش ۱: دو کامبویِ خاص ──
        $this->newLine();
        $this->info('── وضعیتِ دو کامبویِ خاص (بخش ۱ نامهٔ فرانت) ──');
        foreach ([['vacuum-cleaner', 'philips'], ['wall-mounted-boiler', 'lorch']] as [$ds, $bs]) {
            $this->line($this->comboFacts($ds, $bs, $publishedSet));
        }

        // ── بخش ۳: لینک‌های CMS به کامبوهای ناموجود ──
        $this->newLine();
        $this->info('── لینک‌های داخلیِ CMS به کامبوها (بخش ۳) ──');
        foreach (['/services/vacuum-cleaner/philips', '/services/wall-mounted-boiler/lorch'] as $needle) {
            $hitsArticles = $this->articlesLinking($needle);
            $this->line($needle.' → '.($hitsArticles === [] ? 'در محتوای مقالات یافت نشد' : implode('، ', $hitsArticles)));
        }

        return self::SUCCESS;
    }

    /**
     * ثبتِ idempotentِ ریدایرکت‌های تأییدشده در seo_redirects.
     * کلیدِ یکتا: source (updateOrCreate) → هیچ رکوردِ تکراری ساخته نمی‌شود؛
     * اجرای دوباره فقط mقصد/کد را هم‌سو می‌کند و hits/last_hit_at دست‌نخورده می‌ماند.
     */
    private function writeRedirects(array $published): void
    {
        $created = 0;
        $updated = 0;
        foreach ($published as $r) {
            if ($r['destination'] === '—' || $r['destination'] === $r['source']) {
                continue; // مقصدِ نامعتبر یا حلقه — رد.
            }
            $existing = SeoRedirect::query()->where('source', $r['source'])->first();
            $attrs = [
                'target' => $r['destination'],
                'status_code' => 301,
                'match_type' => 'exact',
                'is_active' => true,
            ];
            if ($existing) {
                $existing->fill($attrs)->save(); // hits/last_hit_at لمس نمی‌شود.
                $updated++;
            } else {
                SeoRedirect::query()->create(array_merge(['source' => $r['source']], $attrs, ['hits' => 0]));
                $created++;
            }
        }
        $this->info("seo_redirects به‌روزرسانی شد: {$created} رکوردِ جدید، {$updated} رکوردِ موجودِ هم‌سوشده (بدونِ تکرار).");
    }

    /** تفکیکِ {brand}-{device} یا {device}-{brand} از روی اسلاگ‌های واقعی (بلندترین اول). */
    private function split(string $core, array $deviceSet, array $brandSet): array
    {
        // الگوی وردپرس: {brand}-{device}
        foreach ($brandSet as $b => $_) {
            if (str_starts_with($core, $b.'-')) {
                $rest = substr($core, strlen($b) + 1);
                if (isset($deviceSet[$rest])) {
                    return [$rest, $b];
                }
            }
        }
        // الگوی معکوس: {device}-{brand}
        foreach ($deviceSet as $d => $_) {
            if (str_starts_with($core, $d.'-')) {
                $rest = substr($core, strlen($d) + 1);
                if (isset($brandSet[$rest])) {
                    return [$d, $rest]; // [device, brand]
                }
            }
        }

        return [null, null];
    }

    private function comboFacts(string $ds, string $bs, array $publishedSet): string
    {
        $dev = Device::query()->where('slug', $ds)->first();
        $brand = Brand::query()->where('slug', $bs)->first();
        $page = ($dev && $brand)
            ? DeviceBrandPage::query()->where('device_id', $dev->id)->where('brand_id', $brand->id)->first()
            : null;
        $contentLen = $page ? mb_strlen((string) ($page->getAttribute('content') ?? '')) : 0;

        return sprintf(
            '%s/%s → device:%s brand:%s page:%s%s | منتشرشده در sitemap: %s',
            $ds, $bs,
            $dev ? "#{$dev->id}(active=".(int) $dev->is_active.')' : 'ناموجود',
            $brand ? "#{$brand->id}(active=".(int) $brand->is_active.')' : 'ناموجود',
            $page ? "#{$page->id}(active=".(int) $page->is_active.')' : 'ندارد',
            $page ? " content_len={$contentLen}" : '',
            isset($publishedSet[$ds.'/'.$bs]) ? 'بله' : 'خیر',
        );
    }

    /** مقالاتی که در متنشان لینکِ داده‌شده وجود دارد. */
    private function articlesLinking(string $needle): array
    {
        $out = [];
        Article::query()->where('content', 'like', '%'.$needle.'%')
            ->select('id', 'slug', 'title')
            ->chunk(200, function ($rows) use (&$out) {
                foreach ($rows as $a) {
                    $out[] = "#{$a->id} «".($a->getAttribute('title') ?: $a->getAttribute('slug')).'»';
                }
            });

        return $out;
    }
}
