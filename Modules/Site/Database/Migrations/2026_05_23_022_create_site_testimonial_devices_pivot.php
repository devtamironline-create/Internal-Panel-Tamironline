<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب testimonialها per-device.
 * بدون رکورد در این جدول → testimonial generic است (در همه‌ی صفحات نمایش داده می‌شود).
 * با رکورد → فقط در صفحه‌ی دستگاه(های) مرتبط دیده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_testimonial_devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('testimonial_id');
            $table->unsignedBigInteger('device_id');
            $table->timestamps();

            $table->unique(['testimonial_id', 'device_id'], 'uk_testimonial_device');
            $table->index('testimonial_id');
            $table->index('device_id');

            $table->foreign('testimonial_id')->references('id')->on('testimonials')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_testimonial_devices');
    }
};
