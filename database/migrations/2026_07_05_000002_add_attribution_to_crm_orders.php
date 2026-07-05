<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ذخیره‌ی داده‌ی attribution (منبعِ تبلیغاتیِ سفارش) روی رکورد سفارش.
 *
 * فرانت هنگام ثبت سفارش فیلدهایی مثل gclid و utm_* را می‌فرستد؛ این‌ها برای
 * گزارش‌گیریِ Offline Conversion در Google Ads لازم‌اند. در یک ستونِ JSON
 * نگه‌داری می‌شوند تا افزودنِ کلیدهای جدید مهاجرت نخواهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_orders', 'attribution')) {
                $t->json('attribution')->nullable()->after('introduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $t) {
            if (Schema::hasColumn('crm_orders', 'attribution')) {
                $t->dropColumn('attribution');
            }
        });
    }
};
