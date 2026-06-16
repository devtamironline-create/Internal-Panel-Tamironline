<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;

/**
 * اتصال به Google PageSpeed Insights برای Core Web Vitals. فقط وقتی کلید
 * API در تنظیمات (pagespeed_api_key) موجود باشد کار می‌کند؛ در غیر این
 * صورت null برمی‌گرداند و کرال بدون CWV ادامه می‌یابد.
 */
class PageSpeedClient
{
    public function enabled(): bool
    {
        return trim((string) SeoSetting::get('pagespeed_api_key')) !== '';
    }

    /**
     * @return array{lcp:?float,cls:?float,inp:?float,fcp:?float,ttfb:?float,performance:?int}|null
     */
    public function measure(string $url, string $strategy = 'mobile'): ?array
    {
        $key = trim((string) SeoSetting::get('pagespeed_api_key'));
        if ($key === '') {
            return null;
        }

        try {
            $resp = Http::timeout(60)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', [
                'url' => $url,
                'strategy' => $strategy,
                'key' => $key,
                'category' => 'performance',
            ]);
            if (! $resp->successful()) {
                return null;
            }
            $json = $resp->json();
        } catch (\Throwable $e) {
            return null;
        }

        $metrics = $json['loadingExperience']['metrics'] ?? [];
        $lab = $json['lighthouseResult']['audits'] ?? [];

        return [
            'lcp' => $this->ms($metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null),
            'cls' => isset($metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'])
                ? round($metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100, 3)
                : null,
            'inp' => $this->ms($metrics['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null),
            'fcp' => $this->ms($metrics['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? null),
            'ttfb' => $this->ms($metrics['EXPERIMENTAL_TIME_TO_FIRST_BYTE']['percentile'] ?? null),
            'performance' => isset($json['lighthouseResult']['categories']['performance']['score'])
                ? (int) round($json['lighthouseResult']['categories']['performance']['score'] * 100)
                : null,
        ];
    }

    private function ms($v): ?float
    {
        return $v === null ? null : round(((float) $v) / 1000, 2);
    }
}
