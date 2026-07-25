<?php

namespace Modules\Identity\Support;

/**
 * اعتبارسنجیِ initData مینی‌اپِ بله (سازگار با WebApp تلگرام).
 *
 * الگوریتم (طبقِ قراردادِ تیمِ ربات):
 *   secret_key        = HMAC_SHA256(key = 'WebAppData', message = BOT_TOKEN)
 *   data_check_string = «key=value»ها به‌جز hash، مرتبِ الفبایی، جداشده با \n
 *   معتبر است اگر HEX(HMAC_SHA256(key=secret_key, message=data_check_string)) == hash
 */
class BaleInitData
{
    /**
     * @return array{ok:bool, reason?:string, user?:array<string,mixed>|null, auth_date?:string|null}
     */
    public static function validate(string $initData, string $botToken, int $maxAge = 86400): array
    {
        if (trim($initData) === '' || trim($botToken) === '') {
            return ['ok' => false, 'reason' => 'empty'];
        }

        $data = [];
        foreach (explode('&', $initData) as $chunk) {
            if ($chunk === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $chunk, 2), 2, '');
            $data[urldecode($k)] = urldecode($v);
        }

        if (! isset($data['hash']) || $data['hash'] === '') {
            return ['ok' => false, 'reason' => 'no_hash'];
        }
        $hash = (string) $data['hash'];
        unset($data['hash']);

        ksort($data);
        $lines = [];
        foreach ($data as $k => $v) {
            $lines[] = $k.'='.$v;
        }
        $dataCheckString = implode("\n", $lines);

        // دقت: کلیدِ HMAC اول «WebAppData» است و پیامْ «توکنِ ربات».
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expected, $hash)) {
            return ['ok' => false, 'reason' => 'bad_hash'];
        }

        if ($maxAge > 0 && isset($data['auth_date']) && (time() - (int) $data['auth_date'] > $maxAge)) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $user = null;
        if (isset($data['user'])) {
            $decoded = json_decode($data['user'], true);
            $user = is_array($decoded) ? $decoded : null;
        }

        return ['ok' => true, 'user' => $user, 'auth_date' => $data['auth_date'] ?? null];
    }
}
