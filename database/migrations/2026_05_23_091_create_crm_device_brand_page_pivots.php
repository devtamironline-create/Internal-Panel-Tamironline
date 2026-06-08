<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivotهای صفحه‌ی ترکیبی (device, brand) — متناظر با
 * crm_device_faqs / crm_device_faq_categories / site_review_devices
 * ولی برای entity جدید crm_device_brand_pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_brand_page_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->ulid('faq_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'faq_id'], 'uk_dbp_faq');
            $table->index('page_id');
            $table->index('faq_id');

            $table->foreign('page_id')->references('id')->on('crm_device_brand_pages')->cascadeOnDelete();
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
        });

        Schema::create('crm_device_brand_page_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('taxonomy_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'taxonomy_id'], 'uk_dbp_faqcat');
            $table->index('page_id');
            $table->index('taxonomy_id');

            $table->foreign('page_id')->references('id')->on('crm_device_brand_pages')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });

        Schema::create('crm_device_brand_page_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->ulid('review_id');
            $table->timestamps();

            $table->unique(['page_id', 'review_id'], 'uk_dbp_review');
            $table->index('page_id');
            $table->index('review_id');

            $table->foreign('page_id')->references('id')->on('crm_device_brand_pages')->cascadeOnDelete();
            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_device_brand_page_reviews');
        Schema::dropIfExists('crm_device_brand_page_faq_categories');
        Schema::dropIfExists('crm_device_brand_page_faqs');
    }
};
