<?php

namespace Modules\Site\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Site\Models\Media\Media;

/**
 * دانلود تصاویر WordPress و ذخیره در site_media (با dedup بر اساس hash).
 *
 * Idempotent: اگر همان URL یا hash قبلاً ذخیره شده باشد، همان Media برمی‌گردد.
 * با retry سه‌بار + backoff تا شکست‌های شبکه‌ای موقتی را تحمل کند.
 *
 * مصرف توسط WpArticleImporter: cover و inline images درون content.
 */
final class WpImageDownloader
{
    private const MAX_RETRIES = 3;

    private const BACKOFF_MS = [500, 1500, 4000];

    private const MAX_BYTES = 20 * 1024 * 1024; // 20MB sanity cap

    /** Cache در حافظه برای جلوگیری از دانلود مکرر در یک run. */
    private array $cache = [];

    /**
     * @return Media|null null اگر دانلود ناموفق بود (logged)
     */
    public function downloadAndStore(string $url): ?Media
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        // اگر hash این URL قبلاً ذخیره شده (از run قبلی)، نیاز به دانلود نیست —
        // ولی hash را فقط بعد از دانلود می‌دانیم. به‌جایش از filename guess می‌کنیم:
        // اگر filename دقیقاً در DB موجود است، همان را برگردان. این یک shortcut
        // اختیاری است برای جلوگیری از دانلود تکراری بین runها.
        $guessName = basename(parse_url($url, PHP_URL_PATH) ?? '');
        if ($guessName !== '') {
            $existing = Media::where('filename', $guessName)->orderByDesc('id')->first();
            if ($existing) {
                $this->cache[$url] = $existing;

                return $existing;
            }
        }

        $tmpPath = $this->downloadWithRetry($url);
        if ($tmpPath === null) {
            return null;
        }

        try {
            $originalName = $guessName !== '' ? $guessName : 'download.bin';
            $mime = $this->detectMime($tmpPath, $originalName);
            // UploadedFile با test=true تا check_uploaded_file را bypass کند.
            $uploaded = new UploadedFile($tmpPath, $originalName, $mime, null, true);
            $media = MediaStorage::store($uploaded);
            $this->cache[$url] = $media;

            return $media;
        } catch (\Throwable $e) {
            Log::warning('wp_image_downloader.store_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($tmpPath);
        }
    }

    private function downloadWithRetry(string $url): ?string
    {
        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            try {
                $response = Http::timeout(30)
                    ->withOptions(['stream' => false])
                    ->withHeaders(['User-Agent' => 'TamirOnline-WP-Importer/1.0'])
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('wp_image_downloader.http_status', [
                        'url' => $url, 'status' => $response->status(), 'attempt' => $i + 1,
                    ]);
                    $this->sleepBackoff($i);

                    continue;
                }

                $body = $response->body();
                $size = strlen($body);
                if ($size === 0 || $size > self::MAX_BYTES) {
                    Log::warning('wp_image_downloader.size_invalid', [
                        'url' => $url, 'size' => $size,
                    ]);

                    return null;
                }

                $tmpPath = tempnam(sys_get_temp_dir(), 'wpimg_');
                if ($tmpPath === false) {
                    return null;
                }
                file_put_contents($tmpPath, $body);

                return $tmpPath;
            } catch (\Throwable $e) {
                Log::warning('wp_image_downloader.exception', [
                    'url' => $url, 'error' => $e->getMessage(), 'attempt' => $i + 1,
                ]);
                $this->sleepBackoff($i);
            }
        }

        return null;
    }

    private function sleepBackoff(int $attempt): void
    {
        $ms = self::BACKOFF_MS[$attempt] ?? 5000;
        usleep($ms * 1000);
    }

    private function detectMime(string $path, string $originalName): string
    {
        $mime = @mime_content_type($path);
        if ($mime !== false && is_string($mime)) {
            return $mime;
        }

        // fallback از روی پسوند
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
