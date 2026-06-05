<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pivot برای انتخاب FAQهای اختصاصی هر برند (متناظر با crm_device_faqs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_brand_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->ulid('faq_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['brand_id', 'faq_id'], 'uk_brand_faq');
            $table->index('brand_id');
            $table->index('faq_id');

            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_brand_faqs');
    }
};
