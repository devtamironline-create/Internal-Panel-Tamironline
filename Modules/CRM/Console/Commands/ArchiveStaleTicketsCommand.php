<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Ticket;
use Modules\CRM\Models\TicketReply;

/**
 * تیکت‌هایی که آخرین پاسخ از سمت اپراتور بوده و از زمان آخرین پاسخ
 * بیش از ۷۲ ساعت گذشته را به‌صورت خودکار به وضعیت «بایگانی» (archived)
 * منتقل می‌کند.
 *
 * منطق:
 *   1) فقط تیکت‌هایی که هنوز archived یا closed نشده‌اند را در نظر می‌گیریم.
 *   2) آخرین reply هر تیکت باید sender_type=admin باشد (اپراتور پاسخ
 *      داده، منتظر تکنسین هستیم).
 *   3) created_at آخرین reply کمتر یا مساوی now()-72h باشد.
 *   4) status را به archived ست می‌کنیم و archived_at = now().
 *
 * در scheduler هر ساعت اجرا می‌شود. idempotent است (دفعه دوم چیزی پیدا
 * نمی‌کند مگر تیکت جدیدی واجد شرایط شده باشد).
 */
class ArchiveStaleTicketsCommand extends Command
{
    protected $signature = 'crm:tickets:archive-stale {--hours=72 : ساعت‌ها قبل از بایگانی}';

    protected $description = 'بایگانی خودکار تیکت‌هایی که اپراتور پاسخ داده و بدون پاسخ تکنسین مانده‌اند';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours <= 0) {
            $this->error('hours باید عدد مثبت باشد.');
            return self::INVALID;
        }

        $threshold = now()->subHours($hours);

        // سه الگو پوشش داده می‌شود:
        //   A) تیکت reply دارد و آخرین reply از admin است و قدیمی‌تر از threshold
        //      → "ما پاسخ دادیم، تکنسین جواب نداد"
        //   B) تیکت reply ندارد ولی توسط admin ساخته شده (admin_to_tech) و
        //      created_at آن قدیمی‌تر از threshold → "ما پیام دادیم، نخواند"
        //   C) تیکت در وضعیت closed است و closed_at آن قدیمی‌تر از threshold
        //      → "بسته شد، پاکسازی صف فعال"

        $patternA = Ticket::query()
            ->whereNotIn('status', ['archived', 'closed'])
            ->whereExists(function ($q) use ($threshold) {
                $q->selectRaw('1')
                    ->from('crm_ticket_replies as r1')
                    ->whereColumn('r1.ticket_id', 'crm_tickets.id')
                    ->where('r1.sender_type', 'admin')
                    ->where('r1.created_at', '<=', $threshold)
                    ->whereRaw('r1.id = (
                        SELECT MAX(r2.id)
                          FROM crm_ticket_replies r2
                         WHERE r2.ticket_id = crm_tickets.id
                    )');
            })
            ->pluck('id');

        $patternB = Ticket::query()
            ->whereNotIn('status', ['archived', 'closed'])
            ->where('direction', Ticket::DIRECTION_ADMIN_TO_TECH)
            ->where('created_at', '<=', $threshold)
            ->whereDoesntHave('replies')
            ->pluck('id');

        // الگوی C — closed_at اگر ست نشده، از updated_at به‌عنوان fallback
        // استفاده می‌کنیم (بعضی تیکت‌های قدیمی ممکن است closed_at NULL داشته باشند).
        $patternC = Ticket::query()
            ->where('status', 'closed')
            ->where(function ($q) use ($threshold) {
                $q->where('closed_at', '<=', $threshold)
                    ->orWhere(function ($qq) use ($threshold) {
                        $qq->whereNull('closed_at')->where('updated_at', '<=', $threshold);
                    });
            })
            ->pluck('id');

        $candidateIds = $patternA->merge($patternB)->merge($patternC)->unique()->values();

        if ($candidateIds->isEmpty()) {
            $this->info('هیچ تیکت واجد شرایط بایگانی پیدا نشد.');
            return self::SUCCESS;
        }

        $now = now();
        $updated = Ticket::whereIn('id', $candidateIds)->update([
            'status'      => 'archived',
            'archived_at' => $now,
            'updated_at'  => $now,
        ]);

        $this->info("{$updated} تیکت به بایگانی منتقل شد.");
        if ($this->getOutput()->isVerbose()) {
            $this->line("  - الگوی A (reply آخر از admin): {$patternA->count()}");
            $this->line("  - الگوی B (تیکت بدون reply توسط admin): {$patternB->count()}");
            $this->line("  - الگوی C (closed قدیمی‌تر از {$hours}h): {$patternC->count()}");
        }

        return self::SUCCESS;
    }
}
