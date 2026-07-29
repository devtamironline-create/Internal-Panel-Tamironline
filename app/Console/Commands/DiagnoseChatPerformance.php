<?php

namespace App\Console\Commands;

use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * چرا پنل کند شده؟ — اندازه‌گیریِ کوئری‌های چت روی دادهٔ واقعی.
 *
 * سه کوئریِ چت با هم پنل را زمین می‌زنند:
 *
 *   ۱) شمارشِ بجِ سایدبار — روی *هر رندرِ صفحه* اجرا می‌شود، پس کندی‌اش
 *      به همهٔ صفحات سرایت می‌کند، نه فقط پیام‌رسان.
 *   ۲) /admin/chat/tick — هر ۱۵ ثانیه در هر تب.
 *   ۳) /admin/chat/unread-recent — هر ۵ ثانیه در هر تب.
 *
 * هر سه روی `conversation_participants` با شرطِ `user_id` فیلتر می‌کنند،
 * ولی تنها ایندکسِ آن جدول `(conversation_id, user_id)` است. ستونِ اولِ
 * ایندکس conversation_id است، پس جست‌وجو بر اساسِ user_id از آن استفاده
 * نمی‌کند و جدول کامل پیمایش می‌شود.
 */
class DiagnoseChatPerformance extends Command
{
    protected $signature = 'chat:diagnose:performance {--user= : شناسهٔ کاربر (پیش‌فرض: پرترافیک‌ترین اپراتور)}';

    protected $description = 'اندازه‌گیری زمان کوئری‌های چت و بررسی وجود ایندکس‌های لازم';

    public function handle(): int
    {
        $this->sizes();
        $this->indexes();

        $userId = (int) ($this->option('user') ?: $this->busiestUser());
        if (! $userId) {
            $this->warn('کاربری برای اندازه‌گیری پیدا نشد.');

            return self::SUCCESS;
        }

        $this->timings($userId);

        return self::SUCCESS;
    }

    private function sizes(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>── اندازهٔ جدول‌ها ──</>');

        $rows = [];
        foreach (['messages', 'conversation_participants', 'conversations', 'message_reads'] as $table) {
            $rows[] = [$table, number_format((int) DB::table($table)->count())];
        }

        $this->table(['جدول', 'تعداد ردیف'], $rows);
    }

    private function indexes(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>── ایندکس‌های کلیدی ──</>');

        $participantIndexes = $this->indexColumns('conversation_participants');
        $messageIndexes = $this->indexColumns('messages');

        $this->line('  conversation_participants: '.(implode(' · ', $participantIndexes) ?: '—'));
        $this->line('  messages: '.(implode(' · ', $messageIndexes) ?: '—'));

        // ایندکسی که ستونِ *اولش* user_id باشد؛ فقط این برای فیلترِ
        // user_id قابلِ استفاده است.
        $hasUserFirst = collect($participantIndexes)
            ->contains(fn ($cols) => str_starts_with($cols, 'user_id'));

        $this->newLine();
        if ($hasUserFirst) {
            $this->info('  ✓ conversation_participants روی user_id ایندکس دارد.');
        } else {
            $this->error('  ⚠ conversation_participants روی user_id ایندکس ندارد — هر سه کوئریِ چت جدول را کامل می‌پیمایند.');
        }
    }

    /** @return array<int, string> نامِ ستون‌های هر ایندکس، به ترتیب */
    private function indexColumns(string $table): array
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}`");
        } catch (\Throwable $e) {
            return ['(خواندن ایندکس ممکن نشد: '.$e->getMessage().')'];
        }

        $byName = [];
        foreach ($rows as $row) {
            $byName[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
        }

        $out = [];
        foreach ($byName as $columns) {
            ksort($columns);
            $out[] = implode(',', $columns);
        }

        return $out;
    }

    private function busiestUser(): int
    {
        return (int) (DB::table('conversation_participants')
            ->whereNull('left_at')
            ->select('user_id', DB::raw('COUNT(*) as c'))
            ->groupBy('user_id')
            ->orderByDesc('c')
            ->value('user_id') ?? 0);
    }

    private function timings(int $userId): void
    {
        $user = User::find($userId);

        $this->newLine();
        $this->line('<fg=cyan>── زمانِ اجرا برای کاربر #'.$userId
            .' ('.($user?->full_name ?? '—').') ──</>');

        $results = [
            // عمداً countUnreadFor و نه unreadCountFor: دومی از کش
            // می‌خواند و عددِ بی‌معنایی گزارش می‌کرد.
            ['بجِ سایدبار — بدونِ کش', $this->time(fn () => Message::countUnreadFor($userId))],
            ['بجِ سایدبار — با کش (رفتارِ واقعی)', $this->time(fn () => Message::unreadCountFor($userId))],
            ['tick — بیشینهٔ شناسه', $this->time(fn () => $this->tickBase($userId)->max('messages.id'))],
            ['tick — شمارشِ نخوانده', $this->time(fn () => $this->tickBase($userId)
                ->where(function ($q) {
                    $q->whereNull('p.last_read_at')
                        ->orWhereColumn('messages.created_at', '>', 'p.last_read_at');
                })->count())],
            ['unread-recent — شناسهٔ گفتگوها', $this->time(fn () => DB::table('conversation_participants')
                ->where('user_id', $userId)->whereNull('left_at')->pluck('conversation_id'))],
        ];

        $this->table(
            ['کوئری', 'زمان'],
            array_map(fn ($r) => [$r[0], number_format($r[1], 1).' میلی‌ثانیه'], $results)
        );

        $total = array_sum(array_column($results, 1));
        $this->newLine();
        $this->line('  جمعِ یک «ضربان» کامل: '.number_format($total, 1).' میلی‌ثانیه');

        if ($results[0][1] > 50) {
            $this->error('  ⚠ بجِ سایدبار روی هر رندرِ صفحه اجرا می‌شود — این عدد مستقیماً به همهٔ صفحات اضافه می‌شود.');
        }
    }

    private function tickBase(int $userId)
    {
        return Message::query()
            ->join('conversation_participants as p', function ($join) use ($userId) {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')
                    ->where('p.user_id', '=', $userId)
                    ->whereNull('p.left_at');
            })
            ->whereNull('messages.deleted_at')
            ->where('messages.user_id', '!=', $userId);
    }

    private function time(callable $fn): float
    {
        $start = microtime(true);
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->warn('  کوئری خطا داد: '.$e->getMessage());
        }

        return (microtime(true) - $start) * 1000;
    }
}
