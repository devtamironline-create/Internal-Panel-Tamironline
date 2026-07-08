<?php

namespace Modules\Site\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Site\Models\SiteSetting;

/**
 * بهینه‌سازی و سخت‌گیریِ آپلودِ تصویر در مخزنِ مدیا — فقط برای آپلودهای «جدید»؛
 * فایل‌های موجودِ مخزن هرگز دست نمی‌خورند.
 *
 * تنظیمات (پنل → مخزن مدیا → تنظیماتِ بهینه‌سازی):
 *   media_image_allowed_types : csv پسوندهای مجازِ ورودی (خالی = همه). مثلاً «webp» یعنی فقط webp.
 *   media_image_convert_webp  : '1' → تبدیلِ خودکارِ jpg/png به WebP (کیفیت ۸۰).
 *   media_image_max_width     : بیشینه‌ی عرض به پیکسل (۰ = بدونِ محدودیت) — بزرگ‌تر downscale می‌شود.
 *   media_image_max_kb        : بیشینه‌ی حجمِ «نهایی» به کیلوبایت (۰ = بدونِ محدودیت) —
 *                               پس از بهینه‌سازی اعمال می‌شود؛ اگر باز بزرگ‌تر بود آپلود رد می‌شود.
 *
 * GIF (به‌خاطرِ انیمیشن) و SVG (غیرِ قابلِ پردازش با GD) تبدیل/resize نمی‌شوند؛
 * فقط قواعدِ نوع و حجم رویشان اعمال می‌شود.
 */
final class ImageOptimizer
{
    /** پسوندهای تصویری که این کلاس می‌شناسد (برای whitelist ورودی). */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

    /** mimeهایی که GD می‌تواند باز کند و تبدیل/resize امن است. */
    private const PROCESSABLE_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    public static function allowedTypes(): array
    {
        $raw = strtolower(trim((string) SiteSetting::get('media_image_allowed_types', '')));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($t) => trim($t, " .\t"),
            preg_split('/[,،\s]+/u', $raw) ?: []
        )));
    }

    public static function convertToWebp(): bool
    {
        return (string) SiteSetting::get('media_image_convert_webp', '1') === '1';
    }

    public static function maxWidth(): int
    {
        return max(0, (int) SiteSetting::get('media_image_max_width', '1920'));
    }

    public static function maxKb(): int
    {
        return max(0, (int) SiteSetting::get('media_image_max_kb', '0'));
    }

    /**
     * اعتبارسنجیِ نوعِ ورودی — فقط برای فایل‌های تصویری صدا زده شود.
     *
     * @throws ValidationException
     */
    public static function validateType(UploadedFile $file): void
    {
        $allowed = self::allowedTypes();
        if ($allowed === []) {
            return;
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $normalized = array_map(fn ($t) => $t === 'jpeg' ? 'jpg' : $t, $allowed);
        if (! in_array($ext, $normalized, true)) {
            throw ValidationException::withMessages([
                'file' => 'فقط آپلودِ تصویر با فرمتِ «'.implode('، ', $allowed).'» مجاز است (فایلِ شما: '.$ext.').',
            ]);
        }
    }

    /**
     * بهینه‌سازی: downscale تا سقفِ عرض + تبدیل به WebP طبقِ تنظیمات.
     * خروجی null یعنی «فایلِ اصلی همان‌طور ذخیره شود» — هر خطای پردازش هم به
     * همین null ختم می‌شود؛ بهینه‌سازی هرگز نباید خودِ آپلود را بشکند.
     *
     * @return array{path: string, extension: string, mime: string, size: int}|null
     */
    public static function optimize(UploadedFile $file): ?array
    {
        try {
            return self::doOptimize($file);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('image_optimizer.failed', [
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
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! in_array($mime, self::PROCESSABLE_MIMES, true)) {
            return null; // gif/svg/avif/... — دست‌نخورده
        }

        $originalSize = (int) ($file->getSize() ?: 0);
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return null;
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];

        // محافظِ حافظه‌ی GD — تصویرِ عظیم را باز نمی‌کنیم (همان سقفِ variantها).
        if (($width * $height) > ImageProcessor::MAX_VARIANT_PIXELS) {
            return null;
        }

        $maxWidth = self::maxWidth();
        $needResize = $maxWidth > 0 && $width > $maxWidth;
        $needConvert = self::convertToWebp() && $mime !== 'image/webp' && function_exists('imagewebp');

        if (! $needResize && ! $needConvert) {
            return null;
        }

        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };
        if (! $src) {
            return null; // فایلِ خراب/غیرقابل‌خواندن — همان اصل ذخیره می‌شود
        }

        // PNGهای indexed (palette) با imagewebp پشتیبانی نمی‌شوند →
        // «imagewebp(): Palette image not supported» و شکستِ کلِ آپلود.
        if (! imageistruecolor($src) && ! @imagepalettetotruecolor($src)) {
            imagedestroy($src);

            return null;
        }

        if ($needResize) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // حفظِ شفافیت برای png/webp
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $resized;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'imgopt_');
        if ($needConvert) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $written = @imagewebp($src, $tmp, ImageProcessor::QUALITY_WEBP);
            $ext = 'webp';
            $outMime = 'image/webp';
        } else {
            // فقط resize — با همان فرمتِ اصلی ذخیره می‌شود
            $written = match ($mime) {
                'image/jpeg', 'image/jpg' => @imagejpeg($src, $tmp, ImageProcessor::QUALITY_JPEG),
                'image/png' => @imagepng($src, $tmp, ImageProcessor::QUALITY_PNG),
                'image/webp' => @imagewebp($src, $tmp, ImageProcessor::QUALITY_WEBP),
                default => false,
            };
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $outMime = $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        }
        imagedestroy($src);

        $size = (int) (@filesize($tmp) ?: 0);
        if ($written === false || $size <= 0) {
            @unlink($tmp);

            return null;
        }

        // اگر فقط تبدیل بود و خروجی از اصل بزرگ‌تر شد (مثلاً گرافیکِ سادهٔ PNG)،
        // همان اصل بهینه‌تر است — برای سئو فایلِ کوچک‌تر می‌بَرد.
        if (! $needResize && $originalSize > 0 && $size >= $originalSize) {
            @unlink($tmp);

            return null;
        }

        return ['path' => $tmp, 'extension' => $ext, 'mime' => $outMime, 'size' => $size];
    }

    /**
     * سقفِ حجمِ نهایی (پس از بهینه‌سازی) — برای سئو.
     *
     * @throws ValidationException
     */
    public static function enforceFinalSize(int $bytes): void
    {
        $maxKb = self::maxKb();
        if ($maxKb > 0 && $bytes > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => 'حجمِ تصویر پس از بهینه‌سازی '.number_format((int) round($bytes / 1024)).' کیلوبایت است؛ بیشینه‌ی مجاز '.number_format($maxKb).' کیلوبایت (سئو). تصویرِ کوچک‌تر/فشرده‌تری آپلود کنید.',
            ]);
        }
    }

    /** آیا فایل، تصویر است؟ (بر اساسِ mime) */
    public static function isImage(UploadedFile $file): bool
    {
        return str_starts_with(strtolower((string) ($file->getMimeType() ?: '')), 'image/');
    }
}
