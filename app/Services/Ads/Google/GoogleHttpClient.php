<?php

namespace App\Services\Ads\Google;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * تنها راهِ خروجِ HTTP به سمت Google (توکن، ingest، requestStatus).
 *
 * چرا یک کلاس جدا: Backend داخل ایران است و هیچ درخواست Google نباید
 * مستقیم خارج شود. این کلاس پروکسی/timeout/TLS را یک‌جا اعمال می‌کند و
 * مهم‌تر: وقتی پروکسی اجباری است ولی پیکربندی ناقص است، پیش از ساختن
 * هر اتصالی exception می‌دهد (Fail Closed) — fallback مستقیم وجود ندارد.
 */
class GoogleHttpClient
{
    public function __construct(protected array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('ads_tracking.google', []));
    }

    /**
     * گزینه‌های Guzzle برای «هر» درخواست Google — public تا تست بتواند
     * ثابت کند پروکسی واقعاً روی transport می‌نشیند.
     *
     * @throws GoogleProxyUnavailableException وقتی پروکسی اجباری ولی ناقص است.
     */
    public function options(): array
    {
        $options = [
            'timeout' => (int) ($this->config['request_timeout'] ?? 30),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 10),
            // TLS verification هرگز خاموش نمی‌شود.
            'verify' => true,
        ];

        $proxy = (array) ($this->config['proxy'] ?? []);
        if (! empty($proxy['enabled'])) {
            $url = trim((string) ($proxy['url'] ?? ''));

            // Fail Closed: پروکسی روشن ولی آدرس خالی/نامعتبر → هیچ درخواستی.
            if ($url === '' || ! preg_match('#^https?://[^/]+#', $url)) {
                throw new GoogleProxyUnavailableException;
            }

            $username = trim((string) ($proxy['username'] ?? ''));
            if ($username !== '') {
                $parts = parse_url($url);
                $auth = rawurlencode($username).':'.rawurlencode((string) ($proxy['password'] ?? ''));
                $url = ($parts['scheme'] ?? 'http').'://'.$auth.'@'.($parts['host'] ?? '')
                    .(isset($parts['port']) ? ':'.$parts['port'] : '');
            }

            // هر دو scheme از همان CONNECT proxy عبور می‌کنند.
            $options['proxy'] = ['http' => $url, 'https' => $url];
        }

        return $options;
    }

    public function proxyRequired(): bool
    {
        return ! empty($this->config['proxy']['enabled']);
    }

    protected function request(array $headers = []): PendingRequest
    {
        // options() قبل از هر درخواست صدا زده می‌شود تا گاردِ fail-closed
        // حتی وسط اجرا (config cache قدیمی و…) هم اعمال شود.
        return Http::withOptions($this->options())->withHeaders($headers)->acceptJson();
    }

    public function postJson(string $url, array $payload, array $headers = []): Response
    {
        return $this->request($headers)->asJson()->post($url, $payload);
    }

    public function postForm(string $url, array $fields, array $headers = []): Response
    {
        return $this->request($headers)->asForm()->post($url, $fields);
    }

    public function get(string $url, array $query = [], array $headers = []): Response
    {
        return $this->request($headers)->get($url, $query);
    }
}
