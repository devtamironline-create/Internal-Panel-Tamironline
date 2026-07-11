<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گرافِ لینک: هر ردیف یک لینکِ <a> است که در یک صفحهٔ خزیده‌شده پیدا شده.
 * برخلافِ seo_audits که فقط «شمارِ» لینک‌ها را نگه می‌داشت، این‌جا خودِ لینک
 * با anchor و محلِ دقیقِ DOM (selector/xpath) ذخیره می‌شود تا بتوان «لینکِ
 * خراب در کدام صفحه و کجای صفحه» را پاسخ داد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('source_url', 700);          // صفحه‌ای که لینک در آن است
            $table->string('target_url', 700);          // مقصدِ مطلقِ نرمال‌شده
            $table->string('target_path', 700)->nullable(); // مسیرِ نرمال (برای join داخلی)
            $table->string('anchor', 300)->nullable();
            $table->string('rel', 120)->nullable();
            $table->boolean('is_internal')->default(true);
            $table->string('selector', 500)->nullable(); // انتخاب‌گرِ تقریبیِ CSS
            $table->string('xpath', 500)->nullable();
            $table->text('context_html')->nullable();    // HTMLِ اطرافِ لینک
            $table->unsignedInteger('position')->default(0); // چندمین لینکِ صفحه
            $table->timestamp('created_at')->nullable();

            $table->index(['run_id', 'is_internal']);
            $table->index('target_path');
            $table->index(['run_id', 'source_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_links');
    }
};
