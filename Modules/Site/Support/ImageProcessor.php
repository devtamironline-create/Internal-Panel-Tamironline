<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Storage;

/**
 * پردازش تصویر با GD native (بدون dependency خارجی).
 *
 * قابلیت‌ها:
 *   - استخراج dimensions و metadata
 *   - تولید variants با resize نسبت‌حفظ‌شده
 *   - پشتیبانی JPG/PNG/WebP/GIF
 */
final class ImageProcessor
{
    /** ابعاد هر variant (max width). ارتفاع متناسب نگه‌داری می‌شود. */
    public const VARIANTS = [
        'thumb' => 150,
        'small' => 400,
        'medium' => 800,
        'large' => 1600,
    ];

    public const QUALITY_JPEG = 82;

    public const QUALITY_WEBP = 80;

    public const QUALITY_PNG = 7;   // 0=بهترین تا 9=کوچک‌ترین

    /**
     * @return array{width:int, height:int, mime:string, kind:string}|null
     */
    public static function probe(string $absolutePath): ?array
    {
        if (! file_exists($absolutePath)) {
            return null;
        }
        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'mime' => (string) $info['mime'],
            'kind' => 'image',
        ];
    }

    /**
     * تولید همه‌ی variantها برای یک فایل و ذخیره روی public disk.
     * هر variant فقط در صورتی ساخته می‌شود که عرض اصلی > maxWidth باشد.
     *
     * @param  string  $originalRelativePath  مسیر فایل اصلی نسبت به root disk public
     * @param  string  $variantsBaseDir  پوشه‌ی هدف برای variants (مثل 'site/media/ab/cd/abcdef/variants')
     * @return array<string, array{path:string, width:int, height:int, size_bytes:int, mime:string}>
     */
    public static function generateVariants(string $originalRelativePath, string $variantsBaseDir, string $baseFilename): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($originalRelativePath)) {
            return [];
        }
        $absolute = $disk->path($originalRelativePath);
        $info = self::probe($absolute);
        if (! $info) {
            return [];
        }

        $src = self::createImage($absolute, $info['mime']);
        if (! $src) {
            return [];
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);
        $out = [];

        foreach (self::VARIANTS as $key => $maxWidth) {
            if ($srcWidth <= $maxWidth) {
                continue;
            }
            $newWidth = $maxWidth;
            $newHeight = (int) round($srcHeight * ($maxWidth / $srcWidth));

            $variantsBaseDirClean = trim($variantsBaseDir, '/');
            $variantPath = $variantsBaseDirClean.'/'.$baseFilename.'-'.$key.self::extensionFor($info['mime']);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // حفظ آلفا برای PNG/WebP
            if ($info['mime'] === 'image/png' || $info['mime'] === 'image/webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

            $tmp = tempnam(sys_get_temp_dir(), 'mv_');
            self::writeImage($resized, $tmp, $info['mime']);
            imagedestroy($resized);

            $disk->put($variantPath, file_get_contents($tmp));
            $size = filesize($tmp);
            @unlink($tmp);

            $out[$key] = [
                'path' => $variantPath,
                'width' => $newWidth,
                'height' => $newHeight,
                'size_bytes' => (int) $size,
                'mime' => $info['mime'],
            ];
        }

        imagedestroy($src);

        return $out;
    }

    /**
     * @return \GdImage|false
     */
    private static function createImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
    }

    private static function writeImage(\GdImage $img, string $path, string $mime): void
    {
        match ($mime) {
            'image/jpeg', 'image/jpg' => imagejpeg($img, $path, self::QUALITY_JPEG),
            'image/png' => imagepng($img, $path, self::QUALITY_PNG),
            'image/webp' => function_exists('imagewebp') ? imagewebp($img, $path, self::QUALITY_WEBP) : null,
            'image/gif' => imagegif($img, $path),
            default => null,
        };
    }

    private static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/gif' => '.gif',
            default => '.bin',
        };
    }

    public static function aspectRatio(int $w, int $h): string
    {
        if ($w <= 0 || $h <= 0) {
            return '';
        }
        $g = self::gcd($w, $h);

        return ($w / $g).':'.($h / $g);
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
