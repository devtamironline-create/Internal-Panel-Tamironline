<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سهمِ کیف‌پولِ مشتری در یک پرداختِ ترکیبی. amount = سهمِ درگاه، wallet_amount =
 * سهمی که از کیف‌پول کسر می‌شود. برآیندِ دو = کلِ مبلغِ فاکتور.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_payments', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_payments', 'wallet_amount')) {
                $t->bigInteger('wallet_amount')->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_payments', function (Blueprint $t) {
            if (Schema::hasColumn('crm_payments', 'wallet_amount')) {
                $t->dropColumn('wallet_amount');
            }
        });
    }
};
