<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Seo\Services\SitemapBuilder;

/**
 * sitemap.xml (index + per-type) — XML معتبر برای موتورهای جستجو، مستقیم
 * از بک‌اند سرو می‌شود.
 *
 * کش سمتِ سرور (spec-index و فایل‌های نام‌دار): هر پاسخ برای مدتِ کوتاهی کش
 * می‌شود تا هر درخواستِ خزنده/CDN، سایت‌مپ را از صفر از دیتابیس نسازد. زیرِ
 * بارِ خزش، بازساختِ هر‌باره‌ی فایل‌های سنگین (بلاگ/فروم/ترکیبی) کارگرهای
 * PHP-FPM را اشغال و به 504های مقطعی منجر می‌شد؛ کش این حلقه را می‌بندد.
 * ایمنیِ «آخرین نسخهٔ سالم»: اگر ساختِ تازه به خطا بخورد یا ناگهان خالی شود،
 * نسخهٔ غیرخالیِ قبلی سرو می‌شود تا سایت‌مپِ سالم با خروجیِ خالی جایگزین نشود.
 */
class SitemapController extends Controller
{
    /** مدتِ کشِ سمتِ سرور (دقیقه) — هم‌تراز با Cache-Control: max-age=3600. */
    private const CACHE_TTL_MINUTES = 60;

    /** نگه‌داریِ آخرین نسخهٔ سالم (غیرخالی) برای fallback. */
    private const LAST_GOOD_DAYS = 30;

    public function __construct(private readonly SitemapBuilder $builder) {}

    public function index(): Response
    {
        $items = $this->builder->index();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($items as $it) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>'.$this->esc($it['loc']).'</loc>'."\n";
            if (! empty($it['lastmod'])) {
                $xml .= '    <lastmod>'.$this->esc($it['lastmod']).'</lastmod>'."\n";
            }
            $xml .= "  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        return $this->xml($xml);
    }

    /**
     * Sitemap Index منطبق‌بر‌اسپکِ سئو — sitemap.xml اصلی.
     * فقط <loc> (بدونِ lastmod طبقِ بندِ ۷ اسپک).
     */
    public function specIndex(): Response
    {
        $xml = Cache::remember(
            'seo:sitemap:spec-index:xml',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->renderIndex($this->builder->specIndex())
        );

        return $this->xml($xml);
    }

    /** رِندرِ XMLِ sitemapindex از آیتم‌های {loc}. */
    private function renderIndex(array $items): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($items as $it) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>'.$this->esc($it['loc']).'</loc>'."\n";
            $xml .= "  </sitemap>\n";
        }

        return $xml.'</sitemapindex>';
    }

    /**
     * فایلِ سایت‌مپِ اسپک: sitemaps/sitemap-{name}.xml
     * فقط <loc>؛ به‌جز بلاگ/فروم که lastmod واقعی دارند (نامهٔ نقشهٔ ایندکس:
     * lastmod یا واقعی یا اصلاً نباشد — بدونِ changefreq/priority).
     */
    public function specNamed(string $name): Response
    {
        if (! $this->builder->specFileExists($name)) {
            return response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $key = 'seo:sitemap:spec:'.$name.':xml';
        $lastGoodKey = 'seo:sitemap:spec:'.$name.':last-good';

        // کشِ داغ — بدونِ بازساخت از دیتابیس.
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $this->xml($cached);
        }

        // بازساخت. اگر دریافتِ داده به خطا بخورد، به‌جای ۵۰۰/خالی، آخرین
        // نسخهٔ سالم سرو می‌شود (نامهٔ سئو، بند ۶ و بخشِ صحتِ داده).
        try {
            $urls = $this->builder->specUrls($name);
        } catch (\Throwable $e) {
            if (($lastGood = Cache::get($lastGoodKey)) !== null) {
                return $this->xml($lastGood);
            }
            throw $e;
        }

        $xml = $this->renderUrlset($urls);

        // ایمنیِ «خالیِ ناگهانی»: اگر ساختِ تازه خالی شد ولی قبلاً نسخهٔ
        // غیرخالی داشتیم، همان نسخهٔ سالم سرو می‌شود و خالی کش نمی‌شود (با TTLِ
        // کوتاه دوباره تلاش می‌کنیم). فایلی که واقعاً خالی است (بدونِ سابقهٔ
        // سالم) همان خالی را می‌گیرد.
        if ($urls === [] && ($lastGood = Cache::get($lastGoodKey)) !== null) {
            Cache::put($key, $lastGood, now()->addMinutes(10));

            return $this->xml($lastGood);
        }

        Cache::put($key, $xml, now()->addMinutes(self::CACHE_TTL_MINUTES));
        if ($urls !== []) {
            Cache::put($lastGoodKey, $xml, now()->addDays(self::LAST_GOOD_DAYS));
        }

        return $this->xml($xml);
    }

    /** رِندرِ XMLِ urlset از آیتم‌های {loc, lastmod?}. */
    private function renderUrlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->esc($u['loc']).'</loc>'."\n";
            if (! empty($u['lastmod'])) {
                $xml .= '    <lastmod>'.$this->esc($u['lastmod']).'</lastmod>'."\n";
            }
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>';
    }

    public function show(string $type, ?string $page = null): Response
    {
        $urls = $page === null
            ? $this->builder->urlsForType($type)
            : $this->builder->urlsForTypePaged($type, (int) $page);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->esc($u['loc']).'</loc>'."\n";
            if (! empty($u['lastmod'])) {
                $xml .= '    <lastmod>'.$this->esc($u['lastmod']).'</lastmod>'."\n";
            }
            $xml .= '    <changefreq>'.$this->esc($u['changefreq']).'</changefreq>'."\n";
            $xml .= '    <priority>'.$this->esc($u['priority']).'</priority>'."\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return $this->xml($xml);
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }
}
