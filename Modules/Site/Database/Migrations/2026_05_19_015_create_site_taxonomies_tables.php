<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول دسته‌بندی‌های عمومی برای faq/testimonial (و موارد بعدی)
        Schema::create('site_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // faq | testimonial
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index('type');
        });

        // پیوت faq ↔ taxonomy
        Schema::create('site_faq_taxonomies', function (Blueprint $table) {
            $table->ulid('faq_id');
            $table->unsignedBigInteger('taxonomy_id');

            $table->primary(['faq_id', 'taxonomy_id']);
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });

        // پیوت testimonial ↔ taxonomy
        Schema::create('site_testimonial_taxonomies', function (Blueprint $table) {
            $table->ulid('testimonial_id');
            $table->unsignedBigInteger('taxonomy_id');

            $table->primary(['testimonial_id', 'taxonomy_id']);
            $table->foreign('testimonial_id')->references('id')->on('testimonials')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_testimonial_taxonomies');
        Schema::dropIfExists('site_faq_taxonomies');
        Schema::dropIfExists('site_taxonomies');
    }
};
