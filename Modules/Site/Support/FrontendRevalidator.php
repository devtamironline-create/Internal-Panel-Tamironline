<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Site\Models\SiteSetting;

/**
 * فراخوانی webhook فرانت Next.js برای پاک‌سازی/بازاعتبارسنجی کش (on-demand revalidation).
 *
 * قرارداد: POST به revalidate_url با هدر x-revalidate-secret و بدنه‌ی
 *   { paths: string[], tags: string[], all: bool }
 *
 * fire-and-forget است؛ هرگز throw نمی‌کند و callerها نباید به موفقیتش وابسته باشند.
 */
class FrontendRevalidator
{
    private function url(): string
    {
        return trim((string) SiteSetting::get('revalidate_url'));
    }

    private function secret(): string
    {
        return trim((string) SiteSetting::get('revalidate_secret'));
    }

    public function enabled(): bool
    {
        return trim((string) SiteSetting::get('revalidate_enabled')) === '1'
            && $this->url() !== '';
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $tags
     * @return array{ok: bool, skipped?: bool, status?: int, message: string}
     */
    public function purge(array $paths = [], array $tags = [], bool $all = false): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'skipped' => true, 'message' => 'غیرفعال یا پیکربندی‌نشده'];
        }

        $url = $this->url();
        $secret = $this->secret();

        try {
            $resp = Http::timeout(8)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url, [
                    'paths' => array_values($paths),
                    'tags'  => array_values($tags),
                    'all'   => $all,
                ]);

            $ok = $resp->successful();

            return [
                'ok'      => $ok,
                'status'  => $resp->status(),
                'message' => $ok
                    ? 'درخواست پاک‌سازی با موفقیت ارسال شد.'
                    : 'فرانت با کد '.$resp->status().' پاسخ داد.',
            ];
        } catch (\Throwable $e) {
            Log::warning('FrontendRevalidator purge failed', [
                'url'     => $url,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'message' => 'خطا در ارتباط با فرانت: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, skipped?: bool, status?: int, message: string}
     */
    public function purgeAll(): array
    {
        return $this->purge([], [], true);
    }

    /**
     * @param  list<string>  $paths
     * @return array{ok: bool, skipped?: bool, status?: int, message: string}
     */
    public function purgePaths(array $paths): array
    {
        return $this->purge($paths, [], false);
    }

    /**
     * @param  list<string>  $tags
     * @return array{ok: bool, skipped?: bool, status?: int, message: string}
     */
    public function purgeTags(array $tags): array
    {
        return $this->purge([], $tags, false);
    }
}
