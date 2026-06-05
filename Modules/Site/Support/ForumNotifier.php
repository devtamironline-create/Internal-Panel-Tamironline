<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Site\Mail\ForumQuestionAnsweredMail;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\SiteSetting;

/**
 * اطلاع‌رسانی به نویسنده‌ی سوال انجمن وقتی ادمین/کارشناس پاسخ می‌دهد.
 *
 * شرایط ارسال:
 *   - تنظیم سراسری `forum_notify_author_email` = '1' (پیش‌فرض = روشن)
 *   - $question->author_email مقدار داشته باشد
 *   - mailer در .env پیکربندی شده باشد (در غیر اینصورت silent fail با log)
 */
final class ForumNotifier
{
    public static function notifyAuthorOfAnswer(Question $question, Answer $answer): void
    {
        // Setting toggle — به‌صورت پیش‌فرض روشن
        $enabled = self::settingEnabled('forum_notify_author_email', true);
        if (! $enabled) {
            return;
        }

        if (empty($question->author_email)) {
            return;
        }
        if (! filter_var($question->author_email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $url = self::publicQuestionUrl($question);
            Mail::to($question->author_email)->send(
                new ForumQuestionAnsweredMail($question, $answer, $url)
            );
        } catch (\Throwable $e) {
            Log::warning('Forum notify email failed', [
                'question_id' => $question->id,
                'email' => $question->author_email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function settingEnabled(string $key, bool $default): bool
    {
        try {
            $val = SiteSetting::query()->where('key', $key)->value('value');
            if ($val === null) {
                return $default;
            }

            return in_array((string) $val, ['1', 'true', 'yes', 'on'], true);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function publicQuestionUrl(Question $question): string
    {
        $base = rtrim((string) config('services.site.public_url', config('app.url', '')), '/');

        return $base.'/forum/'.$question->id.'-'.$question->slug;
    }
}
