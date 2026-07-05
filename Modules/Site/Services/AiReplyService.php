<?php

namespace Modules\Site\Services;

use Modules\Site\Models\AiModel;
use Modules\Site\Models\SiteSetting;

/**
 * تولیدِ پاسخِ خودکارِ AI برای کامنت‌ها و سوال‌های انجمن (وظیفهٔ «reply»).
 *
 * خروجی یک متنِ فارسیِ آمادهٔ نمایش است، یا skip=true وقتی نظری نیازی به پاسخ ندارد.
 * لحن: پشتیبانِ رسمیِ تعمیرآنلاین — کوتاه، دقیق، بدونِ وعده‌های قطعیِ قیمت/زمان.
 */
class AiReplyService
{
    public function __construct(private AiGatewayService $gateway) {}

    /** مدلِ انتخاب‌شده برای وظیفهٔ پاسخ (یا پیش‌فرض). */
    public function model(): ?AiModel
    {
        return AiModel::bySlug(SiteSetting::get('ai_reply_model')) ?? AiModel::default();
    }

    /**
     * تولیدِ پاسخ. هیچ‌وقت throw نمی‌کند.
     *
     * @param  array<string, mixed>  $context  type / title / must_reply
     * @return array{ok:bool, skip:bool, text:?string, model:?AiModel, usage:array<string,mixed>, error:?string}
     */
    public function reply(string $text, array $context = []): array
    {
        $model = $this->model();
        if (! $model) {
            return $this->fail('هیچ مدلِ فعالی برای پاسخ تنظیم نشده است.');
        }

        $text = trim($text);
        if ($text === '') {
            return $this->fail('متنِ خالی.', $model);
        }

        $mustReply = (bool) ($context['must_reply'] ?? false);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($text, $context)],
        ];

        // سقفِ توکنِ بالا برای مدل‌های reasoning (مثلِ GPT-5-Mini): این مدل‌ها قبل
        // از خروجی، توکنِ «استدلال» مصرف می‌کنند؛ با سقفِ کم، content خالی
        // برمی‌گردد (finish_reason=length).
        $res = $this->gateway->chat($model, $messages, ['temperature' => 0.4, 'max_tokens' => 2000]);

        // اگر سقفِ توکن پر شد (استدلالِ طولانی)، یک‌بار با سقفِ دوبرابر تلاش کن —
        // تا سوالِ سخت بی‌پاسخ نماند.
        if (! $res['ok'] && str_contains((string) $res['error'], 'max_tokens')) {
            $res = $this->gateway->chat($model, $messages, ['temperature' => 0.4, 'max_tokens' => 4000]);
        }

        if (! $res['ok']) {
            return $this->fail($res['error'] ?? 'خطای نامشخصِ AI.', $model);
        }

        $out = trim((string) $res['content']);
        // مدل گاهی متن را در «...» یا ```...``` می‌گذارد — پاک کن.
        $out = trim($out, '`');
        $out = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $out) ?? $out;

        // اگر پاسخ لازم نبود (فقط برای کامنت‌ها؛ سوالِ انجمن همیشه پاسخ می‌گیرد).
        if (! $mustReply && ($out === '' || mb_stripos($out, 'NO_REPLY') !== false)) {
            return ['ok' => true, 'skip' => true, 'text' => null, 'model' => $model, 'usage' => $res['usage'], 'error' => null];
        }

        // در حالتِ must_reply (انجمن) اگر مدل NO_REPLY داد، متنِ خام نباید منتشر
        // شود — شکست تا در اجرای بعدی دوباره تلاش شود.
        if ($mustReply && $out !== '' && mb_stripos($out, 'NO_REPLY') !== false) {
            return $this->fail('مدل به‌جای پاسخ NO_REPLY برگرداند.', $model);
        }

        if ($out === '' || mb_strlen($out) < 3) {
            return $this->fail('پاسخِ خالی از مدل.', $model);
        }

        return ['ok' => true, 'skip' => false, 'text' => mb_substr($out, 0, 3000), 'model' => $model, 'usage' => $res['usage'], 'error' => null];
    }

    private function systemPrompt(): string
    {
        return 'تو پشتیبانِ رسمیِ «تعمیرآنلاین» (خدماتِ تخصصیِ تعمیرِ لوازم خانگی) هستی. '
            .'به نظر/سوالِ کاربر یک پاسخِ فارسیِ کوتاه (۲ تا ۴ جمله)، مؤدب، دقیق و کاربردی بده. '
            .'قوانین: قیمت یا زمانِ دقیق را قطعی نگو (بگو پس از بررسی/بازدید اعلام می‌شود)؛ '
            .'اگر نیاز به بازدیدِ تکنسین است بگو؛ چیزی از خودت نساز و اطلاعاتِ نادرست نده؛ '
            .'لحن حرفه‌ای و همدلانه؛ بدونِ markdown و بدونِ امضا. '
            .'فقط خودِ متنِ پاسخ را بنویس. اگر متنِ کاربر صرفاً تشکر/تعریفِ ساده است و نیازی به پاسخ ندارد، دقیقاً بنویس: NO_REPLY';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function userPrompt(string $text, array $context): string
    {
        $type = $context['type'] ?? 'نظر';
        $title = isset($context['title']) ? "عنوانِ سوال: {$context['title']}\n" : '';
        // صفحهٔ میزبان (نامِ دستگاه/برند یا مقاله) تا پاسخ به همان محصول/موضوع
        // مرتبط و دقیق باشد.
        $page = isset($context['page']) ? "این {$type} در صفحهٔ «{$context['page']}» ثبت شده است؛ پاسخ را به همین موضوع مرتبط کن.\n" : '';

        return "به این «{$type}»ِ کاربر پاسخ بده:\n{$page}{$title}«««\n{$text}\n»»»";
    }

    /**
     * @return array{ok:false, skip:false, text:null, model:?AiModel, usage:array<string,mixed>, error:string}
     */
    private function fail(string $error, ?AiModel $model = null): array
    {
        return ['ok' => false, 'skip' => false, 'text' => null, 'model' => $model, 'usage' => [], 'error' => $error];
    }
}
