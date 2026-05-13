<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جهت سینک per-technician.
 *
 *   both           — کامل دو طرفه (پیش‌فرض)
 *   wp_to_laravel  — فقط WP→Laravel (push لاراولی مسدود می‌شود)
 *   laravel_to_wp  — فقط Laravel→WP (inbound از WP مسدود می‌شود)
 *   none           — قطع کامل سینک برای این موجودیت روی این تکنسین
 *
 * این دو ستون به‌طور جداگانه برای سفارش و کیف‌پول کنترل می‌شوند چون
 * مشکلات گزارش‌شده مستقل از هم هستند (تغییر وضعیت سفارش vs.
 * صفر شدن شارژ کیف‌پول).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->enum('order_sync_direction', ['both', 'wp_to_laravel', 'laravel_to_wp', 'none'])
                ->default('both')
                ->after('ready_for_delivery');
            $table->enum('wallet_sync_direction', ['both', 'wp_to_laravel', 'laravel_to_wp', 'none'])
                ->default('both')
                ->after('order_sync_direction');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn(['order_sync_direction', 'wallet_sync_direction']);
        });
    }
};
