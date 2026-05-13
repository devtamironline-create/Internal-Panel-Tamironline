<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تغییر پیش‌فرض order_sync_direction به «دوطرفه» (both).
 *
 * جریان کاری واقعی: WP ابتدا سفارش را می‌سازد و به پنل می‌فرستد، سپس
 * اپراتور/تکنسین در پنل تغییراتی می‌دهد (وضعیت، تخصیص، فاکتور، …)
 * که باید به WP برگردد. برای این workflow، direction باید both باشد.
 *
 * محافظت در inbound: SyncOrderController لیست $laravelManaged دارد که
 * فیلدهای مدیریت‌شده توسط اپراتور را از WP بازنویسی نمی‌گذارد.
 *
 * wallet_sync_direction بدون تغییر باقی می‌ماند (همچنان wp_to_laravel) —
 * مشکل overwrite شارژ کیف‌پول هنوز یک‌طرفه نگه داشته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱) رکوردهای موجود که هنوز روی wp_to_laravel (پیش‌فرض migration
        //    قبلی) هستند را به both منتقل کن. انتخاب‌های دستی ادمین
        //    (laravel_to_wp / none) دست‌نخورده باقی می‌مانند.
        DB::table('crm_technicians')
            ->where('order_sync_direction', 'wp_to_laravel')
            ->update(['order_sync_direction' => 'both']);

        // ۲) تغییر default ستون — برای رکوردهای جدید آینده. wallet بدون
        //    تغییر می‌ماند.
        DB::statement("ALTER TABLE crm_technicians
            MODIFY order_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'both'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE crm_technicians
            MODIFY order_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'wp_to_laravel'");

        // عمداً مقادیر داده را بازگردانی نمی‌کنیم.
    }
};
