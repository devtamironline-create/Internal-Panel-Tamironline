<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «منبعِ ثبتِ سفارش» (نحوهٔ آشنایی/کانال) — طبقِ نامهٔ تیمِ رباتِ بله.
 * مقادیر: app | web | bale_bot | bale_miniapp (+ آینده). nullable → سفارش‌های
 * قدیمی و کلاینت‌هایی که نمی‌فرستند دست‌نخورده می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_orders', 'source')) {
                $table->string('source', 30)->nullable()->after('source_of_truth')->index('crm_orders_source_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_orders', 'source')) {
                $table->dropIndex('crm_orders_source_idx');
                $table->dropColumn('source');
            }
        });
    }
};
