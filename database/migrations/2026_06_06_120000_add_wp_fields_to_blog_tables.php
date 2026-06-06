<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون‌های لازم برای import مقالات از WordPress.
 *
 * idempotent: همه ستون‌ها nullable + در صورت وجود رد می‌شود.
 * هیچ داده‌ی موجودی تغییر نمی‌کند.
 *
 *   site_blog_articles.wp_id          → unique idx برای re-import امن
 *   site_blog_articles.author_name    → نام نویسنده WP (آزاد، بدون FK)
 *   site_blog_articles.original_url   → URL قدیمی برای 301 redirect
 *   site_blog_topics.wp_term_id       → unique idx برای match category قدیمی
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_blog_articles')) {
            Schema::table('site_blog_articles', function (Blueprint $t) {
                if (! Schema::hasColumn('site_blog_articles', 'wp_id')) {
                    $t->unsignedBigInteger('wp_id')->nullable()->after('id');
                    $t->unique('wp_id', 'site_blog_articles_wp_id_unique');
                }
                if (! Schema::hasColumn('site_blog_articles', 'author_name')) {
                    $t->string('author_name', 120)->nullable()->after('meta_description');
                }
                if (! Schema::hasColumn('site_blog_articles', 'original_url')) {
                    $t->string('original_url', 500)->nullable()->after('author_name');
                }
            });
        }

        if (Schema::hasTable('site_blog_topics')) {
            Schema::table('site_blog_topics', function (Blueprint $t) {
                if (! Schema::hasColumn('site_blog_topics', 'wp_term_id')) {
                    $t->unsignedBigInteger('wp_term_id')->nullable()->after('id');
                    $t->unique('wp_term_id', 'site_blog_topics_wp_term_id_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_blog_articles')) {
            Schema::table('site_blog_articles', function (Blueprint $t) {
                if (Schema::hasColumn('site_blog_articles', 'original_url')) {
                    $t->dropColumn('original_url');
                }
                if (Schema::hasColumn('site_blog_articles', 'author_name')) {
                    $t->dropColumn('author_name');
                }
                if (Schema::hasColumn('site_blog_articles', 'wp_id')) {
                    try {
                        $t->dropUnique('site_blog_articles_wp_id_unique');
                    } catch (\Throwable $e) {
                    }
                    $t->dropColumn('wp_id');
                }
            });
        }

        if (Schema::hasTable('site_blog_topics')) {
            Schema::table('site_blog_topics', function (Blueprint $t) {
                if (Schema::hasColumn('site_blog_topics', 'wp_term_id')) {
                    try {
                        $t->dropUnique('site_blog_topics_wp_term_id_unique');
                    } catch (\Throwable $e) {
                    }
                    $t->dropColumn('wp_term_id');
                }
            });
        }
    }
};
