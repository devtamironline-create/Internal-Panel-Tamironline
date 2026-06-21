<?php

namespace Modules\Site\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Site\Models\Media\Media;
use Modules\Site\Models\Media\MediaVariant;

/**
 * مرکز upload فایل + dedup با hash + ساخت variants.
 *
 * مسیر ذخیره: site/media/{hashPrefix1}/{hashPrefix2}/{hash}.{ext}
 * (sharding بر اساس ۲ کاراکتر اول و دوم hash برای جلوگیری از فولدر هزاران فایل)
 */
final class MediaStorage
{
    /**
     * upload یک فایل، اگر hash آن قبلاً موجود باشد همان رکورد را برمی‌گرداند (dedup).
     *
     * @param  string  $visibility  'public'|'private'
     */
    public static function store(UploadedFile $file, ?int $userId = null, string $visibility = 'public'): Media
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if ($existing = Media::where('hash', $hash)->first()) {
            // dedup — همان فایل قبلاً آپلود شده. ولی اگر فایلِ فیزیکی پاک شده
            // باشد (پاک‌سازیِ دستیِ مخزن)، همین‌جا روی همان مسیر بازیابی‌اش
            // می‌کنیم تا URLهای موجود (مقالات/برندها) بدونِ تغییر دوباره کار کنند.
            self::restoreMissingFile($existing, $file);

            return $existing;   // dedup — همان فایل قبلاً آپلود شده
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $kind = self::kindFromMime($mime);

        $dir = 'site/media/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
        $filename = $hash.'.'.$ext;
        $path = $dir.'/'.$filename;

        Storage::disk('public')->putFileAs($dir, $file, $filename);
        // chmod 0644 تا سرور بتواند بخواند (UMASK پیش‌فرض ممکن است 0600 بدهد)
        @chmod(Storage::disk('public')->path($path), 0644);

        $width = $height = null;
        $aspect = null;
        if ($kind === 'image') {
            $absolute = Storage::disk('public')->path($path);
            $info = ImageProcessor::probe($absolute);
            if ($info) {
                $width = $info['width'];
                $height = $info['height'];
                $aspect = ImageProcessor::aspectRatio($width, $height);
            }
        }

        $media = Media::create([
            'hash' => $hash,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $mime,
            'extension' => $ext,
            'size_bytes' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $aspect,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'kind' => $kind,
            'visibility' => in_array($visibility, ['public', 'private'], true) ? $visibility : 'public',
            'uploaded_by_user_id' => $userId,
        ]);

        // تولید variants فقط برای تصاویر — شکستِ ساختِ variant نباید کلِ آپلود
        // را با ۵۰۰ از کار بیندازد؛ فایلِ اصلی ذخیره شده و variantها بعداً
        // قابل بازسازی‌اند (rebuild-variants).
        if ($kind === 'image' && $width !== null) {
            try {
                self::buildVariants($media);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('media.build_variants_failed', [
                    'media_id' => $media->id,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $media;
    }

    /**
     * بازیابیِ فایلِ فیزیکیِ یک Media که رکوردش در DB هست ولی فایلش روی disk
     * پاک شده. مسیر/نام از خودِ رکورد (یا بازساخت از hash) می‌آید تا URLهای
     * موجود دست‌نخورده بمانند. اگر فایل سرجایش باشد، کاری نمی‌کند.
     *
     * @return bool true اگر بازیابی انجام شد
     */
    public static function restoreMissingFile(Media $media, UploadedFile $file): bool
    {
        $disk = Storage::disk('public');

        // مسیرِ مرجع: path موجود، وگرنه بازساخت از hash+extension (هم‌منطق با store).
        $ext = $media->extension ?: strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $path = $media->path
            ?: 'site/media/'.substr($media->hash, 0, 2).'/'.substr($media->hash, 2, 2).'/'.$media->hash.'.'.$ext;

        if ($disk->exists($path)) {
            return false;   // فایل سرجایش است — نیازی به بازیابی نیست
        }

        $disk->putFileAs(dirname($path), $file, basename($path));
        @chmod($disk->path($path), 0644);

        // اگر path روی رکورد خالی بود، تثبیتش کن.
        if (empty($media->path)) {
            $media->forceFill(['path' => $path])->save();
        }

        // variantها هم با فایل اصلی پاک شده‌اند → بازسازی (شکست نباید throw کند).
        if ($media->kind === 'image') {
            try {
                self::buildVariants($media);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('media.build_variants_failed', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * ساخت/بازسازی variants یک media.
     */
    public static function buildVariants(Media $media): void
    {
        if ($media->kind !== 'image') {
            return;
        }
        $variantsDir = dirname($media->path).'/variants';
        $baseFilename = pathinfo($media->path, PATHINFO_FILENAME);

        $variants = ImageProcessor::generateVariants($media->path, $variantsDir, $baseFilename);

        // پاک‌سازی variants قبلی و ساخت دوباره
        MediaVariant::where('media_id', $media->id)->delete();
        $disk = Storage::disk('public');
        foreach ($variants as $key => $info) {
            @chmod($disk->path($info['path']), 0644);
            MediaVariant::create([
                'media_id' => $media->id,
                'variant' => $key,
                'path' => $info['path'],
                'width' => $info['width'],
                'height' => $info['height'],
                'size_bytes' => $info['size_bytes'],
                'mime' => $info['mime'],
            ]);
        }
    }

    /**
     * حذف فایل اصلی + variants از disk و رکورد از DB.
     */
    public static function delete(Media $media): void
    {
        $disk = Storage::disk('public');
        foreach ($media->variants as $v) {
            $disk->delete($v->path);
        }
        $disk->delete($media->path);
        $media->delete();
    }

    private static function kindFromMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (in_array($mime, ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            return 'document';
        }

        return 'other';
    }
}
