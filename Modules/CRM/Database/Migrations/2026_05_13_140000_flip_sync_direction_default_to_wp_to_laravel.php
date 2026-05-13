<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تغییر پیش‌فرض جهت سینک از «دوطرفه» به «فقط از WP به پنل» — برای همه
 * تکنسین‌ها. ادمین به‌صورت دستی برای هر تکنسینی که نیاز باشد جهت را
 * به both / laravel_to_wp تغییر می‌دهد.
 *
 * منطق: WP منبع حقیقت اصلی است و سینک پنل→WP فقط در موارد خاص فعال
 * می‌شود تا از overwrite ناخواسته‌ای که گزارش شده (مثلاً صفر شدن
 * شارژ کیف‌پول) جلوگیری شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱) مهاجرت رکوردهای موجود که هنوز روی both هستند.
        DB::table('crm_technicians')
            ->where('order_sync_direction', 'both')
            ->update(['order_sync_direction' => 'wp_to_laravel']);

        DB::table('crm_technicians')
            ->where('wallet_sync_direction', 'both')
            ->update(['wallet_sync_direction' => 'wp_to_laravel']);

        // ۲) تغییر default ستون — برای رکوردهای جدیدی که در آینده ساخته
        //    می‌شوند بدون اینکه فیلد ست شود. روی MySQL با raw DDL نوشته
        //    می‌شود تا نیاز به doctrine/dbal نداشته باشد و enum بدون
        //    تخریب داده اصلاح شود.
        DB::statement("ALTER TABLE crm_technicians
            MODIFY order_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'wp_to_laravel'");
        DB::statement("ALTER TABLE crm_technicians
            MODIFY wallet_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'wp_to_laravel'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE crm_technicians
            MODIFY order_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'both'");
        DB::statement("ALTER TABLE crm_technicians
            MODIFY wallet_sync_direction ENUM('both','wp_to_laravel','laravel_to_wp','none')
            NOT NULL DEFAULT 'both'");

        // عمداً مقادیر داده را بازگردانی نمی‌کنیم — انتخاب‌های دستی
        // ادمین نباید با rollback از بین برود.
    }
};
