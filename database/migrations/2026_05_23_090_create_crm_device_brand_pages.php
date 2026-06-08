<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول crm_device_brand_pages — entity جداگانه برای هر صفحه‌ی ترکیبی
 * (device, brand) مثل /devices/dishwasher/samsung. هر صفحه می‌تواند
 * تمام فیلدهای hero/CTAs/steps/content/SEO/sections_enabled را
 * override کند و pivotهای faq/reviews خودش را دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_brand_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('brand_id');

            // Hero
            $table->string('eyebrow', 120)->nullable();
            $table->string('title', 200)->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('caption', 500)->nullable();

            // CTAs
            $table->string('cta_primary_label', 60)->nullable();
            $table->string('cta_primary_url', 500)->nullable();
            $table->string('cta_primary_icon', 60)->nullable();
            $table->string('cta_secondary_label', 60)->nullable();
            $table->string('cta_secondary_url', 500)->nullable();
            $table->string('cta_secondary_icon', 60)->nullable();

            // Steps section
            $table->string('steps_image_desktop', 500)->nullable();
            $table->string('steps_image_mobile', 500)->nullable();

            // Content
            $table->longText('description')->nullable();

            // SEO
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 500)->nullable();

            // Toggles
            $table->json('sections_enabled')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['device_id', 'brand_id'], 'uk_device_brand_page');
            $table->index('device_id');
            $table->index('brand_id');

            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_brand_pages');
    }
};
