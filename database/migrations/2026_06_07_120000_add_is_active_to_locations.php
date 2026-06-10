<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن is_active به crm_provinces و crm_cities.
 *
 * API موبایل فقط استان/شهرهای is_active=true را در picker نمایش می‌دهد.
 * ادمین می‌تواند مناطق ساخته‌نشده را خاموش بگذارد. پیش‌فرض true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_provinces') && ! Schema::hasColumn('crm_provinces', 'is_active')) {
            Schema::table('crm_provinces', function (Blueprint $t) {
                $t->boolean('is_active')->default(true)->after('sort_order')->index();
            });
        }

        if (Schema::hasTable('crm_cities') && ! Schema::hasColumn('crm_cities', 'is_active')) {
            Schema::table('crm_cities', function (Blueprint $t) {
                $t->boolean('is_active')->default(true)->after('sort_order')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_provinces') && Schema::hasColumn('crm_provinces', 'is_active')) {
            Schema::table('crm_provinces', function (Blueprint $t) {
                $t->dropColumn('is_active');
            });
        }
        if (Schema::hasTable('crm_cities') && Schema::hasColumn('crm_cities', 'is_active')) {
            Schema::table('crm_cities', function (Blueprint $t) {
                $t->dropColumn('is_active');
            });
        }
    }
};
