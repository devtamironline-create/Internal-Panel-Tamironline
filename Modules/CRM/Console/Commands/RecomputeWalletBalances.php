<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * بازخوانی balance_after هر تراکنش کیف‌پول و wallet_balance هر تکنسین
 * از روی running sum واقعی amounts. لازمه پس از تغییر منطق محاسبهٔ
 * مبلغ یا دسته‌بندی تراکنش‌ها (مثل وقتی reward/penalty از wallet_pay
 * به total_invoice منتقل شد) — تا داده‌های قدیمی به ترتیب درست
 * بازنویسی شوند.
 *
 * Idempotent: می‌توان هرچندبار اجرا کرد بدون اثر منفی.
 */
class RecomputeWalletBalances extends Command
{
    protected $signature = 'crm:wallet:recompute-balances
                             {--technician= : فقط برای یک تکنسین (id) — وگرنه همه}';

    protected $description = 'بازخوانی balance_after هر تراکنش کیف‌پول و wallet_balance تکنسین‌ها به ترتیب id (idempotent).';

    public function handle(): int
    {
        $onlyId = $this->option('technician');

        $query = Technician::query();
        if ($onlyId !== null) {
            $query->where('id', (int) $onlyId);
        }

        $totalTechs = (clone $query)->count();
        $this->info("بازخوانی برای {$totalTechs} تکنسین آغاز شد...");

        $bar = $this->output->createProgressBar($totalTechs);
        $bar->start();

        $updatedRows = 0;
        $touchedTechs = 0;

        $query->select(['id', 'wallet_balance'])->chunkById(100, function ($techs) use (&$updatedRows, &$touchedTechs, $bar) {
            foreach ($techs as $tech) {
                $running = 0;
                $lastBalance = 0;

                WalletTransaction::where('technician_id', $tech->id)
                    ->orderBy('id')
                    ->select(['id', 'amount', 'balance_after'])
                    ->chunkById(500, function ($txs) use (&$running, &$lastBalance, &$updatedRows) {
                        foreach ($txs as $tx) {
                            $running += (int) $tx->amount;
                            if ((int) $tx->balance_after !== $running) {
                                DB::table('crm_tech_wallet_transactions')
                                    ->where('id', $tx->id)
                                    ->update(['balance_after' => $running]);
                                $updatedRows++;
                            }
                            $lastBalance = $running;
                        }
                    });

                if ((int) $tech->wallet_balance !== $lastBalance) {
                    DB::table('crm_technicians')
                        ->where('id', $tech->id)
                        ->update(['wallet_balance' => $lastBalance]);
                    $touchedTechs++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("انجام شد. {$updatedRows} ردیف balance_after و {$touchedTechs} تکنسین به‌روزرسانی شد.");

        return self::SUCCESS;
    }
}
