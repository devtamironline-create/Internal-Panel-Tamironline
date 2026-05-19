<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قفل کردن داده‌های تکنسین در سمت Laravel — از این لحظه به بعد، هر
 * inbound از WP CRM برای تکنسین‌های موجود نادیده گرفته می‌شود. فقط
 * Laravel منبع حقیقت تکنسین‌ها است. می‌توان بعداً در /admin/crm/sync
 * این flag را خاموش کرد.
 *
 * چرا: ادمین همه تکنسین‌ها را در پنل لاراول وارد و تنظیم کرد. از این
 * لحظه نمی‌خواهد WP CRM با هر تغییری Laravel را override کند.
 *
 * زمان قفل در tech_data_locked_at ثبت می‌شود تا قابل پیگیری باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        // اگر crm_settings جدول key-value است (مثل قبلی‌ها)
        $now = now()->toDateTimeString();

        DB::table('crm_settings')->updateOrInsert(
            ['key' => 'tech_data_locked'],
            ['value' => '1', 'updated_at' => $now]
        );

        DB::table('crm_settings')->updateOrInsert(
            ['key' => 'tech_data_locked_at'],
            ['value' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'tech_data_locked')->update(['value' => '0']);
        DB::table('crm_settings')->where('key', 'tech_data_locked_at')->delete();
    }
};
