<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Seo\Services\SitemapBuilder;

/**
 * sitemap.xml (index + per-type) — XML معتبر برای موتورهای جستجو، مستقیم
 * از بک‌اند سرو می‌شود.
 */
class SitemapController extends Controller
{
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
