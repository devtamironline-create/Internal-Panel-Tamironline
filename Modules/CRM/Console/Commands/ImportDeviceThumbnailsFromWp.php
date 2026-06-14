<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\CRM\Models\Device;
use Modules\Site\Support\WpImageDownloader;

/**
 * Import تصویر (thumbnail) دستگاه‌ها از API وردپرس قدیمی.
 *
 * منبع: GET {url} با خروجی { success, data: [ { name, image, is_active } ] }
 * (پیش‌فرض: https://crm.tamironline.com/wp-json/tamir/v1/services/categories)
 *
 * تطبیق بر اساس «نام فارسی» (با نرمال‌سازی ي/ك عربی و نیم‌فاصله) به crm_devices.
 * تصویر هر دستگاه دانلود و در site_media ذخیره می‌شود (dedup با hash) و سپس
 * thumbnail / thumbnail_media_id دستگاه ست می‌شود — دقیقاً مثل آپلود از پنل.
 *
 * Dry-run by default — برای اعمال واقعی --apply بزن:
 *   php artisan crm:import-device-thumbnails
 *   php artisan crm:import-device-thumbnails --apply
 *   php artisan crm:import-device-thumbnails --apply --force   (بازنویسی thumbnailهای موجود)
 *   php artisan crm:import-device-thumbnails --apply --url=https://...
 */
class ImportDeviceThumbnailsFromWp extends Command
{
    protected $signature = 'crm:import-device-thumbnails
        {--url=https://crm.tamironline.com/wp-json/tamir/v1/services/categories : آدرس API دسته‌بندی‌های وردپرس}
        {--apply : اعمال واقعی (پیش‌فرض dry-run)}
        {--force : بازنویسی دستگاه‌هایی که از قبل thumbnail دارند}';

    protected $description = 'دانلود تصاویر دستگاه‌ها از API وردپرس و ست‌کردن thumbnail در crm_devices';

    public function handle(WpImageDownloader $downloader): int
    {
        $url = (string) $this->option('url');
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        $this->info('دریافت لیست از: '.$url);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'TamirOnline-Thumbnail-Importer/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('خطا در اتصال به API: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('پاسخ نامعتبر از API (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $items = $response->json('data');
        if (! is_array($items) || $items === []) {
            $this->error('کلید data خالی یا نامعتبر است.');

            return self::FAILURE;
        }

        // نگاشت نام نرمال‌شده → دستگاه (در PHP می‌سازیم تا نرمال‌سازی یکدست باشد).
        $devicesByName = [];
        foreach (Device::query()->get(['id', 'name', 'thumbnail', 'thumbnail_media_id']) as $device) {
            $devicesByName[$this->normalize($device->name)] = $device;
        }

        $matched = $skipped = $imported = $failed = $unmatched = 0;
        $rows = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $image = trim((string) ($item['image'] ?? ''));

            if ($name === '' || $image === '') {
                continue;
            }

            $device = $devicesByName[$this->normalize($name)] ?? null;

            if (! $device) {
                $unmatched++;
                $rows[] = [$name, '—', 'بدون تطبیق', mb_strimwidth($image, 0, 50, '…')];

                continue;
            }

            $matched++;

            $hasThumb = trim((string) $device->thumbnail) !== '';
            if ($hasThumb && ! $force) {
                $skipped++;
                $rows[] = [$name, $device->name, 'thumbnail دارد (skip)', ''];

                continue;
            }

            if (! $apply) {
                $rows[] = [$name, $device->name, 'آماده برای import', mb_strimwidth($image, 0, 50, '…')];

                continue;
            }

            $media = $downloader->downloadAndStore($image);
            if (! $media) {
                $failed++;
                $rows[] = [$name, $device->name, 'دانلود ناموفق', mb_strimwidth($image, 0, 50, '…')];

                continue;
            }

            $device->forceFill([
                'thumbnail'          => $media->url(),
                'thumbnail_media_id' => $media->id,
            ])->save();
            $device->attachMedia((int) $media->id, 'thumbnail');

            $imported++;
            $rows[] = [$name, $device->name, 'ثبت شد (media #'.$media->id.')', ''];
        }

        $this->table(['نام WP', 'دستگاه پنل', 'وضعیت', 'تصویر'], $rows);

        $this->newLine();
        $this->info(sprintf(
            'تطبیق‌یافته: %d | %s: %d | skip: %d | ناموفق: %d | بدون تطبیق: %d',
            $matched,
            $apply ? 'ثبت‌شده' : 'قابل ثبت',
            $imported,
            $skipped,
            $failed,
            $unmatched
        ));

        if (! $apply) {
            $this->warn('این یک dry-run بود. برای اعمال واقعی --apply را اضافه کنید.');
        }

        return self::SUCCESS;
    }

    /**
     * نرمال‌سازی نام فارسی برای تطبیق: یکسان‌سازی ي/ك عربی، حذف نیم‌فاصله،
     * فشرده‌سازی فاصله‌ها و حذف فاصله‌های ابتدا/انتها.
     */
    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\u{064A}", "\u{0643}", "\u{200C}"], ["\u{06CC}", "\u{06A9}", ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim(mb_strtolower($value));
    }
}
