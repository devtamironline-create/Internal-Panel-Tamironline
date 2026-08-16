<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لینکِ برگشت به اپ بعد از پرداخت — اپِ مشتری/تکنسین موقعِ شروعِ پرداخت
 * لینکِ خودش را می‌دهد (پس از عبور از allowlist) و صفحهٔ نتیجه بعد از
 * callback خودکار به همان برمی‌گردد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            $table->string('return_url', 500)->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            $table->dropColumn('return_url');
        });
    }
};
