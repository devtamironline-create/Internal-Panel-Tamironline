<?php

namespace Modules\Site\Observers;

use Modules\Site\Models\Comment;
use Modules\Site\Services\CommentReplyNotifier;

/**
 * وقتی یک پاسخِ ادمین/AI «تأییدشده» ثبت یا تأیید می‌شود، به کاربرِ کامنتِ اصلی
 * پیامک اطلاع‌رسانی زده می‌شود. همهٔ مسیرها را پوشش می‌دهد: پاسخِ دستیِ ادمین
 * (تأییدشده)، پاسخِ خودکارِ AI در حالتِ auto، و پیش‌نویسِ AI که بعداً تأیید می‌شود.
 */
class CommentObserver
{
    public function saved(Comment $comment): void
    {
        if ($comment->is_admin_reply
            && $comment->status === Comment::STATUS_APPROVED
            && ! $comment->reply_sms_sent_at
            && $comment->parent_id) {
            app(CommentReplyNotifier::class)->notify($comment);
        }
    }
}
