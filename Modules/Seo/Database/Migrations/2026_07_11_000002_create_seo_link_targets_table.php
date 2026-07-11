<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وضعیتِ هر «مقصدِ یکتا» در یک کرال. هر target_url فقط یک‌بار چک می‌شود
 * (حتی اگر ده‌ها صفحه به آن لینک داده باشند) و نتیجه این‌جا ذخیره می‌شود؛
 * سپس با seo_links جوین می‌شود تا «لینکِ خراب + صفحاتِ ارجاع‌دهنده» ساخته شود.
 *
 * یکتاییِ (run_id, url) با هشِ url مدیریت می‌شود چون طولِ url برای ایندکسِ
 * یکتای MySQL (utf8mb4) بزرگ است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_link_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('url', 700);
            $table->char('url_hash', 40);               // sha1(url) برای یکتایی
            $table->string('path', 700)->nullable();
            $table->boolean('is_internal')->default(true);
            $table->unsignedSmallInteger('status_code')->default(0);
            $table->string('final_url', 700)->nullable();
            $table->unsignedTinyInteger('redirect_count')->default(0);
            $table->json('redirect_chain')->nullable();
            $table->boolean('is_broken')->default(false);
            $table->boolean('is_redirect')->default(false);
            $table->string('error', 500)->nullable();
            $table->timestamp('ignored_at')->nullable(); // «نادیده‌گرفته» در کارگاهِ اصلاح
            $table->string('ignore_reason', 300)->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->unique(['run_id', 'url_hash']);
            $table->index(['run_id', 'is_broken']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_link_targets');
    }
};
