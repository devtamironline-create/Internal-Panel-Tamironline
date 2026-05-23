<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * یکپارچه‌سازی testimonials و site_device_reviews در یک جدول واحد site_reviews.
 *
 * هدف: داشتن یک منبع داده‌ی واحد برای «نظرات و توصیه‌نامه‌ها» با تفکیک
 * type=audio (testimonialهای صوتی که ادمین وارد می‌کند) و
 * type=text (نظرات متنی که از فرم عمومی صفحه‌ی دستگاه ارسال می‌شوند).
 *
 * این migration:
 *   1) جداول جدید site_reviews, site_review_replies, site_review_likes,
 *      site_review_devices (pivot)، site_review_taxonomies (pivot) را می‌سازد
 *   2) داده‌های موجود از جدول‌های قدیمی را کپی می‌کند
 *   3) جدول‌های قدیمی را drop می‌کند
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ۱) جداول جدید ────────────────────────────────────────────
        Schema::create('site_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('type', ['audio', 'text']);

            // FK اختیاری برای reviewهای متنی (هر review یک دستگاه)
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('device_slug', 100)->nullable(); // denormalized for query speed

            // فیلدهای مشترک
            $table->string('author_name', 80);
            $table->string('author_avatar', 500)->nullable();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->boolean('is_published')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_expert')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();

            // مخصوص audio
            $table->string('topic', 120)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();

            // مخصوص text
            $table->string('email', 120)->nullable();
            $table->text('content')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['device_slug', 'status']);
            $table->index(['type', 'is_published', 'published_at']);
            $table->index('created_at');

            $table->foreign('device_id')->references('id')->on('crm_devices')->nullOnDelete();
        });

        Schema::create('site_review_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('review_id');
            $table->string('author_name', 80);
            $table->string('author_avatar', 500)->nullable();
            $table->boolean('is_expert')->default(true);
            $table->text('content');
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
        });

        Schema::create('site_review_likes', function (Blueprint $table) {
            $table->id();
            $table->ulid('review_id');
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['review_id', 'ip']);
            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
        });

        Schema::create('site_review_devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('review_id');
            $table->unsignedBigInteger('device_id');
            $table->timestamps();

            $table->unique(['review_id', 'device_id'], 'uk_review_device');
            $table->index('review_id');
            $table->index('device_id');

            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
        });

        Schema::create('site_review_taxonomies', function (Blueprint $table) {
            $table->ulid('review_id');
            $table->unsignedBigInteger('taxonomy_id');
            $table->primary(['review_id', 'taxonomy_id']);
            $table->foreign('review_id')->references('id')->on('site_reviews')->cascadeOnDelete();
            $table->foreign('taxonomy_id')->references('id')->on('site_taxonomies')->cascadeOnDelete();
        });

        // ── ۲) کپی داده‌های موجود ────────────────────────────────────
        // 2.a) testimonials → site_reviews (type=audio)
        if (Schema::hasTable('testimonials')) {
            DB::statement("
                INSERT INTO site_reviews (
                    id, type, device_id, device_slug,
                    author_name, rating, status, is_published, is_verified, is_expert, is_featured,
                    likes, sort_order, published_at,
                    topic, audio_url, duration_seconds,
                    email, content, ip, user_agent,
                    created_at, updated_at
                )
                SELECT
                    id, 'audio', NULL, NULL,
                    customer_name, rating,
                    CASE WHEN is_published = 1 THEN 'approved' ELSE 'pending' END,
                    is_published, 0, 0, 0,
                    0, sort_order, published_at,
                    topic, audio_url, duration_seconds,
                    NULL, NULL, NULL, NULL,
                    created_at, updated_at
                FROM testimonials
            ");
        }

        // 2.b) site_device_reviews → site_reviews (type=text)
        if (Schema::hasTable('site_device_reviews')) {
            DB::statement("
                INSERT INTO site_reviews (
                    id, type, device_id, device_slug,
                    author_name, author_avatar, rating, status, is_published, is_verified, is_expert, is_featured,
                    likes, sort_order, published_at,
                    topic, audio_url, duration_seconds,
                    email, content, ip, user_agent,
                    created_at, updated_at
                )
                SELECT
                    r.id, 'text',
                    (SELECT d.id FROM crm_devices d WHERE d.slug = r.device_slug LIMIT 1),
                    r.device_slug,
                    r.author_name, r.author_avatar, r.rating, r.status,
                    CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END,
                    r.is_verified, r.is_expert, 0,
                    r.likes, 0,
                    CASE WHEN r.status = 'approved' THEN r.created_at ELSE NULL END,
                    NULL, NULL, NULL,
                    r.email, r.content, r.ip, r.user_agent,
                    r.created_at, r.updated_at
                FROM site_device_reviews r
            ");
        }

        // 2.c) replies
        if (Schema::hasTable('site_device_review_replies')) {
            DB::statement('
                INSERT INTO site_review_replies (id, review_id, author_name, author_avatar, is_expert, content, created_at, updated_at)
                SELECT id, review_id, author_name, author_avatar, is_expert, content, created_at, updated_at
                FROM site_device_review_replies
            ');
        }

        // 2.d) likes
        if (Schema::hasTable('site_device_review_likes')) {
            DB::statement('
                INSERT INTO site_review_likes (review_id, ip, created_at)
                SELECT review_id, ip, created_at
                FROM site_device_review_likes
            ');
        }

        // 2.e) testimonial-device pivot → review-device pivot
        if (Schema::hasTable('site_testimonial_devices')) {
            DB::statement('
                INSERT INTO site_review_devices (review_id, device_id, created_at, updated_at)
                SELECT testimonial_id, device_id, created_at, updated_at
                FROM site_testimonial_devices
            ');
        }

        // 2.f) testimonial-taxonomy pivot → review-taxonomy pivot
        if (Schema::hasTable('site_testimonial_taxonomies')) {
            DB::statement('
                INSERT INTO site_review_taxonomies (review_id, taxonomy_id)
                SELECT testimonial_id, taxonomy_id
                FROM site_testimonial_taxonomies
            ');
        }

        // ── ۳) FK روی site_page_testimonials را به site_reviews می‌بریم
        //        (IDها در کپی حفظ شده‌اند)
        if (Schema::hasTable('site_page_testimonials')) {
            Schema::table('site_page_testimonials', function (Blueprint $table) {
                try {
                    $table->dropForeign(['testimonial_id']);
                } catch (\Throwable $e) {
                    // FK ممکن است وجود نداشته باشد
                }
            });
            Schema::table('site_page_testimonials', function (Blueprint $table) {
                $table->foreign('testimonial_id')
                    ->references('id')
                    ->on('site_reviews')
                    ->cascadeOnDelete();
            });
        }

        // ── ۴) drop جدول‌های قدیمی (ترتیب: pivotها → child → parent) ──
        Schema::dropIfExists('site_device_review_likes');
        Schema::dropIfExists('site_device_review_replies');
        Schema::dropIfExists('site_testimonial_devices');
        Schema::dropIfExists('site_testimonial_taxonomies');
        Schema::dropIfExists('site_device_reviews');
        Schema::dropIfExists('testimonials');
    }

    public function down(): void
    {
        // بازسازی جداول قدیمی (شکل اولیه)
        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('customer_name', 80);
                $table->string('topic', 120);
                $table->unsignedTinyInteger('rating');
                $table->string('audio_url', 500)->nullable();
                $table->unsignedSmallInteger('duration_seconds')->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_device_reviews')) {
            Schema::create('site_device_reviews', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('device_slug', 100)->index();
                $table->string('author_name', 80);
                $table->string('email', 120);
                $table->string('author_avatar', 500)->nullable();
                $table->unsignedTinyInteger('rating');
                $table->text('content');
                $table->string('status', 20)->default('pending');
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_expert')->default(false);
                $table->unsignedInteger('likes')->default(0);
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
            });
        }

        // بازگرداندن داده‌ها بدون پشتیبانی کامل (best-effort)
        if (Schema::hasTable('site_reviews')) {
            DB::statement("
                INSERT INTO testimonials (id, customer_name, topic, rating, audio_url, duration_seconds, is_published, sort_order, published_at, created_at, updated_at)
                SELECT id, author_name, COALESCE(topic, ''), rating, audio_url, duration_seconds, is_published, sort_order, published_at, created_at, updated_at
                FROM site_reviews WHERE type = 'audio'
            ");

            DB::statement("
                INSERT INTO site_device_reviews (id, device_slug, author_name, email, author_avatar, rating, content, status, is_verified, is_expert, likes, ip, user_agent, created_at, updated_at)
                SELECT id, COALESCE(device_slug, ''), author_name, COALESCE(email, ''), author_avatar, rating, COALESCE(content, ''), status, is_verified, is_expert, likes, ip, user_agent, created_at, updated_at
                FROM site_reviews WHERE type = 'text'
            ");
        }

        Schema::dropIfExists('site_review_taxonomies');
        Schema::dropIfExists('site_review_devices');
        Schema::dropIfExists('site_review_likes');
        Schema::dropIfExists('site_review_replies');
        Schema::dropIfExists('site_reviews');
    }
};
