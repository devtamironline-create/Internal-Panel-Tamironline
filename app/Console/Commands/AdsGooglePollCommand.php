<?php

namespace App\Console\Commands;

use App\Services\Ads\Google\GoogleCallConversionUploader;
use Illuminate\Console\Command;

/**
 * پیگیری وضعیت requestهای در حال پردازش در Google Data Manager
 * (requestStatus:retrieve — از مسیر پروکسی).
 */
class AdsGooglePollCommand extends Command
{
    protected $signature = 'ads:google-poll {--limit=50 : حداکثر event در هر اجرا}';

    protected $description = 'بررسی وضعیت آپلودهای در حال پردازش Google Data Manager';

    public function handle(): int
    {
        if (! config('ads_tracking.google.upload_enabled')) {
            $this->line('Google upload خاموش است — کاری انجام نشد.');

            return self::SUCCESS;
        }

        $stats = GoogleCallConversionUploader::fromConfig()->pollProcessing((int) $this->option('limit'));

        $this->info(sprintf(
            'checked=%d uploaded=%d still_processing=%d retried=%d failed=%d',
            $stats['checked'], $stats['uploaded'], $stats['still_processing'], $stats['retried'], $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
