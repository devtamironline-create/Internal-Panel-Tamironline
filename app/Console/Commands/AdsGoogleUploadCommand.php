<?php

namespace App\Console\Commands;

use App\Services\Ads\Google\GoogleCallConversionUploader;
use Illuminate\Console\Command;

/**
 * ارسال Call Click های pending به Google Data Manager.
 *
 * از scheduler اجرا می‌شود (صفِ پروژه sync است). تا وقتی
 * ADS_TRACKING_GOOGLE_UPLOAD=false باشد کاری نمی‌کند — ثبتِ رویدادها
 * مستقل از این کامند همیشه فعال است.
 */
class AdsGoogleUploadCommand extends Command
{
    protected $signature = 'ads:google-upload {--limit=50 : حداکثر event در هر اجرا}';

    protected $description = 'آپلود Conversionهای تماس به Google Data Manager (از مسیر پروکسی)';

    public function handle(): int
    {
        if (! config('ads_tracking.google.upload_enabled')) {
            $this->line('Google upload خاموش است (ADS_TRACKING_GOOGLE_UPLOAD=false) — کاری انجام نشد.');

            return self::SUCCESS;
        }

        $stats = GoogleCallConversionUploader::fromConfig()->uploadPending((int) $this->option('limit'));

        $this->info(sprintf(
            'claimed=%d processing=%d validated=%d retried=%d failed=%d',
            $stats['claimed'], $stats['processing'], $stats['validated'], $stats['retried'], $stats['failed'],
        ));

        if (config('ads_tracking.google.validate_only') && $stats['validated'] > 0) {
            $this->warn('حالت validate_only روشن است — هیچ conversion واقعی ساخته نشد.');
        }

        return self::SUCCESS;
    }
}
