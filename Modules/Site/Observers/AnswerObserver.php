<?php

namespace Modules\Site\Observers;

use Modules\Site\Models\Forum\Answer;
use Modules\Site\Services\ForumAnswerSmsNotifier;

/**
 * وقتی پاسخی روی سوالِ انجمن «تأیید» می‌شود (پاسخِ AI، ادمین، یا پاسخِ
 * تأییدشدهٔ کاربرِ دیگر)، به نویسندهٔ سوال پیامکِ اطلاع‌رسانی زده می‌شود.
 * همهٔ مسیرها را پوشش می‌دهد: انتشارِ خودکارِ AI، پاسخِ دستیِ ادمین، و
 * پیش‌نویسی که بعداً تأیید می‌شود.
 */
class AnswerObserver
{
    public function saved(Answer $answer): void
    {
        if ($answer->status === Answer::STATUS_APPROVED && ! $answer->sms_sent_at) {
            app(ForumAnswerSmsNotifier::class)->notify($answer);
        }
    }
}
