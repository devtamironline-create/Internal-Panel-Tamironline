<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Site\Models\AiDecisionLog;
use Modules\Site\Models\Comment;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Services\AiModerationService;
use Modules\Site\Services\AiReplyService;

/**
 * خطِ لولهٔ خودکارِ AI برای کامنت‌ها و سوال‌های انجمن: «مودریشن سپس پاسخ».
 *
 * محتوای در‌انتظار ابتدا مودریت می‌شود (اسپم/رد → مخفی؛ سالم → تأیید)، سپس به
 * محتوای سالم پاسخ داده می‌شود. این کاملاً hands-off است.
 *
 * حالت (ai_reply_mode):
 *   - off   : غیرفعال (پیش‌فرض)
 *   - draft : پاسخ نوشته می‌شود ولی «در انتظار» می‌ماند (تأییدِ مدیر)
 *   - auto  : پاسخ بلافاصله منتشر می‌شود
 * (توجه: مودریشنِ خودِ محتوا در هر دو حالت خودکار اعمال می‌شود؛ فقط انتشارِ
 *  «پاسخ» تابعِ حالت است.)
 *
 * idempotent: هر آیتم یک‌بار پردازش می‌شود (ai_decision_logs, task=reply). برای
 * scheduler هر ۵ دقیقه ثبت شده است.
 */
class AutoReplyCommand extends Command
{
    protected $signature = 'ai:auto-reply
                            {--limit=5 : تعداد آیتم در هر اجرا (برای هر بخش)}
                            {--days=30 : فقط محتوای جدیدتر از این تعداد روز}';

    protected $description = 'مودریشن + پاسخِ خودکارِ AI به کامنت‌ها و سوال‌های انجمن';

    public function handle(AiReplyService $reply, AiModerationService $moderation): int
    {
        $mode = SiteSetting::get('ai_reply_mode', 'off');
        if (! in_array($mode, ['draft', 'auto'], true)) {
            $this->info('پاسخِ خودکار غیرفعال است (ai_reply_mode='.$mode.').');

            return self::SUCCESS;
        }

        $limit = max(1, min((int) $this->option('limit'), 50));
        $days = max(1, (int) $this->option('days'));
        $publish = $mode === 'auto';
        $author = SiteSetting::get('ai_reply_author', 'تیم تعمیرآنلاین') ?: 'تیم تعمیرآنلاین';

        // گیتِ ایمنیِ مودریشن — همان تنظیماتِ مسیرِ دستی. اقدامِ مخرب (spam/reject)
        // با اطمینانِ پایین اعمال نمی‌شود و برای بازبینیِ انسان «در انتظار» می‌ماند.
        $modMode = (string) SiteSetting::get('ai_moderation_mode', 'assist');
        $modThreshold = (float) SiteSetting::get('ai_moderation_auto_threshold', '0.85');

        $c = $this->handleComments($reply, $moderation, $limit, $days, $publish, $author, $modMode, $modThreshold);
        $q = $this->handleQuestions($reply, $moderation, $limit, $days, $publish, $modMode, $modThreshold);

        $this->info(
            "کامنت: {$c['moderated']} مودریت، {$c['replied']} پاسخ، {$c['skipped']} بی‌نیاز | "
            ."انجمن: {$q['moderated']} مودریت، {$q['replied']} پاسخ | "
            .'حالتِ پاسخ: '.($publish ? 'منتشرشده' : 'پیش‌نویس')
        );

        return self::SUCCESS;
    }

