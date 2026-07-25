<?php

namespace Modules\CustomerApp\Support;

use Illuminate\Support\Facades\Http;

/**
 * ارسالِ همگام (sync) پیامِ متنی به بله با نتیجهٔ قابلِ‌بررسی — برای کمپین‌ها
 * و تست. (کانالِ نوتیفیکیشن BaleChannel جدا و fire-and-forget است.)
 *
 * دو مسیر:
 *   sendToChat: چتِ ربات با bale_user_id (رایگان)
 *   sendToPhone: سفیر با شمارهٔ 98912… (سرویسِ تجاری — قراردادِ تأییدشده:
 *                bot_id عددی + message_data.message.text)
 */
class BaleMessenger
{
    /** @return array{ok: bool, error: ?string} */
    public static function sendToChat(int $chatId, string $text): array
    {
        $token = (string) config('services.bale.bot_token');
        if ($token === '' || $chatId <= 0) {
            return ['ok' => false, 'error' => 'bot_not_configured'];
        }

        $base = rtrim((string) config('services.bale.api_base', 'https://tapi.bale.ai'), '/');

        return self::post($base.'/bot'.$token.'/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ], []);
    }

    /** @return array{ok: bool, error: ?string} */
    public static function sendToPhone(string $phone, string $text): array
    {
        $accessKey = (string) config('services.bale.safir_access_key');
        $botId = (string) config('services.bale.safir_bot_id');
        if ($accessKey === '' || $botId === '') {
            return ['ok' => false, 'error' => 'safir_not_configured'];
        }

        $base = rtrim((string) config('services.bale.safir_base', 'https://safir.bale.ai'), '/');

        return self::post($base.'/api/v3/send_message', [
            'bot_id' => is_numeric($botId) ? (int) $botId : $botId,
            'phone_number' => $phone,
            'message_data' => ['message' => ['text' => $text]],
        ], ['api-access-key' => $accessKey]);
    }

    /**
     * بهترین مسیرِ در دسترس برای یک گیرنده: اول چتِ ربات، بعد سفیر.
     *
     * @return array{ok: bool, error: ?string}
     */
    public static function sendBest(?int $baleUserId, ?string $phone, string $text): array
    {
        if ($baleUserId !== null && $baleUserId > 0 && (string) config('services.bale.bot_token') !== '') {
            $result = self::sendToChat($baleUserId, $text);
            if ($result['ok']) {
                return $result;
            }
            // چت شکست خورد (مثلاً کاربر ربات را بلاک کرده) → تلاش با سفیر
        }

        if ($phone !== null && $phone !== '') {
            return self::sendToPhone($phone, $text);
        }

        return $result ?? ['ok' => false, 'error' => 'no_route'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array{ok: bool, error: ?string}
     */
    private static function post(string $url, array $payload, array $headers): array
    {
        try {
            $resp = Http::connectTimeout(3)->timeout(8)->withHeaders($headers)->post($url, $payload);
            if ($resp->successful()) {
                return ['ok' => true, 'error' => null];
            }

            return ['ok' => false, 'error' => 'HTTP '.$resp->status().': '.mb_substr((string) $resp->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }
}
