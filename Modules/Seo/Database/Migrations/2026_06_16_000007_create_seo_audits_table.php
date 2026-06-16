<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نتیجهٔ آدیت On-page یک URL در یک run. تاریخچه‌دار (هر کرال یک ردیف نو).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->nullable()->constrained('seo_audit_runs')->nullOnDelete();
            $table->string('url', 700);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('final_url', 700)->nullable();
            $table->unsignedTinyInteger('redirect_count')->default(0);

            $table->string('title', 512)->nullable();
            $table->unsignedSmallInteger('title_length')->default(0);
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('description_length')->default(0);
            $table->unsignedSmallInteger('h1_count')->default(0);
            $table->string('canonical', 700)->nullable();
            $table->boolean('is_noindex')->default(false);
            $table->string('robots', 191)->nullable();
            $table->boolean('og_present')->default(false);
            $table->boolean('twitter_present')->default(false);
            $table->boolean('jsonld_present')->default(false);

            $table->unsignedSmallInteger('images_total')->default(0);
            $table->unsignedSmallInteger('images_without_alt')->default(0);
            $table->unsignedSmallInteger('internal_links')->default(0);
            $table->unsignedSmallInteger('external_links')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('response_time_ms')->default(0);

            $table->json('cwv')->nullable();      // Core Web Vitals (PageSpeed)
            $table->json('issues')->nullable();   // [{code,severity,message}]
            $table->string('severity', 12)->default('good'); // good|notice|warning|critical
            $table->unsignedTinyInteger('score')->default(0);

            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index('url');
            $table->index('severity');
            $table->index('crawled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audits');
    }
};
