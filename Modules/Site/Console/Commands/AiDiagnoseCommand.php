<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\Site\Models\AiDecisionLog;
use Modules\Site\Models\AiModel;
use Modules\Site\Models\Comment;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\SiteSetting;
use Modules\Site\Services\AiGatewayService;

/**
 * عیب‌یابیِ خطِ لولهٔ خودکارِ AI — «چرا این کامنت/سوال پاسخ نگرفته؟»
 *
 *   php artisan ai:diagnose            ← گزارشِ کامل + تستِ زندهٔ اتصال
 *   php artisan ai:diagnose --no-test  ← بدونِ مصرفِ توکن
 *
 * برای هر آیتمِ بی‌پاسخ دقیقاً می‌گوید کدام شرط جلوی پردازش را گرفته:
 * حالتِ خاموش، نبودِ مدل، خطای اتصال، گیتِ ایمنی، لاگِ قبلی، پنجرهٔ زمانی، یا صف.
 */
class AiDiagnoseCommand extends Command
{
    protected $signature = 'ai:diagnose
                            {--days=30 : پنجرهٔ زمانیِ پردازش (باید با ai:auto-reply یکی باشد)}
                            {--limit=10 : حداکثر آیتم برای نمایش در هر بخش}
                            {--no-test : بدونِ تستِ زندهٔ اتصال به مدل}';

    protected $description = 'عیب‌یابیِ AI — تنظیمات، مدل‌ها و دلیلِ پاسخ‌نگرفتنِ هر کامنت/سوال';

