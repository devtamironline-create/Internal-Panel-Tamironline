<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;

/**
 * Instant Indexing از طریق IndexNow (Bing/Yandex/…). کلید در تنظیمات
 * (indexnow_key) و فایل کلید باید روی دامنه میزبانی شود:
 *   https://{host}/{key}.txt  →  محتوای آن دقیقاً همان key است.
 */
class IndexNowClient
{
    public function enabled(): bool
    {
        return trim((string) SeoSetting::get('indexnow_key')) !== '';
    }

    private function baseUrl(): string
    {
        return SeoSetting::siteUrl();
    }

    /**
     * @param  list<string>  $urls
     */
    public function submit(array $urls): bool
    {
        $key = trim((string) SeoSetting::get('indexnow_key'));
        if ($key === '' || $urls === []) {
            return false;
        }

        $host = (string) parse_url($this->baseUrl(), PHP_URL_HOST);

        try {
            $resp = Http::timeout(20)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $this->baseUrl().'/'.$key.'.txt',
                'urlList' => array_values($urls),
            ]);

            return $resp->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function submitOne(string $url): bool
    {
        return $this->submit([$url]);
    }

    /**
     * Ping استاندارد موتورها برای sitemap (Google/Bing).
     */
    public function pingSitemaps(): void
    {
        $sitemap = $this->baseUrl().'/v1/seo/sitemap-index.xml';
        foreach ([
            'https://www.google.com/ping?sitemap='.urlencode($sitemap),
            'https://www.bing.com/ping?sitemap='.urlencode($sitemap),
        ] as $endpoint) {
            try {
                Http::timeout(15)->get($endpoint);
            } catch (\Throwable $e) {
                // بی‌صدا — ping بهترین‌تلاش است.
            }
        }
    }
}
