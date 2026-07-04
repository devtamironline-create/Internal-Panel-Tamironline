<?php

namespace Modules\Site\Services;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Models\SeoSetting;
use Modules\Site\Models\Article;
use Modules\Site\Models\Comment;
use Modules\Site\Models\SiteSetting;
use Modules\SMS\Services\KavenegarService;

/**
 * اطلاع‌رسانیِ پیامکی به کاربر وقتی به کامنتش پاسخ داده می‌شود.
 *
 * الگوی کاوه‌نگار: comment-response — تنها توکن: %token = لینکِ مشاهدهٔ کامنت.
 * فقط برای پاسخِ ادمین/AIِ «تأییدشده» و یک‌بار (reply_sms_sent_at) ارسال می‌شود.
 */
class CommentReplyNotifier
{
    public const TEMPLATE = 'comment-response';

    public function __construct(private KavenegarService $sms) {}

    /**
     * برای یک پاسخِ (فرزندِ is_admin_reply)، به مشتریِ کامنتِ والد پیامک بزن.
     */
    public function notify(Comment $reply): void
    {
        try {
            if (SiteSetting::get('comment_reply_sms_enabled', '1') !== '1') {
                return;
            }
            if (! $reply->is_admin_reply
                || $reply->status !== Comment::STATUS_APPROVED
                || $reply->reply_sms_sent_at
                || ! $reply->parent_id) {
                return;
            }

            $parent = $reply->parent; // کامنتِ خودِ کاربر
            $mobile = $parent?->customer?->mobile;
            if (! $mobile) {
                return; // کاربرِ مهمان/بدونِ شماره — چیزی ارسال نمی‌شود
            }

            $link = $this->buildLink($reply, $parent);
            if (! $link) {
                return;
            }

            $res = $this->sms->sendTemplate($mobile, self::TEMPLATE, ['token' => $link]);

            if ($res['success'] ?? false) {
                $reply->reply_sms_sent_at = now();
                $reply->saveQuietly();
            } else {
                Log::warning('comment_reply_sms.failed', [
                    'reply_id' => $reply->id, 'message' => $res['message'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('comment_reply_sms.exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * لینکِ فرانتِ کامنت: siteUrl + مسیرِ entity + #comment-{parentId}.
     */
    private function buildLink(Comment $reply, Comment $parent): ?string
    {
        $owner = $reply->commentable;
        if (! $owner || empty($owner->slug)) {
            return null;
        }

        $prefix = match (true) {
            $owner instanceof Article => '/blog/',
            $owner instanceof Device => '/devices/',
            $owner instanceof Brand => '/brands/',
            default => null,
        };
        if ($prefix === null) {
            return null;
        }

        return SeoSetting::siteUrl().$prefix.$owner->slug.'#comment-'.$parent->id;
    }
}
