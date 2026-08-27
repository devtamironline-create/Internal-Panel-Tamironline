<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسخهٔ قالبِ قرارداد پرسنل (۱۴۰۵/۰۶/۰۳):
 *   ۱ = قرارداد مشاوره‌ای/پروژه‌ای (نسخهٔ قبلی — پیش‌فرض)
 *   ۲ = قرارداد کار با مدت معین (کارمند کال‌سنتر)
 *
 * روی رکورد snapshot می‌شود تا تغییرِ بعدی، سندِ امضاشده را عوض نکند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_contracts', 'version')) {
                $table->unsignedTinyInteger('version')->default(1)->after('contract_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('staff_contracts', 'version')) {
                $table->dropColumn('version');
            }
        });
    }
};
