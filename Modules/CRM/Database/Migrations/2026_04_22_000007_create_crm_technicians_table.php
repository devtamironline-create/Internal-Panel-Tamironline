<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_technicians', function (Blueprint $table) {
            $table->id();

            // ارتباط با کاربر (برای لاگین و دیدن سفارش‌های خودش)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // شناسه
            $table->string('tech_code', 50)->nullable()->unique();  // کد داخلی مثل T405001
            $table->string('national_code', 20)->nullable();

            // مشخصات
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('mobile', 20)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('phone_force', 20)->nullable(); // تلفن اضطراری
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();

            // آدرس
            $table->foreignId('province_id')->nullable()->constrained('crm_provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('crm_cities')->nullOnDelete();
            $table->text('address')->nullable();

            // تخصص
            $table->string('specialty')->nullable(); // تخصص (مثلاً ماشین لباسشویی)
            $table->enum('tech_type', ['regular', 'senior', 'expert', 'freelance'])->default('regular');
            $table->text('description')->nullable();

            // تصاویر (URL/path)
            $table->string('personal_image')->nullable();
            $table->string('card_image')->nullable();

            // قوانین کاری و محاسباتی
            $table->unsignedTinyInteger('commission_percent')->default(0); // درصد کمیسیون 0-100
            $table->unsignedInteger('max_concurrent_orders')->nullable();  // سقف سفارش همزمان
            $table->unsignedBigInteger('max_order_price')->nullable();     // سقف قیمت سفارش (تومان)
            $table->enum('calc_type', ['percent_of_customer', 'percent_of_total'])->default('percent_of_customer');

            // اطلاعات مالی
            $table->string('bank_sheba', 34)->nullable();     // شماره شبا
            $table->string('bank_card', 20)->nullable();      // شماره کارت
            $table->string('bank_account', 30)->nullable();   // شماره حساب

            // وضعیت
            $table->boolean('is_active')->default(true);
            $table->boolean('ready_for_delivery')->default(false); // آماده دریافت سفارش جدید

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('is_active');
            $table->index('ready_for_delivery');
            $table->index('tech_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technicians');
    }
};
