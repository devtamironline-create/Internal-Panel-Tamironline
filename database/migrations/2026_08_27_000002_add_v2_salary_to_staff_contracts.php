<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اعدادِ پایهٔ جدولِ حقوقِ قراردادِ نسخه ۲ (کارمند کال‌سنتر) — ۱۴۰۵/۰۶/۰۴.
 * سه عددِ پایه (به ریال) که بقیهٔ جدول از آن‌ها محاسبه می‌شود، روی رکورد
 * snapshot می‌شوند تا قراردادِ امضاشده با تغییرِ بعدیِ پیش‌فرض‌ها عوض نشود:
 *   - دستمزد روزانه
 *   - پایه سنوات روزانه
 *   - مزایای ماهانه مشمول
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_contracts', 'v2_daily_wage')) {
                $table->unsignedBigInteger('v2_daily_wage')->nullable()->after('promissory_serial');
            }
            if (! Schema::hasColumn('staff_contracts', 'v2_daily_seniority')) {
                $table->unsignedBigInteger('v2_daily_seniority')->nullable()->after('v2_daily_wage');
            }
            if (! Schema::hasColumn('staff_contracts', 'v2_monthly_benefits')) {
                $table->unsignedBigInteger('v2_monthly_benefits')->nullable()->after('v2_daily_seniority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            foreach (['v2_daily_wage', 'v2_daily_seniority', 'v2_monthly_benefits'] as $col) {
                if (Schema::hasColumn('staff_contracts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
