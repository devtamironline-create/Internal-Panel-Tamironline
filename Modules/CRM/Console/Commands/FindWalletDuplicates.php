<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * پیدا کردن (و حذف اختیاری) تراکنش‌های wallet duplicate.
 *
 * علت ایجاد duplicate: زنجیرهٔ push→pull. وقتی Laravel یک تراکنش به
 * WP push می‌کند، WP یک financial post جدید با wp_id جدید می‌سازد.
 * بعد از sync بعدی، Laravel این wp_id جدید را در DB خود نمی‌بیند و
 * به‌عنوان تراکنش جدید insert می‌کند → duplicate.
 *
 * منطق تشخیص duplicate:
 *   ⌐ همان technician_id
 *   ⌐ همان type (wallet_charge/reward/penalty/...)
 *   ⌐ همان amount (دقیقاً)
 *   ⌐ created_at در فاصلهٔ کمتر از N روز (پیش‌فرض ۷)
 *
 * استراتژی نگه‌داری:
 *   ⌐ قدیمی‌ترین (کم‌ترین id) به‌عنوان «اصلی» نگه داشته می‌شود
 *   ⌐ بقیه به‌عنوان duplicate علامت‌گذاری/حذف می‌شوند
 *
 * نمونه:
 *   php artisan crm:find-wallet-duplicates                  # dry-run همه
 *   php artisan crm:find-wallet-duplicates --tech=42
 *   php artisan crm:find-wallet-duplicates --window-days=3
 *   php artisan crm:find-wallet-duplicates --apply          # حذف واقعی
 *   php artisan crm:find-wallet-duplicates --apply --recompute  # حذف + recompute
 */
class FindWalletDuplicates extends Command
{
    protected $signature = 'crm:find-wallet-duplicates
                            {--tech= : فقط برای این تکنسین (id Panel)}
                            {--window-days=7 : پنجره زمانی برای تطبیق (روز)}
                            {--apply : حذف واقعی duplicates (پیش‌فرض dry-run)}
                            {--recompute : بعد از حذف، crm:wallet:recompute-balances را هم بزن}';

    protected $description = 'پیدا کردن (و حذف) تراکنش‌های wallet duplicate که از زنجیره push→pull ایجاد شده‌اند';

    public function handle(): int
    {
        $windowDays = max(1, (int) $this->option('window-days'));
        $apply = (bool) $this->option('apply');

        $techIds = $this->option('tech')
            ? [(int) $this->option('tech')]
            : Technician::pluck('id')->all();

        if (empty($techIds)) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — پنجره: {$windowDays} روز — تکنسین‌ها: " . count($techIds));

        $totalGroups = 0;
        $totalDupes = 0;
        $totalAmount = 0;
        $samples = []; // برای نمایش

        $bar = $this->output->createProgressBar(count($techIds));
        $bar->start();

        foreach ($techIds as $techId) {
            $txs = WalletTransaction::where('technician_id', $techId)
                ->orderBy('id')
                ->get(['id', 'technician_id', 'wp_id', 'type', 'amount', 'note', 'order_id', 'created_at']);

            if ($txs->count() < 2) {
                $bar->advance();
                continue;
            }

            // group by (type, amount) — کاندیداهای duplicate
            $groups = $txs->groupBy(fn($t) => ($t->type instanceof \Modules\CRM\Enums\WalletTxType ? $t->type->value : (string) $t->type) . ':' . (int) $t->amount);

            foreach ($groups as $key => $group) {
                if ($group->count() < 2) continue;

                // مرتب بر اساس id (قدیمی‌ترین اول)
                $sorted = $group->sortBy('id')->values();
                $primary = $sorted->first();
                $primaryTime = $primary->created_at?->timestamp ?? 0;

                // candidate duplicates: همان (type, amount) که فاصله زمانی کمتر از window است
                $dupes = $sorted->slice(1)->filter(function ($t) use ($primaryTime, $windowDays) {
                    $tt = $t->created_at?->timestamp ?? 0;
                    return abs($tt - $primaryTime) <= ($windowDays * 86400);
                });

                if ($dupes->isEmpty()) continue;

                $totalGroups++;
                $totalDupes += $dupes->count();
                $totalAmount += abs((int) $primary->amount) * $dupes->count();

                if (count($samples) < 20) {
                    $samples[] = [
                        'tech_id' => $techId,
                        'primary_id' => $primary->id,
                        'primary_date' => $primary->created_at?->format('Y-m-d H:i'),
                        'type' => $primary->type instanceof \Modules\CRM\Enums\WalletTxType ? $primary->type->value : (string) $primary->type,
                        'amount' => number_format((int) $primary->amount),
                        'dup_ids' => $dupes->pluck('id')->implode(','),
                        'dup_count' => $dupes->count(),
                    ];
                }

                if ($apply) {
                    DB::table('crm_tech_wallet_transactions')
                        ->whereIn('id', $dupes->pluck('id')->all())
                        ->delete();
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("─────────────────────────────");
        $this->info("گروه‌های دارای duplicate: {$totalGroups}");
        $this->info("تعداد تراکنش‌های duplicate: {$totalDupes}");
        $this->info("جمع کل |amount| duplicateها: " . number_format($totalAmount));

        if (! empty($samples)) {
            $this->newLine();
            $this->info('نمونه‌های ۲۰ تای اول:');
            $rows = [];
            foreach ($samples as $s) {
                $rows[] = [
                    $s['tech_id'],
                    $s['primary_id'] . ' (' . $s['primary_date'] . ')',
                    $s['type'],
                    $s['amount'],
                    $s['dup_count'],
                    $s['dup_ids'],
                ];
            }
            $this->table(['tech', 'primary id+date', 'type', 'amount', 'dup#', 'dup ids'], $rows);
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود — هیچ تغییری اعمال نشد.');
            $this->line('برای حذف واقعی: php artisan crm:find-wallet-duplicates --apply --recompute');
        } else {
            $this->newLine();
            $this->info('✓ duplicates حذف شدند.');
            if ($this->option('recompute')) {
                $this->info('در حال recompute balances...');
                Artisan::call('crm:wallet:recompute-balances', [], $this->getOutput());
            } else {
                $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
            }
        }

        return self::SUCCESS;
    }
}
