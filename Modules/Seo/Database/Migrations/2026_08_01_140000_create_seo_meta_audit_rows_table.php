<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * snapshotِ بازبینیِ متا — تنها چیزی که ابزارِ `/admin/seo/meta-audit` می‌نویسد.
 *
 * چرا جدولِ جدا و نه محاسبهٔ زنده: صفحهٔ ابزار باید روی چند هزار ردیف فیلتر و
 * صفحه‌بندی کند. محاسبهٔ زندهٔ هر ردیف یعنی خواندنِ قالب‌ها و رندرِ متغیرها برای
 * هر سطر در هر بارگذاری — که نه صفحه‌بندی‌پذیر است نه ایندکس‌پذیر.
 *
 * هیچ کدِ زنده‌ای از این جدول نمی‌خواند؛ حذفش فقط ابزار را خالی می‌کند و روی
 * سایت هیچ اثری ندارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta_audit_rows', function (Blueprint $table) {
            $table->id();

            $table->string('page_type', 20);            // brand_device | device | brand
            $table->string('url', 700);
            $table->unsignedBigInteger('entity_id');     // device یا brand
            $table->unsignedBigInteger('secondary_id')->nullable(); // brand در صفحاتِ ترکیبی
            $table->string('label', 255)->nullable();    // «اجاق گاز زانوسی» — برای خوانایی

            // مقدارِ زنده (کانالِ سئو — همان چیزی که /v1/seo/meta می‌دهد)
            $table->text('live_title')->nullable();
            $table->unsignedSmallInteger('live_title_len')->default(0);
            $table->text('live_description')->nullable();
            $table->unsignedSmallInteger('live_description_len')->default(0);

            // مقدارِ کانالِ کاتالوگ — فقط وقتی با بالا فرق دارد پر می‌شود.
            $table->text('catalog_title')->nullable();
            $table->text('catalog_description')->nullable();
            $table->boolean('channels_diverge')->default(false);

            // تشخیصِ تکراری با یک GROUP BY روی هش، نه مقایسهٔ متنِ بلند.
            $table->char('title_hash', 32)->nullable();
            $table->char('description_hash', 32)->nullable();

            // «قبل» — آخرین کرالِ خودِ پنل (seo_audits)
            $table->text('crawled_title')->nullable();
            $table->text('crawled_description')->nullable();
            $table->timestamp('crawled_at')->nullable();

            $table->json('flags')->nullable();           // ['title_long', 'title_duplicate', …]
            $table->string('verdict', 20)->nullable();   // fixed | awaiting_crawl | needs_review | ok
            $table->boolean('matches_template')->default(false);
            $table->boolean('url_pattern_conflict')->default(false);

            $table->timestamp('generated_at')->nullable();

            // شمارشِ کارت‌ها و فیلترها باید ایندکس بخورند، نه اسکنِ کامل.
            $table->index('page_type');
            $table->index('title_hash');
            $table->index('description_hash');
            $table->index('live_title_len');
            $table->index('live_description_len');
            $table->index('verdict');
            $table->index('channels_diverge');
            $table->unique(['page_type', 'entity_id', 'secondary_id'], 'seo_meta_audit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta_audit_rows');
    }
};
