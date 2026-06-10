<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول multi-address برای مشتری‌ها (اپلیکیشن موبایل).
 *
 * فیلدهای آدرس فعلی روی crm_customers (province_id, city_id, address,
 * postal_code) دست‌نخورده می‌مانند — به‌عنوان «آدرس اولیه» در نمایش‌های
 * legacy ادمین و CRM سنکرون با WP استفاده می‌شوند.
 *
 * این جدول جدید فقط برای زمانی است که کاربر از اپ موبایل چند آدرس ست می‌کند
 * (خانه، محل کار، ...). is_default روی یکی است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $t->string('label', 50)->nullable();              // «خانه» / «محل کار» / null
            $t->foreignId('province_id')->nullable()->constrained('crm_provinces')->nullOnDelete();
            $t->foreignId('city_id')->nullable()->constrained('crm_cities')->nullOnDelete();
            $t->text('full_address');                          // متن کامل آدرس
            $t->string('postal_code', 10)->nullable();         // ۱۰ رقم
            $t->string('phone', 20)->nullable();               // تلفن ثابت اختیاری
            $t->boolean('is_default')->default(false);
            $t->timestamps();

            $t->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_addresses');
    }
};
