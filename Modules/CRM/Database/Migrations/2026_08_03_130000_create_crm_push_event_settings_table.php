<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تنظیماتِ هر رویدادِ پوش — چیزی که پنل ادمین ویرایش می‌کند.
 *
 * ردیف نداشتن یعنی «پیش‌فرضِ کد». عمداً موقعِ مهاجرت شش ردیف نمی‌سازیم:
 * تا وقتی ادمین دست نزده، منبعِ حقیقت همان `PushEvent` است و اگر بعداً
 * متنِ پیش‌فرض در کد بهتر شود، سایت‌هایی که چیزی تغییر نداده‌اند خودبه‌خود
 * متنِ تازه را می‌گیرند — نه اینکه پشتِ یک کپیِ کهنه در دیتابیس گیر کنند.
 *
 * ستون‌های nullable هم همین معنی را دارند: null یعنی «از کد بخوان»، نه
 * «خالی بفرست».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_push_event_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 50)->unique();

            $table->boolean('is_enabled')->default(true);

            // null ⇒ قالبِ پیش‌فرضِ `PushEvent`.
            $table->string('title', 250)->nullable();
            $table->text('body')->nullable();
            $table->unsignedSmallInteger('ttl')->nullable();

            // پنجرهٔ ساعتِ مجازِ همین رویداد. null ⇒ پنجرهٔ سراسری.
            $table->unsignedTinyInteger('quiet_start')->nullable();
            $table->unsignedTinyInteger('quiet_end')->nullable();

            // «پیامک هم بفرست» — امروز فقط ثبت می‌شود و مسیرهای پیامک
            // دست‌نخورده‌اند؛ سوئیچِ خواسته‌شده در بخش ۵.۲ سند.
            $table->boolean('send_sms')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_push_event_settings');
    }
};
