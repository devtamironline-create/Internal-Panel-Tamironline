<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب دسته‌بندی FAQ روی هر برند (متناظر با crm_device_faq_categories).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_brand_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('taxonomy_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'taxonomy_id'], 'uk_brand_faqcat');
            $table->index('brand_id');
            $table->index('taxonomy_id');

            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_brand_faq_categories');
    }
};