    public function handle(AiGatewayService $gateway): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, min((int) $this->option('limit'), 50));
        $issues = 0;

        // ─── ۱) تنظیمات ─────────────────────────────────────────────
        $replyMode = (string) SiteSetting::get('ai_reply_mode', 'off');
        $modMode = (string) SiteSetting::get('ai_moderation_mode', 'assist');
        $threshold = (string) SiteSetting::get('ai_moderation_auto_threshold', '0.85');

        $this->info('─── ۱) تنظیمات ───');
        $this->table(['کلید', 'مقدار', 'معنی'], [
            ['ai_reply_mode', $replyMode, [
                'off' => '❌ پاسخِ خودکار خاموش است — هیچ پاسخی ساخته نمی‌شود',
                'draft' => 'پیش‌نویس — پاسخ ساخته می‌شود ولی منتظرِ تأییدِ مدیر می‌ماند',
                'auto' => '✅ انتشارِ خودکار',
            ][$replyMode] ?? '⚠️ مقدارِ ناشناخته'],
            ['ai_moderation_mode', $modMode, 'assist/hybrid: اقدامِ مخربِ کم‌اطمینان نگه داشته می‌شود'],
            ['ai_moderation_auto_threshold', $threshold, 'آستانهٔ اعمالِ خودکارِ spam/reject'],
            ['ai_reply_model', (string) SiteSetting::get('ai_reply_model', ''), 'خالی = مدلِ پیش‌فرض'],
            ['ai_moderation_model', (string) SiteSetting::get('ai_moderation_model', ''), 'خالی = مدلِ پیش‌فرض'],
        ]);
        if (! in_array($replyMode, ['draft', 'auto'], true)) {
            $this->error('⛔ علتِ اصلی: ai_reply_mode فعال نیست. در پنل → هوش مصنوعی → «پاسخِ خودکار» را روشن کنید.');
            $issues++;
        }

        // ─── ۲) مدل‌ها ──────────────────────────────────────────────
        $this->info('─── ۲) مدل‌ها ───');
        $models = AiModel::query()->orderByDesc('is_default')->get();
        if ($models->isEmpty()) {
            $this->error('⛔ هیچ مدلی ثبت نشده است. در پنل → هوش مصنوعی یک مدل بسازید.');
            $issues++;
        } else {
            $rows = [];
            foreach ($models as $m) {
                try {
                    $keyState = trim((string) $m->api_key) !== '' ? '✅' : '⚠️ خالی';
                } catch (\Throwable $e) {
                    $keyState = '⛔ رمزگشایی ناموفق (APP_KEY عوض شده؟)';
                    $issues++;
                }
                $rows[] = [$m->name, $m->model_name, $m->is_active ? '✅' : '❌', $m->is_default ? '⭐' : '', $keyState];
            }
            $this->table(['نام', 'مدل', 'فعال', 'پیش‌فرض', 'کلید'], $rows);
        }

        $default = AiModel::default();
        if (! $default && $models->isNotEmpty()) {
            $this->error('⛔ هیچ مدلِ «فعالی» وجود ندارد — همه غیرفعال‌اند.');
            $issues++;
        }

        // ─── ۳) تستِ زندهٔ اتصال ────────────────────────────────────
        if (! $this->option('no-test') && $default) {
            $this->info('─── ۳) تستِ زندهٔ اتصال ───');
            $res = $gateway->testConnection($default);
            if ($res['ok']) {
                $this->line('✅ اتصال به «'.$default->name.'» موفق. پاسخ: '.trim((string) $res['content']));
            } else {
                $this->error('⛔ اتصال به «'.$default->name.'» ناموفق: '.$res['error']);
                $this->line('   ← تا اتصال برقرار نشود هیچ مودریشن/پاسخی انجام نمی‌شود (کلید/سهمیه/endpoint را چک کنید).');
                $issues++;
            }
        }

        // ─── ۴) فعالیتِ زمان‌بند ────────────────────────────────────
        $this->info('─── ۴) فعالیتِ زمان‌بند ───');
        $lastLog = AiDecisionLog::query()->latest('id')->first();
        if ($lastLog) {
            $this->line('آخرین تصمیمِ ثبت‌شدهٔ AI: '.$lastLog->created_at?->diffForHumans().' ('.$lastLog->created_at.')');
        } else {
            $this->line('هنوز هیچ لاگِ AI ثبت نشده است.');
        }
        if (in_array($replyMode, ['draft', 'auto'], true)
            && (! $lastLog || $lastLog->created_at?->lt(now()->subMinutes(30)))) {
            $this->warn('⚠️ در ۳۰ دقیقهٔ اخیر فعالیتی نبوده. اگر آیتمِ در صف دارید، cronِ زمان‌بند را چک کنید:');
            $this->line('   * * * * * php artisan schedule:run   (هر دقیقه)');
            $this->line('   یا برای تستِ فوری: php artisan ai:auto-reply --limit=10');
        }

        // آخرین خطاهای ثبت‌شده
        $errors = AiDecisionLog::query()->whereNull('decision')->latest('id')->limit(5)->get();
        if ($errors->isNotEmpty()) {
            $this->warn('آخرین خطاهای AI:');
            $this->table(['زمان', 'وظیفه', 'سوژه', 'خطا'], $errors->map(fn ($l) => [
                (string) $l->created_at, $l->task, class_basename((string) $l->subject_type).'#'.$l->subject_id,
                mb_substr((string) $l->reason, 0, 90),
            ])->all());
        }

        // ─── ۵) کامنت‌های بی‌پاسخ ───────────────────────────────────
        $this->info('─── ۵) کامنت‌های بی‌پاسخ ───');
        $this->diagnoseComments($days, $limit);

        // ─── ۶) سوال‌های بی‌پاسخِ انجمن ─────────────────────────────
        $this->info('─── ۶) سوال‌های بی‌پاسخِ انجمن ───');
        $this->diagnoseQuestions($days, $limit);

        $this->newLine();
        $issues === 0
            ? $this->info('پیکربندی سالم است — ستونِ «تشخیص» دلیلِ هر آیتم را می‌گوید.')
            : $this->error("{$issues} مشکلِ پیکربندی پیدا شد (بالا ⛔ خورده‌اند) — اول آن‌ها را برطرف کنید.");

        return self::SUCCESS;
    }

    private function diagnoseComments(int $days, int $limit): void
    {
        $morph = (new Comment)->getMorphClass();

        $items = Comment::query()
            ->whereNull('parent_id')->where('is_admin_reply', false)
            ->whereIn('status', [Comment::STATUS_PENDING, Comment::STATUS_APPROVED])
            ->whereDoesntHave('replies', fn ($q) => $q->where('is_admin_reply', true))
            ->latest()->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->line('کامنتِ بی‌پاسخی نیست. ✅');
        } else {
            $this->table(['#', 'وضعیت', 'تاریخ', 'تشخیص'], $items->map(fn ($c) => [
                $c->id, $c->status, (string) $c->created_at, $this->blockReason($morph, $c, $days),
            ])->all());
        }

        // کامنت‌هایی که پاسخِ پیش‌نویس دارند (خودشان یا پاسخ‌شان منتظرِ تأیید است).
        $withDraft = Comment::query()
            ->whereNull('parent_id')->where('is_admin_reply', false)
            ->where('status', Comment::STATUS_PENDING)
            ->whereHas('replies', fn ($q) => $q->where('is_admin_reply', true))
            ->count();
        if ($withDraft > 0) {
            $this->warn("⚠️ {$withDraft} کامنتِ در انتظار «پاسخِ پیش‌نویس» دارند — از پنل → مدیریتِ کامنت‌ها تأیید کنید.");
        }
    }

    private function diagnoseQuestions(int $days, int $limit): void
    {
        $morph = (new Question)->getMorphClass();

        $items = Question::query()
            ->whereIn('status', [Question::STATUS_PENDING, Question::STATUS_APPROVED])
            ->whereDoesntHave('answers')
            ->latest()->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->line('سوالِ بی‌پاسخی نیست. ✅');

            return;
        }
        $this->table(['#', 'وضعیت', 'تاریخ', 'تشخیص'], $items->map(fn ($q) => [
            $q->id, $q->status, (string) $q->created_at, $this->blockReason($morph, $q, $days),
        ])->all());
    }

    /**
     * دلیلِ پردازش‌نشدنِ یک آیتم — به همان ترتیبی که خطِ لوله فیلتر می‌کند.
     */
    private function blockReason(string $morph, \Illuminate\Database\Eloquent\Model $subject, int $days): string
    {
        $createdAt = $subject->getAttribute('created_at');
        if ($createdAt && $createdAt->lt(now()->subDays($days))) {
            return "قدیمی‌تر از {$days} روز — خارج از پنجرهٔ پردازش (ai:auto-reply --days)";
        }

        $done = AiDecisionLog::query()->where('task', 'reply')
            ->where('subject_type', $morph)->where('subject_id', $subject->getKey())
            ->whereIn('decision', ['reply', 'none'])->exists();
        if ($done) {
            return 'قبلاً پردازش شده (پاسخ داده شده یا بی‌نیاز از پاسخ تشخیص داده شده)';
        }

        $held = AiDecisionLog::query()->where('task', 'moderation')
            ->where('subject_type', $morph)->where('subject_id', $subject->getKey())
            ->where('applied', false)->whereIn('decision', ['spam', 'reject'])
            ->latest('id')->first();
        if ($held) {
            return '🛑 گیتِ ایمنی: پیشنهادِ «'.$held->decision.'» ('.($held->confidence !== null ? round($held->confidence * 100).'٪' : '؟').') اعمال نشد — منتظرِ تصمیمِ شما در پنل';
        }

        $fail = AiDecisionLog::query()
            ->where('subject_type', $morph)->where('subject_id', $subject->getKey())
            ->whereNull('decision')->latest('id')->first();
        if ($fail) {
            return '⚠️ آخرین تلاش خطا داشت: '.mb_substr((string) $fail->reason, 0, 80).' — دوباره تلاش می‌شود';
        }

        return '⏳ در صف — با اجرای بعدیِ ai:auto-reply (هر ۵ دقیقه) پردازش می‌شود';
    }
}
