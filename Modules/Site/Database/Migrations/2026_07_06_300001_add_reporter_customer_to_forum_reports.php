<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dedupeِ گزارشِ تخلف بر اساسِ «حساب» برای کاربرانِ واردشده — چون IPِ همهٔ
 * کاربرانِ پشتِ BFF یکی است و dedupeِ per-IP گزارشِ بقیه را بی‌صدا می‌انداخت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_reports', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_reports', 'reporter_customer_id')) {
                $t->unsignedBigInteger('reporter_customer_id')->nullable()->after('reporter_ip')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_forum_reports', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_reports', 'reporter_customer_id')) {
                $t->dropColumn('reporter_customer_id');
            }
        });
    }
};
