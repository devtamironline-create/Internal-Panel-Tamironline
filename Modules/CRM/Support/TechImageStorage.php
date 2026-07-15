<?php

namespace Modules\CRM\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ذخیره‌سازیِ فایل‌های آپلودیِ پنل/اپِ تکنسین با «بهینه‌سازیِ سخت».
 *
 * سیاست:
 *   - ورودی تا ۳۰ مگابایت پذیرفته می‌شود (اعتبارسنجیِ حجم در خودِ کنترلر با max:30720).
 *   - تصویر به‌صورتِ سخت بهینه می‌شود: downscale تا عرضِ حداکثرِ MAX_WIDTH و
 *     تبدیل به WebP با کیفیتِ WEBP_QUALITY. این سیاست ثابت است و به تنظیماتِ
 *     مخزنِ مدیای سایت وابسته نیست (تکنسین همیشه فشرده‌سازیِ سخت می‌گیرد).
 *   - **فایلِ اصلیِ تکنسین هرگز ذخیره نمی‌شود**؛ فقط نسخهٔ بهینه‌شده می‌ماند.
 *   - اگر فرمت قابلِ پردازش نبود (gif/svg/...) یا بهینه‌سازی به هر دلیل شکست خورد،
 *     فایلِ اصلی ذخیره می‌شود تا آپلود (مثلاً عکسِ اجباریِ بستنِ سفارش) از دست نرود.
 *     بهینه‌سازی هرگز نباید خودِ آپلود را بشکند.
 */
final class TechImageStorage
{
    /** بیشینه‌ی عرضِ خروجی به پیکسل — بزرگ‌تر downscale می‌شود. */
    public const MAX_WIDTH = 1600;

    /** کیفیتِ WebP خروجی (۰..۱۰۰). */
    public const WEBP_QUALITY = 72;

    /** سقفِ ابعادِ ورودی برای بازکردن با GD (محافظِ حافظه). */
    private const MAX_PIXELS = 30_000_000;

    /** mimeهایی که GD می‌تواند باز کند و تبدیل/resize امن است. */
    private const PROCESSABLE_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    /**
     * ذخیرهٔ فایل با بهینه‌سازیِ سخت. خروجی: مسیرِ نسبیِ فایلِ ذخیره‌شده روی دیسک.
     */
    public static function store(UploadedFile $file, string $dir, string $disk = 'public'): string
    {
        $dir = trim($dir, '/');

        $optimized = self::optimize($file);
        if ($optimized !== null) {
            $path = $dir.'/'.self::filename($file, $optimized['extension']);
            $stored = false;
            $stream = @fopen($optimized['path'], 'rb');
            try {
                if ($stream) {
                    $stored = (bool) Storage::disk($disk)->put($path, $stream);
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                @unlink($optimized['path']); // نسخهٔ موقتِ بهینه پاک می‌شود
            }

            if ($stored) {
                @chmod(Storage::disk($disk)->path($path), 0644);

                return $path;
            }
        }

        // fallback: بهینه‌سازی ممکن نشد → همان فایلِ اصلی ذخیره می‌شود.
        return $file->store($dir, $disk);
    }

    /**
     * بهینه‌سازیِ سخت با GD. خروجی مسیرِ فایلِ موقتِ بهینه‌شده یا null (یعنی
     * «قابلِ بهینه‌سازی نبود»). هر خطا به null ختم می‌شود.
     *
     * @return array{path: string, extension: string, mime: string, size: int}|null
     */
    private static function optimize(UploadedFile $file): ?array
    {
        try {
            return self::doOptimize($file);
        } catch (\Throwable $e) {
            Log::warning('tech_image_storage.optimize_failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{path: string, extension: string, mime: string, size: int}|null
     */
    private static function doOptimize(UploadedFile $file): ?array
    {
        if (! function_exists('imagewebp')) {
            return null; // بدونِ پشتیبانیِ WebP، بهینه‌سازیِ سخت ممکن نیست
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! in_array($mime, self::PROCESSABLE_MIMES, true)) {
            return null; // gif/svg/avif/... — دست‌نخورده
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return null;
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            return null;
        }

        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };
        if (! $src) {
            return null;
        }

        // WebP فقط روی تصویرِ truecolor کار می‌کند (PNGِ palette را تبدیل می‌کنیم).
        if (! imageistruecolor($src) && ! @imagepalettetotruecolor($src)) {
            imagedestroy($src);

            return null;
        }

        // downscale تا عرضِ حداکثر
        if ($width > self::MAX_WIDTH) {
            $newWidth = self::MAX_WIDTH;
            $newHeight = max(1, (int) round($height * (self::MAX_WIDTH / $width)));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $resized;
        }

        imagealphablending($src, false);
        imagesavealpha($src, true);

        $tmp = tempnam(sys_get_temp_dir(), 'techimg_');
        $written = @imagewebp($src, $tmp, self::WEBP_QUALITY);
        imagedestroy($src);

        $size = (int) (@filesize($tmp) ?: 0);
        if ($written === false || $size <= 0) {
            @unlink($tmp);

            return null;
        }

        return ['path' => $tmp, 'extension' => 'webp', 'mime' => 'image/webp', 'size' => $size];
    }

    /** نامِ فایلِ تصادفی با پسوندِ خروجیِ بهینه‌شده. */
    private static function filename(UploadedFile $file, string $ext): string
    {
        $base = pathinfo($file->hashName(), PATHINFO_FILENAME); // ۴۰ کاراکترِ تصادفی

        return $base.'.'.$ext;
    }
}
