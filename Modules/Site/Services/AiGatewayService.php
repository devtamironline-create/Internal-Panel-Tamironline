<?php

namespace Modules\Site\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Site\Models\AiModel;

/**
 * کلاینتِ عمومیِ AI — سازگار با OpenAI (`/chat/completions`).
 *
 * gatewayِ آروان‌کلاد هم سازگار با OpenAI است؛ همین کلاینت برای هر providerِ
 * سازگار کار می‌کند. احرازِ هویت: هدرِ `Authorization: apikey <key>`.
 *
 * این کلاس provider-agnostic و بی‌اطلاع از «وظیفه» است؛ منطقِ وظیفه‌ها (مثلِ
 * مودریشن) در سرویس‌های بالادستی نوشته می‌شود تا افزودنِ وظایفِ آینده ساده باشد.
 */
class AiGatewayService
{
    /**
     * یک درخواستِ chat به مدل. آرایهٔ استاندارد برمی‌گرداند؛ هیچ‌وقت throw نمی‌کند.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  array<string, mixed>  $opts  override برای max_tokens/temperature/response_format
     * @return array{ok:bool, content:?string, usage:array<string,mixed>, status:?int, error:?string}
     */
    public function chat(AiModel $model, array $messages, array $opts = []): array
    {
        if (! $model->hasApiKey()) {
            return $this->fail('کلیدِ API برای این مدل تنظیم نشده است.');
        }

        $payload = array_filter([
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int) ($opts['max_tokens'] ?? $model->max_tokens),
            'temperature' => (float) ($opts['temperature'] ?? $model->temperature),
            'response_format' => $opts['response_format'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'apikey '.$model->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout((int) ($opts['timeout'] ?? 45))
                ->post($model->chatUrl(), $payload);
        } catch (\Throwable $e) {
            Log::warning('ai.gateway.exception', ['model' => $model->slug, 'error' => $e->getMessage()]);

            return $this->fail('خطا در ارتباط با سرویسِ AI: '.$e->getMessage());
        }

        $status = $response->status();

        if ($status === 401) {
            return $this->fail('کلیدِ API نامعتبر است (401).', $status);
        }
        if ($status === 429) {
            return $this->fail('محدودیتِ نرخِ درخواست (429) — کمی بعد دوباره تلاش کنید.', $status);
        }
        if (! $response->successful()) {
            return $this->fail('پاسخِ ناموفق از سرویسِ AI (کد '.$status.').', $status);
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            return $this->fail('پاسخِ خالی از مدل دریافت شد.', $status);
        }

        return [
            'ok' => true,
            'content' => (string) $content,
            'usage' => (array) ($json['usage'] ?? []),
            'status' => $status,
            'error' => null,
        ];
    }

    /**
     * تستِ اتصال — یک پیامِ ساده می‌فرستد و موفقیت/خطا را برمی‌گرداند.
     *
     * @return array{ok:bool, content:?string, usage:array<string,mixed>, status:?int, error:?string}
     */
    public function testConnection(AiModel $model): array
    {
        return $this->chat($model, [
            ['role' => 'user', 'content' => 'به فارسی فقط بنویس: «سلام»'],
        ], ['max_tokens' => 20, 'timeout' => 20]);
    }

    /**
     * @return array{ok:bool, content:?string, usage:array<string,mixed>, status:?int, error:string}
     */
    private function fail(string $error, ?int $status = null): array
    {
        return ['ok' => false, 'content' => null, 'usage' => [], 'status' => $status, 'error' => $error];
    }
}
