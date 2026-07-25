<?php

namespace Modules\CustomerApp\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کانالِ نوتیفیکیشنِ «بله» — همان نوتیفیکیشن‌های اپِ مشتری را از طریقِ رباتِ
 * بله (@tamironlinebot) به چتِ کاربر می‌فرستد.
 *
 * شرطِ ارسال: مشتری bale_user_id داشته باشد (بعد از اولین ورود از مینی‌اپِ
 * بله ست می‌شود) و BALE_BOT_TOKEN پیکربندی شده باشد. API بله سازگار با
 * تلگرام است: POST {base}/bot{token}/sendMessage {chat_id, text}.
 *
 * ارسال بعد از پاسخِ HTTP انجام می‌شود (afterResponse) تا تأخیر/خرابیِ شبکهٔ
 * بله هرگز ثبتِ سفارش یا تغییرِ وضعیت را کند/خراب نکند؛ خطاها فقط لاگ می‌شوند.
 */
class BaleChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toBale')) {
            return;
        }

        $chatId = (int) ($notifiable->bale_user_id ?? 0);
        $token = (string) config('services.bale.bot_token');
        if ($chatId <= 0 || $token === '') {
            return;
        }

        $text = (string) $notification->toBale($notifiable);
        if (trim($text) === '') {
            return;
        }

        $base = rtrim((string) config('services.bale.api_base', 'https://tapi.bale.ai'), '/');
        $url = $base.'/bot'.$token.'/sendMessage';

        dispatch(function () use ($url, $chatId, $text) {
            try {
                $resp = Http::connectTimeout(3)->timeout(5)->post($url, [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
                if (! $resp->successful()) {
                    Log::warning('customer_app.bale_notify_failed', [
                        'chat_id' => $chatId,
                        'status' => $resp->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('customer_app.bale_notify_failed', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
