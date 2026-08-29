<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برابریِ محتوایِ صفحاتِ سئوِ شهری با صفحاتِ اصلی (فاز B): همان فیلدهای
 * hero/CTA/مراحل/سکشن‌ها + روابطِ FAQ/دسته‌بندیِ FAQ/نظرات که صفحهٔ
 * ترکیبیِ دستگاه+برند دارد، تا ادمین محتوای صفحهٔ شهری را کامل ویرایش کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_city_pages', function (Blueprint $table) {
            foreach ([
                'eyebrow', 'subtitle', 'caption',
                'cta_primary_label', 'cta_primary_url', 'cta_primary_icon',
                'cta_secondary_label', 'cta_secondary_url', 'cta_secondary_icon',
                'steps_image_desktop', 'steps_image_mobile', 'meta_title',
            ] as $col) {
                if (! Schema::hasColumn('crm_city_pages', $col)) {
                    $table->string($col, 500)->nullable();
                }
            }
            if (! Schema::hasColumn('crm_city_pages', 'hero_image')) {
                $table->json('hero_image')->nullable();
            }
            if (! Schema::hasColumn('crm_city_pages', 'sections_enabled')) {
                $table->json('sections_enabled')->nullable();
            }
        });

        if (! Schema::hasTable('crm_city_page_faqs')) {
            Schema::create('crm_city_page_faqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->ulid('faq_id');
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['page_id', 'faq_id'], 'uk_cityp_faq');
                $table->index('page_id');
            });
        }

        if (! Schema::hasTable('crm_city_page_faq_categories')) {
            Schema::create('crm_city_page_faq_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->unsignedBigInteger('taxonomy_id');
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['page_id', 'taxonomy_id'], 'uk_cityp_faqcat');
                $table->index('page_id');
            });
        }

        if (! Schema::hasTable('crm_city_page_reviews')) {
            Schema::create('crm_city_page_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->ulid('review_id');
                $table->timestamps();
                $table->unique(['page_id', 'review_id'], 'uk_cityp_review');
                $table->index('page_id');
                $table->index('review_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_city_page_faqs');
        Schema::dropIfExists('crm_city_page_faq_categories');
        Schema::dropIfExists('crm_city_page_reviews');

        Schema::table('crm_city_pages', function (Blueprint $table) {
            foreach ([
                'eyebrow', 'subtitle', 'caption',
                'cta_primary_label', 'cta_primary_url', 'cta_primary_icon',
                'cta_secondary_label', 'cta_secondary_url', 'cta_secondary_icon',
                'steps_image_desktop', 'steps_image_mobile', 'meta_title',
                'hero_image', 'sections_enabled',
            ] as $col) {
                if (Schema::hasColumn('crm_city_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
