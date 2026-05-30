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
     */
    public static function store(UploadedFile $file, ?int $userId = null): Media
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if ($existing = Media::where('hash', $hash)->first()) {
            return $existing;   // dedup — همان فایل قبلاً آپلود شده
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $kind = self::kindFromMime($mime);

        $dir = 'site/media/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
        $filename = $hash.'.'.$ext;
        $path = $dir.'/'.$filename;

        Storage::disk('public')->putFileAs($dir, $file, $filename);

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
            'uploaded_by_user_id' => $userId,
        ]);

        // تولید variants فقط برای تصاویر
        if ($kind === 'image' && $width !== null) {
            self::buildVariants($media);
        }

        return $media;
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
        foreach ($variants as $key => $info) {
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
