<?php

namespace Modules\CRM\Services\Najva;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\TechnicianPushToken;

/**
 * ارسالِ پوش تراکنشی به نجوا — POST /v1/send/token/.
 *
 * سه چیز این‌جا عمدی است:
 *
 *   ۱) هیچ‌وقت throw نمی‌کند. اعلان، اثرِ جانبیِ یک عملیاتِ کاری است؛
 *      اگر نجوا پایین باشد، تخصیصِ سفارش نباید شکست بخورد. نتیجه در
 *      `NajvaResult` برمی‌گردد.
 *
 *   ۲) `retry` فقط روی خطای شبکه و ۵xx و ۴۲۹ عمل می‌کند. تکرارِ ۴۱۶
 *      (IP ثبت‌نشده) یا ۴۱۸ (اعتبار تمام) بی‌فایده است و فقط تأخیر
 *      می‌سازد — رفتارِ پیش‌فرضِ لاراول همهٔ ۴xx را تکرار می‌کند، پس
 *      صریح خاموشش می‌کنیم.
 *
 *   ۳) وضعیتِ هر توکن جدا پردازش می‌شود. `InvalidToken` یعنی مرورگر دیگر
 *      وجود ندارد؛ اگر باطلش نکنیم، تا ابد برای هر رویداد هزینه و خطا
 *      تولید می‌کند.
 */
class NajvaPushService
{
    /** سقفِ توکن در هر درخواست — بیشتر از این، نجوا ۴۱۴ می‌دهد. */
    public const MAX_TOKENS_PER_REQUEST = 1000;

    public const TITLE_MAX = 250;

    public const BODY_MAX = 400;

    /** خطاهایی که ادمین باید فوراً ببیند. */
    public const ALARMING_CODES = [403, 416, 418];

    /** ترجمهٔ کدهای خطای نجوا — جدولِ بخش ۳.۴ سند. */
    public const ERRORS = [
        400 => 'پارامترهای ارسالی نامعتبر است.',
        403 => 'کلید API نجوا نامعتبر است.',
        414 => 'تعداد توکن‌ها از سقف مجاز بیشتر است.',
        415 => 'حجم آیکون یا تصویر بیش از حد مجاز است.',
        416 => 'IP سرور در وایت‌لیست پنل نجوا ثبت نشده است.',
        418 => 'اعتبار حساب نجوا کافی نیست.',
    ];

    protected string $apiKey;

    protected string $websiteId;

    protected string $baseUrl;

    public function __construct()
    {
        // اولویت با تنظیماتِ قابلِ ویرایش از پنل؛ در نبودشان env — همان
        // الگوی `KavenegarService`.
        $this->apiKey = $this->setting('najva_api_key', (string) config('services.najva.api_key', ''));
        $this->websiteId = $this->setting('najva_website_id', (string) config('services.najva.website_id', ''));
        $this->baseUrl = rtrim((string) config('services.najva.base_url', 'https://push.najva.com'), '/');
    }

    public function configured(): bool
    {
        return $this->apiKey !== '' && $this->websiteId !== '';
    }

