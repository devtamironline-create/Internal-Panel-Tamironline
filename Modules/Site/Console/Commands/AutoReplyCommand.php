<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Site\Models\AiDecisionLog;
use Modules\Site\Models\Comment;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Services\AiReplyService;

/**
 * پاسخِ خودکارِ AI به کامنت‌ها و سوال‌های انجمن.
 *
 * حالت (ai_reply_mode):
 *   - off   : غیرفعال (پیش‌فرض)
 *   - draft : AI پاسخ می‌نویسد ولی «در انتظار» می‌ماند تا مدیر تأیید کند
 *   - auto  : پاسخ بلافاصله منتشر می‌شود
 *
 * idempotent: هر آیتم فقط یک‌بار پردازش می‌شود (ردیابی با ai_decision_logs، task=reply).
 * برای اجرای دوره‌ای در scheduler ثبت شده است.
 */
class AutoReplyCommand extends Command
{
    protected $signature = 'ai:auto-reply
                            {--limit=5 : تعداد آیتم در هر اجرا (برای هر بخش)}
                            {--days=30 : فقط محتوای جدیدتر از این تعداد روز}';

    protected $description = 'پاسخِ خودکارِ AI به کامنت‌ها و سوال‌های انجمن (بر اساسِ حالتِ ai_reply_mode)';

    public function handle(AiReplyService $reply): int
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

        $c = $this->handleComments($reply, $limit, $days, $publish, $author);
        $q = $this->handleQuestions($reply, $limit, $days, $publish);

        $this->info("کامنت: {$c['replied']} پاسخ، {$c['skipped']} رد | انجمن: {$q['replied']} پاسخ"
            .' | حالت: '.($publish ? 'منتشرشده' : 'پیش‌نویس'));

        return self::SUCCESS;
    }

    /**
     * @return array{replied:int, skipped:int}
     */
    private function handleComments(AiReplyService $reply, int $limit, int $days, bool $publish, string $author): array
    {
        $morph = (new Comment)->getMorphClass();
        $table = (new Comment)->getTable();

        $comments = Comment::query()
            ->where('status', Comment::STATUS_APPROVED)
            ->whereNull('parent_id')
            ->where('is_admin_reply', false)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereDoesntHave('replies', fn ($q) => $q->where('is_admin_reply', true))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'reply')->where('subject_type', $morph)
                ->whereColumn('subject_id', "{$table}.id"))
            ->latest()
            ->limit($limit)
            ->get();

        $replied = 0;
        $skipped = 0;
        foreach ($comments as $comment) {
            try {
                $res = $reply->reply((string) $comment->content, ['type' => 'نظر']);
                if (! $res['ok']) {
                    $this->warn("کامنت #{$comment->id}: ".$res['error']);

                    continue; // بدونِ لاگ → دفعهٔ بعد دوباره تلاش
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
            }
        }

        return ['replied' => $replied, 'skipped' => $skipped];
    }

    /**
     * @return array{replied:int}
     */
    private function handleQuestions(AiReplyService $reply, int $limit, int $days, bool $publish): array
    {
        $morph = (new Question)->getMorphClass();
        $table = (new Question)->getTable();

        $questions = Question::query()
            ->where('status', Question::STATUS_APPROVED)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereDoesntHave('answers')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'reply')->where('subject_type', $morph)
                ->whereColumn('subject_id', "{$table}.id"))
            ->latest()
            ->limit($limit)
            ->get();

        $replied = 0;
        foreach ($questions as $question) {
            try {
                $res = $reply->reply((string) $question->body, [
                    'type' => 'سوالِ انجمن',
                    'title' => $question->title,
                    'must_reply' => true,
                ]);
                if (! $res['ok'] || ! $res['text']) {
                    $this->warn("سوال #{$question->id}: ".($res['error'] ?? 'بدونِ متن'));

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
                    // بازمحاسبهٔ شمارِ پاسخ‌های تأییدشده (idempotent).
                    $question->answers_count = $question->answers()->where('status', Answer::STATUS_APPROVED)->count();
                    $question->saveQuietly();
                }

                $this->logReply($morph, $question->id, $res, 'reply', $publish, mb_substr((string) $res['text'], 0, 300));
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("سوال #{$question->id}: خطا — ".$e->getMessage());
            }
        }

        return ['replied' => $replied];
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
