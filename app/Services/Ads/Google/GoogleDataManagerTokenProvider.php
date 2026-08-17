<?php

namespace App\Services\Ads\Google;

use Illuminate\Support\Facades\Cache;

/**
 * گرفتنِ OAuth Access Token با Service Account — بدون پکیج اضافه.
 *
 * JWT با RS256 (openssl خودِ PHP) امضا و به oauth2.googleapis.com فرستاده
 * می‌شود؛ درخواست توکن هم مثل بقیهٔ ترافیک Google از GoogleHttpClient
 * (یعنی از پروکسی) عبور می‌کند. توکن تا نزدیک expiry کش می‌شود.
 *
 * هرگز private key یا access token یا محتوای فایل credential در exception
 * یا log قرار نمی‌گیرد.
 */
class GoogleDataManagerTokenProvider
{
    protected const CACHE_KEY = 'ads_google_access_token';

    public function __construct(protected GoogleHttpClient $http, protected array $config) {}

    public static function fromConfig(): self
    {
        return new self(GoogleHttpClient::fromConfig(), (array) config('ads_tracking.google', []));
    }

    public function token(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() && filled($cached['token'] ?? null)) {
            return (string) $cached['token'];
        }

        [$token, $expiresIn] = $this->fetchToken();

        $margin = (int) ($this->config['token_safety_margin'] ?? 300);
        $ttl = max(60, $expiresIn - $margin);
        Cache::put(self::CACHE_KEY, ['token' => $token, 'expires_at' => time() + $ttl], $ttl);

        return $token;
    }

    /** بعد از 401، توکنِ کش‌شده دور ریخته می‌شود تا تلاش بعدی تازه بگیرد. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{0: string, 1: int} [access_token, expires_in] */
    protected function fetchToken(): array
    {
        $creds = $this->credentials();

        $now = time();
        $jwt = $this->signJwt([
            'iss' => $creds['client_email'],
            'scope' => (string) ($this->config['scope'] ?? 'https://www.googleapis.com/auth/datamanager'),
            'aud' => (string) ($this->config['oauth_token_url'] ?? 'https://oauth2.googleapis.com/token'),
            'iat' => $now,
            'exp' => $now + 3600,
        ], (string) $creds['private_key']);

        try {
            $response = $this->http->postForm((string) ($this->config['oauth_token_url'] ?? 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // خطای شبکه/پروکسی موقعِ گرفتن توکن — retryable و fail-closed.
            throw new GoogleDeliveryException(
                'خطای اتصال به OAuth گوگل (از مسیر پروکسی): '.$e->getMessage(),
                retryable: true,
                errorCode: 'CONNECTION',
            );
        }

        if (! $response->successful() || blank($response->json('access_token'))) {
            // بدنهٔ خطای OAuth ممکن است توضیح داشته باشد؛ فقط فیلد error
            // (کوتاه و بدون secret) برداشته می‌شود.
            throw new GoogleDeliveryException(
                'OAuth token failed: HTTP '.$response->status().' '.(string) $response->json('error', ''),
                retryable: $response->status() !== 400 && $response->status() !== 403,
                errorCode: 'OAUTH_'.$response->status(),
            );
        }

        return [(string) $response->json('access_token'), (int) $response->json('expires_in', 3600)];
    }

    /** @return array{client_email: string, private_key: string} */
    protected function credentials(): array
    {
        $path = trim((string) ($this->config['credentials_path'] ?? ''));

        if ($path === '' || ! is_readable($path)) {
            // مسیر لاگ می‌شود (حساس نیست)؛ محتوای فایل هرگز.
            throw new GoogleDeliveryException(
                'فایل credential سرویس‌اکانت در دسترس نیست: '.($path === '' ? '(تنظیم نشده)' : $path),
                retryable: false,
                errorCode: 'CREDENTIALS_MISSING',
            );
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || blank($json['client_email'] ?? null) || blank($json['private_key'] ?? null)) {
            throw new GoogleDeliveryException(
                'فایل credential سرویس‌اکانت نامعتبر است (client_email/private_key).',
                retryable: false,
                errorCode: 'CREDENTIALS_INVALID',
            );
        }

        return $json;
    }

    protected function signJwt(array $claims, string $privateKey): string
    {
        $encode = fn (array $data): string => rtrim(strtr(base64_encode(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ), '+/', '-_'), '=');

        $input = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new GoogleDeliveryException('private key قابل بارگذاری نیست.', retryable: false, errorCode: 'CREDENTIALS_INVALID');
        }

        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GoogleDeliveryException('امضای JWT ناموفق بود.', retryable: false, errorCode: 'JWT_SIGN_FAILED');
        }

        return $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