    /**
     * ارسال به فهرستی از توکن‌ها. تکه‌تکه می‌شود، پس تعدادِ ورودی محدود
     * نیست.
     *
     * @param  list<string>  $tokens
     * @param  array{event_key?: string|null, order_id?: int|null, sent_by?: int|null}  $context
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        string $clickUrl,
        int $ttl = 24,
        array $context = []
    ): NajvaResult {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return new NajvaResult;
        }

        if (! $this->configured()) {
            Log::warning('najva.not_configured', ['tokens' => count($tokens)]);

            return NajvaResult::notConfigured();
        }

        $title = mb_substr($title, 0, self::TITLE_MAX);
        $body = mb_substr($body, 0, self::BODY_MAX);

        $sent = $invalid = $failed = $requestIds = [];
        $cost = 0;
        $errorCode = null;
        $errorMessage = null;

        foreach (array_chunk($tokens, self::MAX_TOKENS_PER_REQUEST) as $chunk) {
            $outcome = $this->sendChunk($chunk, $title, $body, $clickUrl, $ttl, $context);

            $sent = array_merge($sent, $outcome->sent);
            $invalid = array_merge($invalid, $outcome->invalid);
            $failed = array_merge($failed, $outcome->failed);
            $requestIds = array_merge($requestIds, $outcome->requestIds);
            $cost += $outcome->cost;

            // اولین خطای واقعی نگه داشته می‌شود؛ تکه‌های بعدی معمولاً
            // همان خطا را می‌دهند و بازنویسی‌اش چیزی اضافه نمی‌کند.
            $errorCode ??= $outcome->errorCode;
            $errorMessage ??= $outcome->errorMessage;
        }

        return new NajvaResult($sent, $invalid, $failed, $requestIds, $cost, $errorCode, $errorMessage);
    }

    /**
     * تستِ اتصال و گرفتنِ `website_id` عددی — GET /v1/sender.
     *
     * ساده‌ترین راهِ تشخیصِ ۴۰۳ (کلیدِ غلط) از ۴۱۶ (IPِ ثبت‌نشده)، و
     * همان چیزی که دکمهٔ «تست اتصال» در تنظیماتِ پنل صدا می‌زند.
     *
     * @return array{ok: bool, message: string, websites: list<array{id: string, address: string}>}
     */
    public function sender(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => 'کلید API نجوا تنظیم نشده است.', 'websites' => []];
        }

        try {
            $response = Http::withHeaders(['apiKey' => $this->apiKey])
                ->timeout(15)
                ->get($this->baseUrl.'/v1/sender');
        } catch (ConnectionException $e) {
            return ['ok' => false, 'message' => 'اتصال به نجوا برقرار نشد: '.$e->getMessage(), 'websites' => []];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->describe($response->status()),
                'websites' => [],
            ];
        }

        $websites = $response->json('Entries.websites', []);

        return [
            'ok' => true,
            'message' => 'اتصال برقرار است.',
            'websites' => is_array($websites) ? $websites : [],
        ];
    }

    // ───────────────────────── داخلی

    /**
     * @param  list<string>  $chunk
     * @param  array{event_key?: string|null, order_id?: int|null, sent_by?: int|null}  $context
     */
    protected function sendChunk(
        array $chunk,
        string $title,
        string $body,
        string $clickUrl,
        int $ttl,
        array $context
    ): NajvaResult {
        try {
            $response = $this->request($chunk, $title, $body, $clickUrl, $ttl);
        } catch (ConnectionException|RequestException $e) {
            Log::error('najva.send_failed', ['error' => $e->getMessage(), 'tokens' => count($chunk)]);
            $this->logChunk($chunk, $title, $body, $clickUrl, $context, PushLog::STATUS_FAILED, null, $e->getMessage());

            return new NajvaResult(failed: $chunk, errorMessage: $e->getMessage());
        }

        if (! $response->successful()) {
            $code = $response->status();
            $message = $this->describe($code);

            // ۴۰۳/۴۱۶/۴۱۸ پیکربندی‌اند نه اتفاق — سطحِ error تا در گزارشِ
            // لاگ گم نشوند.
            Log::error('najva.rejected', ['code' => $code, 'message' => $message, 'body' => $response->body()]);
            $this->logChunk($chunk, $title, $body, $clickUrl, $context, PushLog::STATUS_FAILED, $code, $response->body());

            return new NajvaResult(failed: $chunk, errorCode: $code, errorMessage: $message);
        }

        return $this->consume($response, $chunk, $title, $body, $clickUrl, $context);
    }

    /**
     * @param  list<string>  $chunk
     */
    protected function request(array $chunk, string $title, string $body, string $clickUrl, int $ttl): Response
    {
        $payload = [
            ['name' => 'website_id', 'contents' => $this->websiteId],
            ['name' => 'ttl', 'contents' => (string) max(1, $ttl)],
            ['name' => 'message.title', 'contents' => $title],
            ['name' => 'message.body', 'contents' => $body],
            ['name' => 'message.notification_click.click_url', 'contents' => $clickUrl],
        ];

        foreach ($chunk as $token) {
            $payload[] = ['name' => 'tokens[]', 'contents' => $token];
        }

        return Http::withHeaders(['apiKey' => $this->apiKey])
            ->asMultipart()
            ->timeout(15)
            // فقط خطای گذرا تکرار می‌شود. `throw: false` یعنی پاسخِ آخر
            // برمی‌گردد به‌جای استثنا — تصمیم با خودِ ما.
            ->retry(3, 500, fn (\Throwable $e) => $this->retryable($e), throw: false)
            ->post($this->baseUrl.'/v1/send/token/', $payload);
    }

    protected function retryable(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException && $e->response !== null) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    /**
     * پردازشِ `Entries.tokens[]` — بخش ۳.۳ سند.
     *
     * @param  list<string>  $chunk
     * @param  array{event_key?: string|null, order_id?: int|null, sent_by?: int|null}  $context
     */
    protected function consume(
        Response $response,
        array $chunk,
        string $title,
        string $body,
        string $clickUrl,
        array $context
    ): NajvaResult {
        $requestId = $response->json('Entries.request_id');
        $rows = $response->json('Entries.tokens', []);

        $sent = $invalid = $failed = [];
        $cost = 0;

        // نگاشتِ توکن → ردیفِ مشترک، یک‌بار؛ تا برای هر توکن یک کوئری نزنیم.
        $subscribers = TechnicianPushToken::query()
            ->whereIn('token', $chunk)
            ->get()
            ->keyBy('token');

        $reported = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $token = $row['token'] ?? null;
            if (! is_string($token) || $token === '') {
                continue;
            }

            $status = (string) ($row['status'] ?? PushLog::STATUS_FAILED);
            $rowCost = (int) ($row['cost'] ?? 0);
            $cost += $rowCost;
            $reported[] = $token;

            $subscriber = $subscribers->get($token);

            match ($status) {
                PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED => $sent[] = $token,
                PushLog::STATUS_INVALID => $invalid[] = $token,
                default => $failed[] = $token,
            };

            $this->applyStatus($subscriber, $status);

            PushLog::create([
                'technician_id' => $subscriber?->technician_id,
                'push_token_id' => $subscriber?->id,
                'token' => $token,
                'event_key' => $context['event_key'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'title' => $title,
                'body' => $body,
                'click_url' => $clickUrl,
                'status' => $status,
                'cost' => $rowCost,
                'request_id' => $requestId,
                'sent_by' => $context['sent_by'] ?? null,
            ]);
        }

        // توکنی که فرستادیم ولی نجوا دربارهٔ آن چیزی نگفت. ساکت رهایش
        // نمی‌کنیم، وگرنه در گزارش «ارسال شده» به‌نظر می‌رسد.
        $silent = array_values(array_diff($chunk, $reported));
        if ($silent !== []) {
            $failed = array_merge($failed, $silent);
            $this->logChunk($silent, $title, $body, $clickUrl, $context, PushLog::STATUS_FAILED, null, 'بدون پاسخ برای این توکن');
        }

        return new NajvaResult(
            sent: $sent,
            invalid: $invalid,
            failed: $failed,
            requestIds: $requestId ? [$requestId] : [],
            cost: $cost,
        );
    }

    /**
     * وضعیتِ مشترک را به‌روز می‌کند.
     *
     * `last_seen_at` عمداً دست‌نخورده می‌ماند: «Sent» یعنی نجوا درخواست را
     * پذیرفت، نه اینکه مرورگر زنده است. اگر با هر ارسال به‌روزش کنیم،
     * ستونِ «آخرین فعالیت» در پنل به «آخرین باری که ما چیزی فرستادیم»
     * تبدیل می‌شود و دیگر نمی‌شود فهمید کدام تکنسین اپ را رها کرده.
     */
    protected function applyStatus(?TechnicianPushToken $subscriber, string $status): void
    {
        if (! $subscriber) {
            return;
        }

        if ($status === PushLog::STATUS_INVALID) {
            $subscriber->forceFill([
                'last_status' => $status,
                'failed_count' => $subscriber->failed_count + 1,
                'revoked_at' => $subscriber->revoked_at ?? now(),
            ])->save();

            return;
        }

        $succeeded = in_array($status, [PushLog::STATUS_SENT, PushLog::STATUS_SCHEDULED], true);

        $subscriber->forceFill([
            'last_status' => $status,
            'failed_count' => $succeeded ? 0 : $subscriber->failed_count + 1,
        ])->save();
    }

    /**
     * ثبتِ یک وضعیتِ یکسان برای کلِ تکه — وقتی درخواست پیش از رسیدن به
     * سطحِ توکن شکست خورده است.
     *
     * @param  list<string>  $chunk
     * @param  array{event_key?: string|null, order_id?: int|null, sent_by?: int|null}  $context
     */
    protected function logChunk(
        array $chunk,
        string $title,
        string $body,
        string $clickUrl,
        array $context,
        string $status,
        ?int $errorCode,
        ?string $response
    ): void {
        $subscribers = TechnicianPushToken::query()
            ->whereIn('token', $chunk)
            ->get()
            ->keyBy('token');

        foreach ($chunk as $token) {
            $subscriber = $subscribers->get($token);

            PushLog::create([
                'technician_id' => $subscriber?->technician_id,
                'push_token_id' => $subscriber?->id,
                'token' => $token,
                'event_key' => $context['event_key'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'title' => $title,
                'body' => $body,
                'click_url' => $clickUrl,
                'status' => $status,
                'error_code' => $errorCode,
                'response' => $response !== null ? mb_substr($response, 0, 2000) : null,
                'sent_by' => $context['sent_by'] ?? null,
            ]);
        }
    }

    protected function describe(int $code): string
    {
        return self::ERRORS[$code] ?? ('خطای نامشخص از نجوا (کد '.$code.').');
    }

    protected function setting(string $key, string $fallback): string
    {
        if (! class_exists(CrmSetting::class)) {
            return $fallback;
        }

        try {
            $value = (string) (CrmSetting::get($key, '') ?? '');
        } catch (\Throwable) {
            return $fallback;
        }

        return $value !== '' ? $value : $fallback;
    }
}
