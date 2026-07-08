<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoSetting;

/**
 * ارسال هشدار پس از کرال (یا رویدادهای بحرانی). کانال‌ها از تنظیمات
 * خوانده می‌شوند: وبهوک (Slack/Telegram/سفارشی) و ایمیل. اگر هیچ کانالی
 * تنظیم نشده باشد، فقط در لاگ ثبت می‌شود.
 */
class AlertNotifier
{
    public function afterRun(SeoAuditRun $run): void
    {
        $missing = (int) ($run->missing_in_sitemap_count ?? 0);
        $stale = (int) ($run->stale_in_sitemap_count ?? 0);

        if ($run->critical_count <= 0 && $run->warning_count <= 0 && $missing <= 0 && $stale <= 0) {
            return; // چیزی برای هشدار نیست
        }

        $message = sprintf(
            'گزارش کرال سئو: %d بحرانی، %d هشدار، %d توجه از %d صفحه (میانگین امتیاز %d).',
            $run->critical_count,
            $run->warning_count,
            $run->notice_count,
            $run->total_urls,
            $run->avg_score
        );
        if ($missing > 0 || $stale > 0) {
            $message .= sprintf(' پوششِ sitemap: %d صفحهٔ جاافتاده از sitemap، %d آدرسِ منسوخ در sitemap.', $missing, $stale);
        }

        $this->send($message, $run);
    }

    public function send(string $message, ?SeoAuditRun $run = null): void
    {
        $sent = false;

        if ($webhook = trim((string) SeoSetting::get('alert_webhook'))) {
            $sent = $this->webhook($webhook, $message) || $sent;
        }

        if ($email = trim((string) SeoSetting::get('alert_email'))) {
            $sent = $this->email($email, $message) || $sent;
        }

        if (! $sent) {
            Log::channel(config('logging.default'))->warning('[SEO alert] '.$message);
        }
    }

    private function webhook(string $url, string $message): bool
    {
        try {
            // فرمت سازگار با Slack/تلگرام-بات‌سازها و وبهوک‌های ساده.
            Http::timeout(15)->post($url, ['text' => $message, 'message' => $message]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[SEO alert] webhook failed: '.$e->getMessage());

            return false;
        }
    }

    private function email(string $to, string $message): bool
    {
        try {
            Mail::raw($message, function ($m) use ($to) {
                $m->to($to)->subject('هشدار سئو — گزارش کرال');
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('[SEO alert] email failed: '.$e->getMessage());

            return false;
        }
    }
}
