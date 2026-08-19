<?php

namespace App\Console\Commands;

use App\Models\AdsCallClickEvent;
use App\Services\Ads\Google\GoogleDataManagerService;
use Illuminate\Console\Command;

/**
 * بازرسیِ یک رویدادِ کلیک تماس — وضعیتِ ذخیره‌شده و (با --live) پاسخِ
 * خامِ requestStatus از خودِ Google. فقط خواندنی: هیچ conversionای ساخته
 * یا وضعیتی عوض نمی‌شود.
 *
 * برای وقتی که وضعیتِ رویدادها failed/processing مانده و باید دید Google
 * دقیقاً چه می‌گوید.
 */
class AdsGoogleInspectCommand extends Command
{
    protected $signature = 'ads:google-inspect
        {id : شناسهٔ ردیف (id) یا event_id}
        {--live : پرس‌وجوی زندهٔ requestStatus از گوگل (از مسیر پروکسی)}';

    protected $description = 'نمایش جزئیات تحویل یک کلیک تماس به Google (اختیاری: پاسخ زندهٔ requestStatus)';

    public function handle(): int
    {
        $key = (string) $this->argument('id');

        $event = ctype_digit($key)
            ? AdsCallClickEvent::find((int) $key)
            : AdsCallClickEvent::where('event_id', $key)->first();

        if (! $event) {
            $this->error('رویدادی با این شناسه پیدا نشد.');

            return self::FAILURE;
        }

        $this->line('');
        $this->table(['فیلد', 'مقدار'], [
            ['id', $event->id],
            ['event_id (transactionId)', $event->event_id],
            ['client_source', $event->client_source],
            ['event_time', (string) $event->event_time],
            ['gclid', GoogleDataManagerService::mask($event->gclid) ?? '—'],
            ['wbraid', GoogleDataManagerService::mask($event->wbraid) ?? '—'],
            ['gbraid', GoogleDataManagerService::mask($event->gbraid) ?? '—'],
            ['google_status', $event->google_status],
            ['google_attempts', (int) $event->google_attempts],
            ['google_request_id', $event->google_request_id ?? '—'],
            ['google_error_code', $event->google_error_code ?? '—'],
            ['google_last_attempt_at', (string) $event->google_last_attempt_at],
            ['google_last_status_checked_at', (string) $event->google_last_status_checked_at],
            ['google_next_retry_at', (string) $event->google_next_retry_at],
            ['google_uploaded_at', (string) $event->google_uploaded_at],
        ]);

        if (filled($event->google_error)) {
            $this->line('');
            $this->comment('متن کامل خطای ذخیره‌شده:');
            $this->line($event->google_error);
        }

        if (filled($event->google_response_meta)) {
            $this->line('');
            $this->comment('metadata پاسخ:');
            $this->line(json_encode($event->google_response_meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        if (! $this->option('live')) {
            $this->line('');
            $this->line('برای پرس‌وجوی زنده از گوگل: همین کامند با --live');
            $this->line('');

            return self::SUCCESS;
        }

        if (blank($event->google_request_id)) {
            $this->warn('این رویداد requestId ندارد (هنوز ارسال نشده یا ingest شکست خورده) — چیزی برای پرس‌وجو نیست.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->comment('پرس‌وجوی زندهٔ requestStatus از گوگل (از مسیر پروکسی)…');

        try {
            $result = GoogleDataManagerService::fromConfig()->requestStatus((string) $event->google_request_id);
        } catch (\Throwable $e) {
            $this->error('ناموفق: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['کلید', 'مقدار'], [
            ['status (برداشتِ ما)', $result['status']],
            ['errors', $result['errors'] ?? '—'],
            ['http_status', $result['meta']['http_status'] ?? '—'],
            ['کلیدهای بدنه', implode(', ', $result['meta']['body_keys'] ?? [])],
        ]);

        $this->line('');
        $this->line('اگر «status» برابر UNKNOWN است یعنی شکلِ پاسخ با چیزی که می‌خوانیم فرق دارد —');
        $this->line('کلیدهای بدنه را بفرستید تا نگاشت را اصلاح کنیم.');
        $this->line('');

        return self::SUCCESS;
    }
}
