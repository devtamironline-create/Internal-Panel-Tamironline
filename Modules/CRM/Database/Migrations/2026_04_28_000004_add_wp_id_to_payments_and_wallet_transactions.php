<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن wp_id به crm_payments و crm_tech_wallet_transactions.
 *
 * در WP CRM یک post_type=financial چندمنظوره است: هم فاکتور، هم
 * تراکنش کیف‌پول، هم پرداخت موفق Zibal — همگی پست‌اند با متاهای
 * متفاوت. در لاراول این‌ها به سه جدول جدا تقسیم شده‌اند، اما کلید
 * تطبیق با WP همان post_id است (همان نامی که قبلاً برای crm_invoices
 * استفاده شده: wp_id).
 *
 * این ستون nullable است (رکوردهایی که در لاراول مستقیم ساخته می‌شوند
 * مرجعی در WP ندارند) و unique است تا یک پست WP فقط یک‌بار سینک شود.
 */
return new class extends Migration
{
    /** @var array<string> */
    private array $tables = [
        'crm_payments',
        'crm_tech_wallet_transactions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->unsignedBigInteger('wp_id')->nullable()->after('id');
                $t->unique('wp_id', $table . '_wp_id_unique');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table . '_wp_id_unique');
                $t->dropColumn('wp_id');
            });
        }
    }
};
