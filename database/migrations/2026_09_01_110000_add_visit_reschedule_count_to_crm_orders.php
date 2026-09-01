<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شمارندهٔ تغییرِ «زمانِ مراجعه» توسطِ تکنسین. تکنسین حداکثر ۲ بار می‌تواند
 * زمانِ مراجعه را تغییر دهد؛ بعد از آن برای دسترسیِ بیشتر باید ادمین
 * شمارنده را صفر کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_orders') || Schema::hasColumn('crm_orders', 'visit_reschedule_count')) {
            return;
        }

        Schema::table('crm_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('visit_reschedule_count')->default(0)->after('visit_scheduled_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'visit_reschedule_count')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn('visit_reschedule_count');
            });
        }
    }
};
