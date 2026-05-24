<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دو ستون برای ذخیرهٔ سهم تکنسین/شرکت دقیقاً همان‌طور که در لاگ WP
 * آمده — برای سفارش‌های is_legacy_closed که نمی‌خواهیم CommissionCalculator
 * مجدداً محاسبه کند (چون فرمول WP لزوماً مثل پنل نیست؛ مثلاً WP بر اساس
 * total_invoice ضرب می‌کرد ولی پنل بر اساس price_customer).
 *
 * این فیلدها فقط برای نمایش هستند — هرگز در محاسبهٔ wallet/invoice
 * استفاده نمی‌شوند (چون is_legacy_closed جلوی هر اقدام مالی را می‌گیرد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->bigInteger('legacy_tech_share')->nullable()->after('is_legacy_closed');
            $table->bigInteger('legacy_company_share')->nullable()->after('legacy_tech_share');
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn(['legacy_tech_share', 'legacy_company_share']);
        });
    }
};
