<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب دسته‌بندی‌های FAQ روی هر دستگاه.
 * هنگام render در API، تمام FAQهای منتشرشده‌ی این دسته‌بندی‌ها به‌علاوه
 * faqهای منفرد انتخاب‌شده در crm_device_faqs نمایش داده می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('taxonomy_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'taxonomy_id'], 'uk_device_faqcat');
            $table->index('device_id');
            $table->index('taxonomy_id');

            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_faq_categories');
    }
};
