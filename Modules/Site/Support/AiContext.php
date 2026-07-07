<?php

namespace Modules\Site\Support;

use Illuminate\Support\Str;
use Modules\Site\Models\Comment;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;

/**
 * ساختِ «زمینه» (context) برای فراخوانی‌های AI — مدل بینِ فراخوانی‌ها حافظه
 * ندارد؛ هر بار کلِ زمینه (مقاله/سوال + گفتگوی قبلی + زنجیرهٔ parent) باید در
 * همان prompt داده شود. هم مسیرِ خودکار (ai:auto-reply) و هم مسیرِ دستیِ پنل
 * از همین کلاس استفاده می‌کنند تا داوری/پاسخ هیچ‌وقت بدونِ زمینه نباشد.
 *
 * بودجهٔ توکن: متن‌ها بریده می‌شوند (مقاله ~۱۵۰۰، سوال ~۸۰۰، هر پاسخ ~۳۰۰
 * کاراکتر؛ حداکثر ۶ پاسخِ قبلی + زنجیرهٔ parent تا ۵ سطح).
 */
class AiContext
{
    /**
     * زمینهٔ یک کامنت: صفحهٔ میزبان (مقاله با خلاصه، یا دستگاه/برند با نام)
     * + زنجیرهٔ کامنت از ریشه تا والدِ مستقیم.
     */
    public static function forComment(Comment $comment): ?string
    {
        $parts = [];

        $owner = $comment->commentable;
        if ($owner) {
            $title = trim((string) ($owner->title ?? $owner->name ?? ''));
            $summary = trim((string) ($owner->excerpt ?? ''));
            if ($summary === '') {
                $summary = trim(strip_tags((string) ($owner->body ?? $owner->description ?? '')));
            }
            $block = '[صفحه/مقاله‌ای که این نظر زیرِ آن ثبت شده]';
            if ($title !== '') {
                $block .= "\nعنوان: {$title}";
            }
            if ($summary !== '') {
                $block .= "\nخلاصه: ".Str::limit($summary, 1500);
            }
            $parts[] = $block;
        }

        $chain = self::commentChain($comment);
        if ($chain !== '') {
            $parts[] = "[زنجیرهٔ گفتگو — از ریشه تا پیامِ قبلی]\n".$chain;
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * زمینهٔ یک سوالِ انجمن: متادیتای دستگاه/برند/مدل/تگ‌ها (متنِ خودِ سوال
     * جداگانه به مدل داده می‌شود).
     */
    public static function forQuestion(Question $question): ?string
    {
        $meta = array_filter([
            'دستگاه' => $question->device?->name,
            'برند' => $question->brand?->name,
            'مدل' => is_string($question->model) ? trim($question->model) : null,
            'تگ‌ها' => is_array($question->tags) ? implode('، ', array_filter($question->tags)) : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($meta === []) {
            return null;
        }

        $lines = [];
        foreach ($meta as $k => $v) {
            $lines[] = "{$k}: {$v}";
        }

        return "[مشخصاتِ سوال]\n".implode("\n", $lines);
    }

    /**
     * زمینهٔ یک پاسخ/پیگیریِ کاربر در انجمن: سوالِ اصلی (عنوان + متادیتا + متن)
     * + پاسخ‌های منتشرشدهٔ قبلی به ترتیبِ زمان + زنجیرهٔ parent («در پاسخ به»).
     */
    public static function forAnswer(Answer $answer): ?string
    {
        $question = $answer->question;
        if (! $question) {
            return null;
        }

        $parts = [];

        $block = "[سوالِ اصلی]\nعنوان: {$question->title}";
        $meta = self::forQuestion($question);
        if ($meta !== null) {
            $block .= "\n".preg_replace('/^\[.*\]\n/', '', $meta);
        }
        $body = trim(strip_tags((string) $question->body));
        if ($body !== '') {
            $block .= "\nمتن: ".Str::limit($body, 800);
        }
        $parts[] = $block;

        $prev = Answer::query()
            ->where('question_id', $question->id)
            ->where('status', Answer::STATUS_APPROVED)
            ->where('id', '!=', $answer->id)
            ->oldest()
            ->limit(6)
            ->get(['id', 'body', 'is_expert_reply', 'author_name']);
        if ($prev->isNotEmpty()) {
            $lines = [];
            foreach ($prev as $i => $p) {
                $who = $p->is_expert_reply ? 'کارشناس' : 'کاربر';
                $lines[] = ($i + 1).') '.$who.': '.Str::limit(trim(strip_tags((string) $p->body)), 300);
            }
            $parts[] = "[پاسخ‌های قبلی — به ترتیبِ زمان]\n".implode("\n", $lines);
        }

        // زنجیرهٔ parent تا ریشه — پیامِ جدید دقیقاً در پاسخ به چه چیزی است؟
        $chainLines = [];
        $cursor = $answer->parent_id ? Answer::find($answer->parent_id) : null;
        $depth = 0;
        while ($cursor && $depth < 5) {
            $who = $cursor->is_expert_reply ? 'کارشناس' : 'کاربر';
            array_unshift($chainLines, $who.': '.Str::limit(trim(strip_tags((string) $cursor->body)), 300));
            $cursor = $cursor->parent_id ? Answer::find($cursor->parent_id) : null;
            $depth++;
        }
        if ($chainLines !== []) {
            $parts[] = "[این پیام مستقیماً در پاسخ به این زنجیره است]\n".implode("\n", $chainLines);
        }

        return implode("\n\n", $parts);
    }

    /**
     * زنجیرهٔ والدینِ یک کامنت از ریشه تا والدِ مستقیم (حداکثر ۶ سطح).
     */
    private static function commentChain(Comment $comment): string
    {
        $lines = [];
        $cursor = $comment->parent_id ? Comment::find($comment->parent_id) : null;
        $depth = 0;
        while ($cursor && $depth < 6) {
            $who = $cursor->is_admin_reply
                ? 'پشتیبانِ تعمیرآنلاین'
                : (trim((string) $cursor->author_name) !== '' ? trim((string) $cursor->author_name) : 'کاربر');
            array_unshift($lines, $who.': '.Str::limit(trim(strip_tags((string) $cursor->content)), 300));
            $cursor = $cursor->parent_id ? Comment::find($cursor->parent_id) : null;
            $depth++;
        }

        return implode("\n", $lines);
    }
}