    /**
     * @return array{replied:int, skipped:int, moderated:int}
     */
    private function handleComments(AiReplyService $reply, AiModerationService $moderation, int $limit, int $days, bool $publish, string $author, string $modMode, float $modThreshold): array
    {
        $morph = (new Comment)->getMorphClass();
        $table = (new Comment)->getTable();

        $comments = Comment::query()
            ->with('commentable')
            ->whereIn('status', [Comment::STATUS_PENDING, Comment::STATUS_APPROVED])
            ->whereNull('parent_id')
            ->where('is_admin_reply', false)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereDoesntHave('replies', fn ($q) => $q->where('is_admin_reply', true))
            // idempotency: فقط خروجیِ «موفق» (پاسخ داده/بی‌نیاز) مانعِ پردازشِ دوباره
            // است — لاگِ خطاهای گذشته نباید آیتم را برای همیشه مسدود کند.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'reply')->where('subject_type', $morph)
                ->whereIn('decision', ['reply', 'none'])
                ->whereColumn('subject_id', "{$table}.id"))
            // آیتمی که گیتِ ایمنی نگه داشته (پیشنهادِ spam/rejectِ اعمال‌نشده) منتظرِ
            // تصمیمِ انسان است — دوباره مودریت/پاسخ نمی‌شود تا توکن هدر نرود.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'moderation')->where('subject_type', $morph)
                ->where('applied', false)->whereIn('decision', ['spam', 'reject'])
                ->whereColumn('subject_id', "{$table}.id"))
            ->latest()
            ->limit($limit)
            ->get();

        $replied = 0;
        $skipped = 0;
        $moderated = 0;
        foreach ($comments as $comment) {
            $pageTitle = $this->ownerLabel($comment->commentable);
            try {
                // ۱) مودریشنِ محتوای در‌انتظار
                if ($comment->status === Comment::STATUS_PENDING) {
                    $m = $moderation->analyze((string) $comment->content, array_filter(['type' => 'نظر', 'title' => $pageTitle]));
                    if (! $m['ok']) {
                        $this->warn("کامنت #{$comment->id}: مودریشن — ".$m['error']);
                        \Illuminate\Support\Facades\Log::warning('ai_auto_reply.moderation_failed', ['type' => 'comment', 'id' => $comment->id, 'error' => $m['error']]);

                        continue;
                    }
                    // گیتِ ایمنی: اقدامِ مخربِ کم‌اطمینان اعمال نمی‌شود (در انتظارِ انسان).
                    $applied = AiModerationService::shouldAutoApply($modMode, $m['decision'], $m['confidence'], $modThreshold);
                    if ($applied) {
                        $this->applyModeration($comment, $m['decision']);
                    }
                    $this->logModeration($morph, $comment->id, $m, $applied);
                    $moderated++;
                    if (! ($applied && $m['decision'] === 'approve')) {
                        continue; // اسپم/رد یا کم‌اطمینان → بدونِ پاسخ
                    }
                }

                // ۲) پاسخ به محتوای سالم — با موضوعِ صفحه برای پاسخِ دقیق‌تر
                $res = $reply->reply((string) $comment->content, array_filter(['type' => 'نظر', 'page' => $pageTitle]));
                if (! $res['ok']) {
                    $this->warn("کامنت #{$comment->id}: پاسخ — ".$res['error']);
                    \Illuminate\Support\Facades\Log::warning('ai_auto_reply.reply_failed', ['type' => 'comment', 'id' => $comment->id, 'error' => $res['error']]);

                    continue;
                }
                if ($res['skip']) {
                    $this->logReply($morph, $comment->id, $res, 'none', false, 'نیازی به پاسخ نداشت');
                    $skipped++;

                    continue;
                }

                Comment::create([
                    'commentable_type' => $comment->commentable_type,
                    'commentable_id' => $comment->commentable_id,
                    'parent_id' => $comment->id,
                    'root_id' => $comment->root_id ?? $comment->id,
                    'user_id' => null,
                    'author_name' => $author,
                    'is_admin_reply' => true,
                    'content' => $res['text'],
                    'status' => $publish ? Comment::STATUS_APPROVED : Comment::STATUS_PENDING,
                    'approved_at' => $publish ? now() : null,
                ]);
                $this->logReply($morph, $comment->id, $res, 'reply', $publish, mb_substr((string) $res['text'], 0, 300));
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("کامنت #{$comment->id}: خطا — ".$e->getMessage());
                \Illuminate\Support\Facades\Log::warning('ai_auto_reply.exception', ['type' => 'comment', 'id' => $comment->id, 'error' => $e->getMessage()]);
            }
        }

        return ['replied' => $replied, 'skipped' => $skipped, 'moderated' => $moderated];
    }

    /**
     * @return array{replied:int, moderated:int}
     */
    private function handleQuestions(AiReplyService $reply, AiModerationService $moderation, int $limit, int $days, bool $publish, string $modMode, float $modThreshold): array
    {
        $morph = (new Question)->getMorphClass();
        $table = (new Question)->getTable();

        $questions = Question::query()
            ->whereIn('status', [Question::STATUS_PENDING, Question::STATUS_APPROVED])
            ->where('created_at', '>=', now()->subDays($days))
            ->whereDoesntHave('answers')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'reply')->where('subject_type', $morph)
                ->whereIn('decision', ['reply', 'none'])
                ->whereColumn('subject_id', "{$table}.id"))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'moderation')->where('subject_type', $morph)
                ->where('applied', false)->whereIn('decision', ['spam', 'reject'])
                ->whereColumn('subject_id', "{$table}.id"))
            ->latest()
            ->limit($limit)
            ->get();

        $replied = 0;
        $moderated = 0;
        foreach ($questions as $question) {
            try {
                if ($question->status === Question::STATUS_PENDING) {
                    $m = $moderation->analyze(trim(($question->title ?? '')."\n".($question->body ?? '')), [
                        'type' => 'سوالِ انجمن', 'title' => $question->title,
                    ]);
                    if (! $m['ok']) {
                        $this->warn("سوال #{$question->id}: مودریشن — ".$m['error']);
                        \Illuminate\Support\Facades\Log::warning('ai_auto_reply.moderation_failed', ['type' => 'question', 'id' => $question->id, 'error' => $m['error']]);

                        continue;
                    }
                    $applied = AiModerationService::shouldAutoApply($modMode, $m['decision'], $m['confidence'], $modThreshold);
                    if ($applied) {
                        $this->applyModeration($question, $m['decision']);
                    }
                    $this->logModeration($morph, $question->id, $m, $applied);
                    $moderated++;
                    if (! ($applied && $m['decision'] === 'approve')) {
                        continue;
                    }
                }

                $res = $reply->reply((string) $question->body, [
                    'type' => 'سوالِ انجمن', 'title' => $question->title, 'must_reply' => true,
                ]);
                if (! $res['ok'] || ! $res['text']) {
                    $this->warn("سوال #{$question->id}: پاسخ — ".($res['error'] ?? 'بدونِ متن'));
                    \Illuminate\Support\Facades\Log::warning('ai_auto_reply.reply_failed', ['type' => 'question', 'id' => $question->id, 'error' => $res['error'] ?? 'بدونِ متن']);

                    continue;
                }

                Answer::create([
                    'question_id' => $question->id,
                    'body' => $res['text'],
                    'expert_id' => null,
                    'is_expert_reply' => true,
                    'status' => $publish ? Answer::STATUS_APPROVED : Answer::STATUS_PENDING,
                    'approved_at' => $publish ? now() : null,
                ]);

                if ($publish) {
                    $question->answers_count = $question->answers()->where('status', Answer::STATUS_APPROVED)->count();
                    $question->saveQuietly();
                }

                $this->logReply($morph, $question->id, $res, 'reply', $publish, mb_substr((string) $res['text'], 0, 300));
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("سوال #{$question->id}: خطا — ".$e->getMessage());
                \Illuminate\Support\Facades\Log::warning('ai_auto_reply.exception', ['type' => 'question', 'id' => $question->id, 'error' => $e->getMessage()]);
            }
        }

        return ['replied' => $replied, 'moderated' => $moderated];
    }

    /**
     * اعمالِ تصمیمِ مودریشن روی وضعیتِ آیتم (کامنت/سوال).
     */
    private function applyModeration(\Illuminate\Database\Eloquent\Model $subject, string $decision): void
    {
        $status = ['approve' => 'approved', 'spam' => 'spam', 'reject' => 'rejected'][$decision] ?? 'pending';
        $subject->update([
            'status' => $status,
            'approved_at' => $decision === 'approve' ? now() : null,
        ]);
    }

    /**
     * برچسبِ صفحهٔ میزبانِ کامنت (نامِ دستگاه/برند یا عنوانِ مقاله) — برای
     * مودریشن و پاسخِ دقیق‌تر. اگر یافت نشد null.
     */
    private function ownerLabel(?\Illuminate\Database\Eloquent\Model $owner): ?string
    {
        if (! $owner) {
            return null;
        }
        $label = $owner->title ?? $owner->name ?? null;

        return is_string($label) && trim($label) !== '' ? trim($label) : null;
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function logModeration(string $morph, int $subjectId, array $m, bool $applied = true): void
    {
        AiDecisionLog::create([
            'ai_model_id' => $m['model']?->id,
            'task' => 'moderation',
            'subject_type' => $morph,
            'subject_id' => $subjectId,
            'decision' => $m['decision'],
            'confidence' => $m['confidence'],
            'reason' => $m['reason'],
            'applied' => $applied,
            'prompt_tokens' => $m['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $m['usage']['completion_tokens'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $res
     */
    private function logReply(string $morph, int $subjectId, array $res, string $decision, bool $applied, string $reason): void
    {
        AiDecisionLog::create([
            'ai_model_id' => $res['model']?->id,
            'task' => 'reply',
            'subject_type' => $morph,
            'subject_id' => $subjectId,
            'decision' => $decision,
            'reason' => $reason,
            'applied' => $applied,
            'prompt_tokens' => $res['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $res['usage']['completion_tokens'] ?? null,
            'raw_response' => ['reply' => $res['text'] ?? null],
        ]);
    }
}
