<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوعِ خدماتِ قابلِ ارائه برای هر دستگاه (تعمیر/سرویس/نصب) — تا ادمین بتواند
 * تعیین کند هر دستگاه کدام نوع‌ها را دارد. NULL = همهٔ نوع‌ها (سازگاریِ عقب‌رو).
 * افزایشی و بی‌خطر برای production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_devices') && ! Schema::hasColumn('crm_devices', 'order_types')) {
            Schema::table('crm_devices', function (Blueprint $table) {
                $table->json('order_types')->nullable()->after('is_active_app');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_devices') && Schema::hasColumn('crm_devices', 'order_types')) {
            Schema::table('crm_devices', function (Blueprint $table) {
                $table->dropColumn('order_types');
            });
        }
    }
};
