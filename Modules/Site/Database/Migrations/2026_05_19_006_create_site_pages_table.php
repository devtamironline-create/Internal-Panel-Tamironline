<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_page_testimonials', function (Blueprint $table) {
            $table->ulid('page_id');
            $table->ulid('testimonial_id');
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['page_id', 'testimonial_id']);
            $table->foreign('page_id')->references('id')->on('site_pages')->cascadeOnDelete();
            $table->foreign('testimonial_id')->references('id')->on('testimonials')->cascadeOnDelete();
        });

        Schema::create('site_page_faqs', function (Blueprint $table) {
            $table->ulid('page_id');
            $table->ulid('faq_id');
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['page_id', 'faq_id']);
            $table->foreign('page_id')->references('id')->on('site_pages')->cascadeOnDelete();
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_faqs');
        Schema::dropIfExists('site_page_testimonials');
        Schema::dropIfExists('site_pages');
    }
};
