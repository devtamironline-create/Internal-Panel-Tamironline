<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قیمتِ خدمات به‌ازای هر دستگاه — برای نمایشِ تعرفه در سایت و مدیریت از پنل.
 *
 * price = قیمتِ زنده (تومان، nullable برای رایگان/«بسته به مدل»)
 * compare_at_price = قیمتِ قدیم (برای مرجع/خط‌خورده، اختیاری)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('crm_devices')->cascadeOnDelete();
            $table->string('title', 300);                 // عنوانِ خدمت
            $table->unsignedBigInteger('price')->nullable();          // تومان (null = رایگان/توافقی)
            $table->unsignedBigInteger('compare_at_price')->nullable(); // قیمتِ قبلی (مرجع)
            $table->boolean('is_free')->default(false);
            $table->string('note', 200)->nullable();       // مثلاً «بسته به مدل»
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['device_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_service_prices');
    }
};
