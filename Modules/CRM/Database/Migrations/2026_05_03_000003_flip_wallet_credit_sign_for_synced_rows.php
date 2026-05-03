<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحیح علامت تراکنش‌هایی که از WP wallet=1 سینک شده بودند.
 *
 * در WP، wallet=1 به‌معنای «شرکت کیف‌پول تکنسین را شارژ کرد» (علامت
 * مثبت برای تکنسین) است — اما ما به‌اشتباه آن‌ها را به WalletTxType::Credit
 * (sign=-1) نگاشت کرده بودیم. در نتیجه هر شارژ به‌صورت بدهی غول‌آسا
 * در ترازنامه ظاهر شد.
 *
 * این migration:
 *  1. type = 'credit' → 'wallet_charge' برای ردیف‌های sync‌شده (wp_id != null)
 *  2. amount را علامت می‌زند (بود -X، می‌شود +X)
 *
 * ردیف‌های دستی (wp_id IS NULL) دست‌نخورده می‌مانند چون آن‌ها واقعاً
 * «واریز تکنسین به شرکت» با علامت منفی هستند.
 *
 * پس از این migration حتماً crm:wallet:recompute-balances را اجرا کنید
 * تا balance_after و wallet_balance همهٔ تکنسین‌ها از روی amounts درست
 * بازمحاسبه شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_tech_wallet_transactions')
            ->where('type', 'credit')
            ->whereNotNull('wp_id')
            ->update([
                'type' => 'wallet_charge',
                'amount' => DB::raw('-amount'),
            ]);
    }

    public function down(): void
    {
        DB::table('crm_tech_wallet_transactions')
            ->where('type', 'wallet_charge')
            ->whereNotNull('wp_id')
            ->update([
                'type' => 'credit',
                'amount' => DB::raw('-amount'),
            ]);
    }
};
