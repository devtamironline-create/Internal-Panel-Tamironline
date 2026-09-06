<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستونِ «حق تأهلِ ماهانه» برای جدولِ حقوقِ قراردادِ نسخه ۲.
 *
 * هم‌سبکِ بقیهٔ اعدادِ v2: مقدار موقعِ صدور روی رکورد snapshot می‌شود؛ نبودش
 * (null) → از تنظیماتِ مجموعه (contract_v2_marriage_allowance) خوانده می‌شود.
 * افزایشی و بی‌خطر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_contracts') && ! Schema::hasColumn('staff_contracts', 'v2_marriage_allowance')) {
            Schema::table('staff_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('v2_marriage_allowance')->nullable()->after('v2_monthly_benefits');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_contracts') && Schema::hasColumn('staff_contracts', 'v2_marriage_allowance')) {
            Schema::table('staff_contracts', function (Blueprint $table) {
                $table->dropColumn('v2_marriage_allowance');
            });
        }
    }
};
