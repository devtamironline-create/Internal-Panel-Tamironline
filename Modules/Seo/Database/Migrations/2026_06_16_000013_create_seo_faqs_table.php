<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول پرسش‌های متداول (FAQ) — به‌صورت polymorphic به هر صفحهٔ seoable
 * متصل می‌شود. خروجی در /v1/seo/meta (کلید faq) و در صورت فعال‌بودن تنظیم
 * faq_schema_enabled به‌صورت FAQPage JSON-LD منتشر می‌شود (§38).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('faqable_type', 191);
            $table->unsignedBigInteger('faqable_id');
            $table->string('question', 500);
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['faqable_type', 'faqable_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_faqs');
    }
};
