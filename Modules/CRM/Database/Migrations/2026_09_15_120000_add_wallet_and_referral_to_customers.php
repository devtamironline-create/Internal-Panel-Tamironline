<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کیف‌پول و سیستمِ معرفِ مشتری روی crm_customers:
 *   - wallet_balance: موجودیِ denormalizedِ کیف‌پول (تومان، علامت‌دار — همیشه ≥۰).
 *   - referred_by: مشتری‌ای که این کاربر را معرفی کرده (کدِ معرف = موبایلِ معرف).
 *   - referral_rewarded_at: مهرِ زمانِ پرداختِ پاداشِ معرف (گاردِ یک‌بار پرداخت).
 *
 * «کدِ معرف» ستونِ جدا ندارد چون دقیقاً همان موبایلِ یکتای مشتری است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_customers', 'wallet_balance')) {
                $t->bigInteger('wallet_balance')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('crm_customers', 'referred_by')) {
                $t->foreignId('referred_by')->nullable()->after('wallet_balance')
                    ->constrained('crm_customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('crm_customers', 'referral_rewarded_at')) {
                $t->timestamp('referral_rewarded_at')->nullable()->after('referred_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $t) {
            if (Schema::hasColumn('crm_customers', 'referred_by')) {
                $t->dropConstrainedForeignId('referred_by');
            }
            foreach (['wallet_balance', 'referral_rewarded_at'] as $col) {
                if (Schema::hasColumn('crm_customers', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
