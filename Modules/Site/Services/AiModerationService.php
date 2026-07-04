<?php

namespace Modules\Site\Services;

use Modules\Site\Models\AiModel;
use Modules\Site\Models\SiteSetting;

/**
 * مودریشنِ محتوا با AI — کامنت‌ها و سوال‌های انجمن.
 *
 * خروجی همیشه یکی از سه تصمیم است: approve / reject / spam، به‌همراه درجهٔ
 * اطمینان و دلیلِ کوتاه. مدلِ استفاده‌شده از تنظیماتِ پنل (ai_moderation_model)
 * یا مدلِ پیش‌فرض انتخاب می‌شود؛ پس تعویضِ مدل فقط تنظیمات است، نه کد.
 */
class AiModerationService
{
    /** تصمیم‌های معتبر. */
    public const DECISIONS = ['approve', 'reject', 'spam'];

    public function __construct(private AiGatewayService $gateway) {}

    /** مدلِ انتخاب‌شده برای وظیفهٔ مودریشن. */
    public function model(): ?AiModel
    {
        return AiModel::bySlug(SiteSetting::get('ai_moderation_model')) ?? AiModel::default();
    }

    /**
     * تحلیلِ یک متن. هیچ‌وقت throw نمی‌کند و چیزی در دیتابیس ذخیره نمی‌کند
     * (ذخیره/اعمال به‌عهدهٔ فراخواننده است).
     *
     * @param  array<string, mixed>  $context  اطلاعاتِ کمکی (نوع، عنوان، …)
     * @return array{ok:bool, model:?AiModel, decision:?string, confidence:?float, reason:?string, usage:array<string,mixed>, raw:?string, error:?string}
     */
    public function analyze(string $text, array $context = []): array
    {
        $model = $this->model();
        if (! $model) {
            return $this->fail('هیچ مدلِ فعالی برای مودریشن تنظیم نشده است. ابتدا در «هوش مصنوعی» یک مدل بسازید.');
        }

        $text = trim($text);
        if ($text === '') {
            return $this->fail('متنِ خالی قابلِ بررسی نیست.', $model);
        }

        $res = $this->gateway->chat($model, [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($text, $context)],
        ], ['temperature' => 0.1, 'max_tokens' => 600]);

        if (! $res['ok']) {
            return $this->fail($res['error'] ?? 'خطای نامشخصِ AI.', $model);
        }

        $content = (string) $res['content'];
        $parsed = $this->parseJson($content);

        // تصمیم را نرمال کن (انگلیسی/فارسی/با متنِ اضافه همگی پذیرفته می‌شوند).
        $decision = $this->normalizeDecision($parsed['decision'] ?? null);
        $confidence = isset($parsed['confidence']) ? max(0.0, min(1.0, (float) $parsed['confidence'])) : null;
        $reason = isset($parsed['reason']) ? mb_substr(trim((string) $parsed['reason']), 0, 500) : null;

        // اگر JSON نبود، از متنِ خامِ پاسخ استنتاج کن (اطمینانِ پایین).
        if ($decision === null) {
            $decision = $this->inferDecisionFromText($content);
            if ($decision !== null) {
                $confidence ??= 0.5;
                $reason ??= 'استنتاج از متنِ پاسخِ مدل';
            }
        }

        if ($decision === null) {
            return $this->fail(
                'پاسخِ AI قابلِ تفسیر نبود. پاسخِ مدل: '.mb_substr(trim($content), 0, 180),
                $model,
                $content
            );
        }

