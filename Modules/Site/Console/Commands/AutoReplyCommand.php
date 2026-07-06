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

        \Modules\Site\Support\AiLog::info('auto_reply.start', [
            'mode' => $mode, 'limit' => $limit, 'days' => $days,
            'moderation_mode' => $modMode, 'threshold' => $modThreshold,
        ]);

        $c = $this->handleComments($reply, $moderation, $limit, $days, $publish, $author, $modMode, $modThreshold);
        $q = $this->handleQuestions($reply, $moderation, $limit, $days, $publish, $author, $modMode, $modThreshold);
        $a = $this->handleUserAnswers($reply, $moderation, $limit, $days, $publish, $author, $modMode, $modThreshold);

        \Modules\Site\Support\AiLog::info('auto_reply.done', [
            'comments' => $c, 'questions' => $q, 'user_answers' => $a, 'published' => $publish,
        ]);

        $this->info(
            "کامنت: {$c['moderated']} مودریت، {$c['replied']} پاسخ، {$c['skipped']} بی‌نیاز | "
            ."انجمن: {$q['moderated']} مودریت، {$q['replied']} پاسخ | "
            ."پیگیری‌های کاربران: {$a['moderated']} مودریت، {$a['replied']} پاسخ، {$a['skipped']} بی‌نیاز | "
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
                        \Modules\Site\Support\AiLog::error('auto_reply.moderation_failed', ['type' => 'comment', 'id' => $comment->id, 'error' => $m['error']]);

                        continue;
                    }
                    // گیتِ ایمنی: اقدامِ مخربِ کم‌اطمینان اعمال نمی‌شود (در انتظارِ انسان).
                    $applied = AiModerationService::shouldAutoApply($modMode, $m['decision'], $m['confidence'], $modThreshold);
                    if ($applied) {
                        $this->applyModeration($comment, $m['decision'], $m['reason']);
                    }
                    $this->logModeration($morph, $comment->id, $m, $applied);
                    \Modules\Site\Support\AiLog::info('auto_reply.comment.moderated', [
                        'id' => $comment->id, 'decision' => $m['decision'],
                        'confidence' => $m['confidence'], 'applied' => $applied, 'page' => $pageTitle,
                    ]);
                    $moderated++;
                    if (! ($applied && $m['decision'] === 'approve')) {
                        continue; // اسپم/رد یا کم‌اطمینان → بدونِ پاسخ
                    }
                }

                // ۲) پاسخ به محتوای سالم — با موضوعِ صفحه برای پاسخِ دقیق‌تر
                $res = $reply->reply((string) $comment->content, array_filter(['type' => 'نظر', 'page' => $pageTitle]));
                if (! $res['ok']) {
                    $this->warn("کامنت #{$comment->id}: پاسخ — ".$res['error']);
                    \Modules\Site\Support\AiLog::error('auto_reply.reply_failed', ['type' => 'comment', 'id' => $comment->id, 'error' => $res['error']]);

                    continue;
                }
                if ($res['skip']) {
                    $this->logReply($morph, $comment->id, $res, 'none', false, 'نیازی به پاسخ نداشت');
                    \Modules\Site\Support\AiLog::info('auto_reply.comment.no_reply_needed', ['id' => $comment->id]);
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
                \Modules\Site\Support\AiLog::info('auto_reply.comment.replied', [
                    'id' => $comment->id, 'published' => $publish, 'page' => $pageTitle,
                    'reply_excerpt' => mb_substr((string) $res['text'], 0, 200),
                ]);
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("کامنت #{$comment->id}: خطا — ".$e->getMessage());
                \Modules\Site\Support\AiLog::error('auto_reply.exception', ['type' => 'comment', 'id' => $comment->id, 'error' => $e->getMessage()]);
            }
        }

        return ['replied' => $replied, 'skipped' => $skipped, 'moderated' => $moderated];
    }

    /**
     * @return array{replied:int, moderated:int}
     */
    private function handleQuestions(AiReplyService $reply, AiModerationService $moderation, int $limit, int $days, bool $publish, string $author, string $modMode, float $modThreshold): array
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
                        \Modules\Site\Support\AiLog::error('auto_reply.moderation_failed', ['type' => 'question', 'id' => $question->id, 'error' => $m['error']]);

                        continue;
                    }
                    $applied = AiModerationService::shouldAutoApply($modMode, $m['decision'], $m['confidence'], $modThreshold);
                    if ($applied) {
                        $this->applyModeration($question, $m['decision'], $m['reason']);
                    }
                    $this->logModeration($morph, $question->id, $m, $applied);
                    \Modules\Site\Support\AiLog::info('auto_reply.question.moderated', [
                        'id' => $question->id, 'decision' => $m['decision'],
                        'confidence' => $m['confidence'], 'applied' => $applied, 'title' => $question->title,
                    ]);
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
                    \Modules\Site\Support\AiLog::error('auto_reply.reply_failed', ['type' => 'question', 'id' => $question->id, 'error' => $res['error'] ?? 'بدونِ متن']);

                    continue;
                }

                $answer = Answer::create([
                    'question_id' => $question->id,
                    'body' => $res['text'],
                    // ستونِ author_name در جدول اجباری است — همان نامِ نویسندهٔ
                    // تنظیم‌شده برای پاسخ‌های AI (مثلِ مسیرِ دستیِ ادمین).
                    'author_name' => $author,
                    'expert_id' => null,
                    'is_expert_reply' => true,
                    'status' => $publish ? Answer::STATUS_APPROVED : Answer::STATUS_PENDING,
                    'approved_at' => $publish ? now() : null,
                ]);

                if ($publish) {
                    // resolution_status + answers_count — همان مسیرِ استانداردِ بقیهٔ
                    // پاسخ‌ها؛ وگرنه سوال با وجودِ پاسخ، «بی‌پاسخ» برچسب می‌خورد.
                    $question->recomputeResolution();
                    // همان اطلاع‌رسانیِ ایمیلیِ مسیرِ دستی — نویسندهٔ سوال باخبر شود
                    // (خودش ایمن است: تنظیم/ایمیلِ خالی → بی‌صدا رد می‌شود).
                    \Modules\Site\Support\ForumNotifier::notifyAuthorOfAnswer($question, $answer);
                }

                $this->logReply($morph, $question->id, $res, 'reply', $publish, mb_substr((string) $res['text'], 0, 300));
                \Modules\Site\Support\AiLog::info('auto_reply.question.replied', [
                    'id' => $question->id, 'published' => $publish, 'title' => $question->title,
                    'reply_excerpt' => mb_substr((string) $res['text'], 0, 200),
                ]);
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("سوال #{$question->id}: خطا — ".$e->getMessage());
                \Modules\Site\Support\AiLog::error('auto_reply.exception', ['type' => 'question', 'id' => $question->id, 'error' => $e->getMessage()]);
            }
        }

        return ['replied' => $replied, 'moderated' => $moderated];
    }

    /**
     * «پاسخ‌های کاربران» در انجمن (پیگیری‌های زیرِ سوال): مودریشن سپس در صورتِ
     * نیاز پاسخِ AI — با زمینهٔ کاملِ رشتهٔ گفتگو. تشکر/تأییدِ ساده پاسخ
     * نمی‌گیرد (NO_REPLY). پیش‌نویس‌های AI (is_expert_reply) دست نمی‌خورند.
     *
     * @return array{moderated:int, replied:int, skipped:int}
     */
    private function handleUserAnswers(AiReplyService $reply, AiModerationService $moderation, int $limit, int $days, bool $publish, string $author, string $modMode, float $modThreshold): array
    {
        $morph = (new Answer)->getMorphClass();
        $table = (new Answer)->getTable();

        $answers = Answer::query()
            ->whereIn('status', [Answer::STATUS_PENDING, Answer::STATUS_APPROVED])
            ->where('is_expert_reply', false)
            ->where('created_at', '>=', now()->subDays($days))
            // idempotency: فقط خروجیِ موفقِ reply (پاسخ داده/بی‌نیاز) مانعِ تکرار است.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'reply')->where('subject_type', $morph)
                ->whereIn('decision', ['reply', 'none'])
                ->whereColumn('subject_id', "{$table}.id"))
            // نگه‌داشتهٔ گیتِ ایمنی → منتظرِ انسان.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ai_decision_logs')
                ->where('task', 'moderation')->where('subject_type', $morph)
                ->where('applied', false)->whereIn('decision', ['spam', 'reject'])
                ->whereColumn('subject_id', "{$table}.id"))
            ->with('question:id,title,body,status,published_at,answers_count,resolution_status')
            ->latest()
            ->limit($limit)
            ->get();

        $moderated = 0;
        $replied = 0;
        $skipped = 0;
        foreach ($answers as $answer) {
            try {
                // ۱) مودریشنِ پیگیریِ در انتظار
                if ($answer->status === Answer::STATUS_PENDING) {
                    $m = $moderation->analyze((string) $answer->body, array_filter([
                        'type' => 'پاسخِ کاربر در انجمن',
                        'title' => $answer->question?->title,
                    ]));
                    if (! $m['ok']) {
                        $this->warn("پاسخِ کاربر #{$answer->id}: مودریشن — ".$m['error']);
                        \Modules\Site\Support\AiLog::error('auto_reply.moderation_failed', ['type' => 'answer', 'id' => $answer->id, 'error' => $m['error']]);

                        continue;
                    }
                    $applied = AiModerationService::shouldAutoApply($modMode, $m['decision'], $m['confidence'], $modThreshold);
                    if ($applied) {
                        $this->applyModeration($answer, $m['decision'], $m['reason']);
                        if ($m['decision'] === 'approve') {
                            $answer->question?->recomputeResolution();
                        }
                    }
                    $this->logModeration($morph, $answer->id, $m, $applied);
                    \Modules\Site\Support\AiLog::info('auto_reply.answer.moderated', [
                        'id' => $answer->id, 'decision' => $m['decision'],
                        'confidence' => $m['confidence'], 'applied' => $applied,
                    ]);
                    $moderated++;
                    if (! ($applied && $m['decision'] === 'approve')) {
                        continue; // رد/اسپم یا نگه‌داشته → بدونِ پاسخ
                    }
                }

                // ۲) پاسخ به پیگیریِ سالم — با زمینهٔ رشتهٔ گفتگو. تشکرِ ساده → NO_REPLY.
                $question = $answer->question;
                $res = $reply->reply((string) $answer->body, array_filter([
                    'type' => 'پیگیریِ کاربر در گفتگوی انجمن',
                    'title' => $question?->title,
                    'thread' => $question ? $this->buildThread($question, $answer->id) : null,
                ]));
                if (! $res['ok']) {
                    $this->warn("پاسخِ کاربر #{$answer->id}: پاسخ — ".$res['error']);
                    \Modules\Site\Support\AiLog::error('auto_reply.reply_failed', ['type' => 'answer', 'id' => $answer->id, 'error' => $res['error']]);

                    continue;
                }
                if ($res['skip'] || ! $res['text']) {
                    $this->logReply($morph, $answer->id, $res, 'none', false, 'نیازی به پاسخ نداشت');
                    \Modules\Site\Support\AiLog::info('auto_reply.answer.no_reply_needed', ['id' => $answer->id]);
                    $skipped++;

                    continue;
                }

                $aiAnswer = Answer::create([
                    'question_id' => $answer->question_id,
                    'body' => $res['text'],
                    'author_name' => $author,
                    'expert_id' => null,
                    'is_expert_reply' => true,
                    'status' => $publish ? Answer::STATUS_APPROVED : Answer::STATUS_PENDING,
                    'approved_at' => $publish ? now() : null,
                ]);
                if ($publish && $question) {
                    $question->recomputeResolution();
                    \Modules\Site\Support\ForumNotifier::notifyAuthorOfAnswer($question, $aiAnswer);
                }
                $this->logReply($morph, $answer->id, $res, 'reply', $publish, mb_substr((string) $res['text'], 0, 300));
                \Modules\Site\Support\AiLog::info('auto_reply.answer.replied', [
                    'id' => $answer->id, 'published' => $publish,
                    'reply_excerpt' => mb_substr((string) $res['text'], 0, 200),
                ]);
                $replied++;
            } catch (\Throwable $e) {
                $this->warn("پاسخِ کاربر #{$answer->id}: خطا — ".$e->getMessage());
                \Modules\Site\Support\AiLog::error('auto_reply.exception', ['type' => 'answer', 'id' => $answer->id, 'error' => $e->getMessage()]);
            }
        }

        return ['moderated' => $moderated, 'replied' => $replied, 'skipped' => $skipped];
    }

    /**
     * خلاصهٔ رشتهٔ گفتگو (سوالِ اصلی + پاسخ‌های تأییدشدهٔ قبلی) برای زمینهٔ پاسخ.
     */
    private function buildThread(Question $question, int $excludeAnswerId): string
    {
        $thread = 'سوالِ اصلی: '.$question->title."\n"
            .\Illuminate\Support\Str::limit(trim(strip_tags((string) $question->body)), 300);

        $prev = Answer::query()
            ->where('question_id', $question->id)
            ->where('status', Answer::STATUS_APPROVED)
            ->where('id', '!=', $excludeAnswerId)
            ->oldest()->limit(6)->get(['body', 'is_expert_reply']);
        foreach ($prev as $p) {
            $who = $p->is_expert_reply ? 'کارشناس' : 'کاربر';
            $thread .= "\n{$who}: ".\Illuminate\Support\Str::limit(trim(strip_tags((string) $p->body)), 300);
        }

        return $thread;
    }

    /**
     * اعمالِ تصمیمِ مودریشن روی وضعیتِ آیتم (کامنت/سوال).
     */
    private function applyModeration(\Illuminate\Database\Eloquent\Model $subject, string $decision, ?string $reason = null): void
    {
        $status = ['approve' => 'approved', 'spam' => 'spam', 'reject' => 'rejected'][$decision] ?? 'pending';
        $data = [
            'status' => $status,
            'approved_at' => $decision === 'approve' ? now() : null,
        ];
        // سوالِ انجمن بدونِ published_at در هیچ لیستِ عمومی‌ای نمایش داده نمی‌شود
        // (scopeApproved هر دو را می‌خواهد) — پس هنگامِ تأیید باید ست شود.
        if ($decision === 'approve' && in_array('published_at', $subject->getFillable(), true)) {
            $data['published_at'] = $subject->getAttribute('published_at') ?? now();
        }
        // علتِ ردِ قابلِ‌نمایش به کاربر — فرانت وضعیت + علت را نشان می‌دهد.
        if (in_array('rejection_reason', $subject->getFillable(), true)) {
            $data['rejection_reason'] = in_array($decision, ['reject', 'spam'], true)
                ? mb_substr((string) $reason, 0, 500) ?: null
                : null;
        }
        $subject->update($data);
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
