<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول متای سئوِ پلی‌مورفیک — هر رکورد به یک موجودیت (صفحه/مقاله/برند/
 * دستگاه/…) از طریق (seoable_type, seoable_id) وصل می‌شود. تمام فیلدها
 * nullable هستند چون «مقدار نهایی» با سطح‌بندی resolver از قالب نوع و
 * تنظیم سراسری پر می‌شود (این جدول فقط override در سطح آیتم است).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');

            // متای پایه
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('focus_keywords')->nullable();
            $table->string('canonical', 500)->nullable();

            // Robots meta (هر پرچم مستقل؛ resolver با پیش‌فرض نوع/سراسری ادغام می‌کند)
            $table->boolean('robots_noindex')->nullable();
            $table->boolean('robots_nofollow')->nullable();
            $table->boolean('robots_noarchive')->nullable();
            $table->boolean('robots_noimageindex')->nullable();
            $table->boolean('robots_nosnippet')->nullable();
            $table->boolean('robots_notranslate')->nullable();
            $table->integer('robots_max_snippet')->nullable();
            $table->string('robots_max_image_preview', 20)->nullable(); // none|standard|large
            $table->integer('robots_max_video_preview')->nullable();

            // Open Graph
            $table->string('og_title', 255)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('og_type', 50)->nullable();

            // Twitter Card
            $table->string('twitter_card', 30)->nullable(); // summary|summary_large_image|app|player
            $table->string('twitter_title', 255)->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image', 500)->nullable();

            // متفرقه
            $table->string('breadcrumb_title', 255)->nullable();
            $table->boolean('is_pillar')->default(false);
            $table->string('schema_type', 50)->nullable();
            $table->json('custom_schema')->nullable();

            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id'], 'seo_meta_seoable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
