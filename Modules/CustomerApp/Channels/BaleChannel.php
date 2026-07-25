<?php

namespace Modules\CustomerApp\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کانالِ نوتیفیکیشنِ «بله» — همان نوتیفیکیشن‌های اپِ مشتری را به بله می‌فرستد.
 *
 * دو مسیر (به‌ترتیبِ اولویت):
 *   ۱) چتِ ربات (رایگان): اگر مشتری bale_user_id دارد (بعد از ورود/اتصالِ
 *      مینی‌اپ) → POST {api_base}/bot{token}/sendMessage
 *   ۲) سفیرِ بله (ارسال با شمارهٔ موبایل — سرویسِ تجاری): اگر bale_user_id
 *      ندارد ولی موبایل دارد و سفیر پیکربندی شده →
 *      POST {safir_base}/api/v3/send_message با هدرِ api-access-key
 *      (مستندات: docs.bale.ai/safir)
 *
 * ارسال بعد از پاسخِ HTTP انجام می‌شود (afterResponse) تا تأخیر/خرابیِ شبکهٔ
 * بله هرگز ثبتِ سفارش یا تغییرِ وضعیت را کند/خراب نکند؛ خطاها فقط لاگ می‌شوند.
 */
class BaleChannel
{
    /** آیا برای این مشتری راهی برای ارسالِ بله وجود دارد؟ (برای via() نوتیفیکیشن‌ها) */
    public static function configuredFor(object $notifiable): bool
    {
        $viaBot = ! empty($notifiable->bale_user_id)
            && (string) config('services.bale.bot_token') !== '';

        $viaSafir = ! empty($notifiable->mobile)
            && (string) config('services.bale.safir_access_key') !== ''
            && (string) config('services.bale.safir_bot_id') !== '';

        return $viaBot || $viaSafir;
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toBale')) {
            return;
        }

        $text = (string) $notification->toBale($notifiable);
        if (trim($text) === '') {
            return;
        }

        // مسیرِ ۱ — چتِ ربات با bale_user_id
        $chatId = (int) ($notifiable->bale_user_id ?? 0);
        $botToken = (string) config('services.bale.bot_token');
        if ($chatId > 0 && $botToken !== '') {
            $base = rtrim((string) config('services.bale.api_base', 'https://tapi.bale.ai'), '/');
            $this->deliver($base.'/bot'.$botToken.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ], [], 'bot:'.$chatId);

            return;
        }

        // مسیرِ ۲ — سفیر: ارسال با شمارهٔ موبایل (بدونِ نیاز به bale_user_id)
        $accessKey = (string) config('services.bale.safir_access_key');
        $safirBotId = (string) config('services.bale.safir_bot_id');
        $phone = self::intlPhone((string) ($notifiable->mobile ?? ''));
        if ($accessKey !== '' && $safirBotId !== '' && $phone !== null) {
            $base = rtrim((string) config('services.bale.safir_base', 'https://safir.bale.ai'), '/');
            // قراردادِ تأییدشده با curl واقعی: bot_id عددی و message_data.message.text
            $this->deliver($base.'/api/v3/send_message', [
                'bot_id' => is_numeric($safirBotId) ? (int) $safirBotId : $safirBotId,
                'phone_number' => $phone,
                'message_data' => ['message' => ['text' => $text]],
            ], ['api-access-key' => $accessKey], 'safir:'.$phone);
        }
    }

    /** 09121112233 → 989121112233 (فرمتِ موردِ انتظارِ سفیر) یا null اگر نامعتبر. */
    public static function intlPhone(string $mobile): ?string
    {
        $m = preg_replace('/\D/', '', $mobile) ?? '';
        if (preg_match('/^0?9\d{9}$/', $m)) {
            return '98'.ltrim($m, '0');
        }
        if (preg_match('/^989\d{9}$/', $m)) {
            return $m;
        }

        return null;
    }

    /**
     * ارسالِ fire-and-forget بعد از پاسخِ HTTP — خطا فقط لاگ می‌شود.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function deliver(string $url, array $payload, array $headers, string $target): void
    {
        dispatch(function () use ($url, $payload, $headers, $target) {
            try {
                $resp = Http::connectTimeout(3)->timeout(5)
                    ->withHeaders($headers)
                    ->post($url, $payload);
                if (! $resp->successful()) {
                    Log::warning('customer_app.bale_notify_failed', [
                        'target' => $target,
                        'status' => $resp->status(),
                        'body' => mb_substr((string) $resp->body(), 0, 300),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('customer_app.bale_notify_failed', [
                    'target' => $target,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
