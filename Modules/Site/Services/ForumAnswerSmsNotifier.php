<?php

namespace Modules\Site\Services;

use Illuminate\Support\Facades\Log;
use Modules\Seo\Models\SeoSetting;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\SiteSetting;
use Modules\SMS\Services\KavenegarService;

/**
 * پیامکِ «سوالِ شما در انجمن پاسخ گرفت» به نویسندهٔ سوال — همان الگوی
 * CommentReplyNotifier کامنت‌ها.
 *
 * الگوی کاوه‌نگار از تنظیمِ forum_answer_sms_template (پیش‌فرض همان
 * comment-response موجود) خوانده می‌شود؛ %token = لینکِ صفحهٔ سوال.
 * فقط برای پاسخِ «تأییدشده» روی سوالِ «منتشرشده» و یک‌بار (sms_sent_at).
 */
class ForumAnswerSmsNotifier
{
    public function __construct(private KavenegarService $sms) {}

    public function notify(Answer $answer): void
    {
        try {
            if (SiteSetting::get('forum_answer_sms_enabled', '1') !== '1') {
                return;
            }
            if ($answer->status !== Answer::STATUS_APPROVED || $answer->sms_sent_at) {
                return;
            }

            $question = $answer->question;
            if (! $question
                || $question->status !== \Modules\Site\Models\Forum\Question::STATUS_APPROVED
                || ! $question->published_at
                || empty($question->slug)) {
                return; // سوالِ منتشرنشده صفحهٔ عمومی ندارد — لینکی برای فرستادن نیست.
            }

            $mobile = $question->customer?->mobile ?: $question->author_phone;
            if (! $mobile) {
                return;
            }

            // اگر نویسندهٔ پاسخ خودِ نویسندهٔ سوال است، اطلاع‌رسانی بی‌معناست.
            if (! empty($answer->author_phone) && $answer->author_phone === $question->author_phone) {
                return;
            }

            $template = SiteSetting::get('forum_answer_sms_template', 'comment-response') ?: 'comment-response';
            $link = SeoSetting::siteUrl().'/forum/'.$question->slug;

            $res = $this->sms->sendTemplate($mobile, $template, ['token' => $link]);

            if ($res['success'] ?? false) {
                $answer->sms_sent_at = now();
                $answer->saveQuietly();
            } else {
                Log::warning('forum_answer_sms.failed', [
                    'answer_id' => $answer->id, 'message' => $res['message'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('forum_answer_sms.exception', ['error' => $e->getMessage()]);
        }
    }
}
