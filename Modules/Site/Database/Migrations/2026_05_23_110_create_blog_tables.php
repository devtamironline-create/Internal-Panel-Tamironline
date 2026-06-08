<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ماژول بلاگ — جداول اصلی:
 *   - site_blog_topics: تاپیک‌های مقالات (عیب‌یابی، نگهداری، ...) با badge color
 *   - site_blog_articles: مقالات با cover/excerpt/HTML content
 *   - ۳ pivot: article↔topic، article↔device، article↔brand
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_blog_topics', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->string('icon', 60)->nullable();         // lucide kebab key
            $table->string('color_bg', 9)->nullable();      // hex bg (#fff1f2)
            $table->string('color_fg', 9)->nullable();      // hex text color
            $table->string('color_border', 9)->nullable();  // hex border
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('site_blog_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('title', 250);
            $table->string('excerpt', 600)->nullable();
            $table->longText('content')->nullable();             // HTML from TinyMCE
            $table->string('cover_image', 500)->nullable();
            $table->string('cover_color', 9)->nullable();        // hex bg for SVG fallback
            $table->unsignedSmallInteger('read_time_minutes')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title', 250)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('site_blog_article_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('topic_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->primary(['article_id', 'topic_id']);
            $table->foreign('article_id')->references('id')->on('site_blog_articles')->cascadeOnDelete();
            $table->foreign('topic_id')->references('id')->on('site_blog_topics')->cascadeOnDelete();
        });

        Schema::create('site_blog_article_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->primary(['article_id', 'device_id']);
            $table->foreign('article_id')->references('id')->on('site_blog_articles')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
        });

        Schema::create('site_blog_article_brands', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->primary(['article_id', 'brand_id']);
            $table->foreign('article_id')->references('id')->on('site_blog_articles')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_blog_article_brands');
        Schema::dropIfExists('site_blog_article_devices');
        Schema::dropIfExists('site_blog_article_topics');
        Schema::dropIfExists('site_blog_articles');
        Schema::dropIfExists('site_blog_topics');
    }
};
