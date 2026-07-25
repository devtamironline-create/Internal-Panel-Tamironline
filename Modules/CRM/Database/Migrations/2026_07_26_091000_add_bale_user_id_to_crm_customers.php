<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نگاشتِ کاربرِ بله ↔ مشتری — برای احرازِ مینی‌اپِ بله (initData). بعد از
 * اولین ورودِ موفق با شمارهٔ به‌اشتراک‌گذاشته‌شده، ورودهای بعدی حتی بدونِ
 * شماره هم با همین شناسه انجام می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_customers', 'bale_user_id')) {
                $table->unsignedBigInteger('bale_user_id')->nullable()->index('crm_customers_bale_uid_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            if (Schema::hasColumn('crm_customers', 'bale_user_id')) {
                $table->dropIndex('crm_customers_bale_uid_idx');
                $table->dropColumn('bale_user_id');
            }
        });
    }
};
