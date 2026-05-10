<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نگه‌داری wp_id کاربر تکنسین روی سفارش — حتی اگر تکنسین هنوز در پنل
 * Laravel sync نشده باشد. این‌طور وقتی sync تکنسین انجام می‌شود،
 * سفارش‌های یتیم می‌توانند با backfill به تکنسین وصل شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('technician_wp_id')
                ->nullable()
                ->after('technician_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropIndex(['technician_wp_id']);
            $table->dropColumn('technician_wp_id');
        });
    }
};