        return [
            'ok' => true,
            'model' => $model,
            'decision' => $decision,
            'confidence' => $confidence,
            'reason' => $reason,
            'usage' => $res['usage'],
            'raw' => $content,
            'error' => null,
        ];
    }

    /**
     * نرمال‌سازیِ مقدارِ تصمیم — انگلیسی/فارسی/با علائم یا متنِ اضافه.
     */
    private function normalizeDecision(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim(mb_strtolower($value));
        $v = trim($v, " \t\n\r\0\x0B.،,\"'«»؛:()");

        $map = [
            'approve' => 'approve', 'approved' => 'approve', 'accept' => 'approve', 'ok' => 'approve',
            'valid' => 'approve', 'publish' => 'approve', 'safe' => 'approve',
            'تایید' => 'approve', 'تأیید' => 'approve', 'تاييد' => 'approve', 'قبول' => 'approve',
            'سالم' => 'approve', 'مجاز' => 'approve', 'منتشر' => 'approve',
            'spam' => 'spam', 'اسپم' => 'spam', 'هرزنامه' => 'spam', 'تبلیغ' => 'spam', 'تبليغ' => 'spam',
            'reject' => 'reject', 'rejected' => 'reject', 'deny' => 'reject', 'denied' => 'reject',
            'رد' => 'reject', 'توهین' => 'reject', 'توهين' => 'reject', 'نامناسب' => 'reject',
            'مستهجن' => 'reject', 'مردود' => 'reject', 'حذف' => 'reject',
        ];
        if (isset($map[$v])) {
            return $map[$v];
        }
        // مقدارِ حاویِ واژهٔ کلیدی (مثلِ "spam (advertising)")
        foreach (['spam', 'reject', 'approve'] as $canon) {
            if (str_contains($v, $canon)) {
                return $canon;
            }
        }

        return null;
    }

    /**
     * استنتاجِ تصمیم از متنِ خامِ پاسخ (وقتی JSON نبود). آخرین راهِ نجات.
     */
    private function inferDecisionFromText(string $content): ?string
    {
        $t = mb_strtolower($content);
        $groups = [
            'spam' => ['"spam"', 'spam', 'اسپم', 'هرزنامه', 'تبلیغ', 'تبليغ'],
            'reject' => ['"reject"', 'reject', 'توهین', 'توهين', 'نامناسب', 'مستهجن', 'فحش', 'مردود'],
            'approve' => ['"approve"', 'approve', 'سالم', 'قابل تایید', 'قابل تأیید', 'مجاز', 'منتشر'],
        ];
        foreach ($groups as $decision => $keywords) {
            foreach ($keywords as $k) {
                if (str_contains($t, $k)) {
                    return $decision;
                }
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        تو ناظرِ محتوای یک وب‌سایتِ فارسیِ خدماتِ تعمیرِ لوازم خانگی هستی. وظیفهٔ تو
        دسته‌بندیِ نظر/سوالِ کاربر در یکی از سه دستهٔ زیر است:

        - "approve": محتوای سالم، مرتبط و قابلِ انتشار (سوال یا نظرِ واقعی دربارهٔ تعمیر/دستگاه).
        - "spam": تبلیغات، لینکِ نامرتبط، شماره‌تلفن/آدرسِ تبلیغاتی، پیامِ تکراری، بی‌معنی یا فریب.
        - "reject": توهین، فحش، محتوای مستهجن، نفرت‌پراکنی، یا کاملاً بی‌ربط و مخرب.

        قواعد:
        - سخت‌گیرِ بی‌مورد نباش؛ انتقادِ منفیِ مؤدبانه approve است.
        - اگر مطمئن نیستی، approve را با confidence پایین برگردان.
        - فقط و فقط یک شیءِ JSON برگردان، بدونِ توضیحِ اضافه و بدونِ markdown.

        قالبِ خروجی (دقیقاً همین کلیدها):
        {"decision":"approve|spam|reject","confidence":0.0..1.0,"reason":"یک جملهٔ کوتاهِ فارسی"}
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function userPrompt(string $text, array $context): string
    {
        $type = $context['type'] ?? 'نظر';
        $title = isset($context['title']) ? "عنوان: {$context['title']}\n" : '';

        return "نوع: {$type}\n{$title}متن:\n\"\"\"\n{$text}\n\"\"\"";
    }

    /**
     * استخراجِ اولین شیءِ JSON از متن (مدل گاهی داخلِ ```json می‌گذارد).
     *
     * @return array<string, mixed>
     */
    private function parseJson(string $content): array
    {
        $content = trim($content);
        // اگر خودش JSON خالص بود
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // وگرنه اولین بلوکِ {...} را بیرون بکش
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array{ok:false, model:?AiModel, decision:null, confidence:null, reason:null, usage:array<string,mixed>, raw:?string, error:string}
     */
    private function fail(string $error, ?AiModel $model = null, ?string $raw = null): array
    {
        return [
            'ok' => false, 'model' => $model, 'decision' => null, 'confidence' => null,
            'reason' => null, 'usage' => [], 'raw' => $raw, 'error' => $error,
        ];
    }
}
