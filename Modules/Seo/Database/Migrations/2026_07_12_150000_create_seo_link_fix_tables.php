<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اصلاحِ لینک‌های داخلیِ اشتباه در سطحِ دیتابیس:
 *  - seo_link_fix_rules: قواعدِ «URL خراب → مقصدِ درست» یا «حذفِ لینک با حفظِ متن».
 *  - seo_link_fix_changes: نسخهٔ کاملِ قبلیِ هر فیلدِ تغییرکرده — برای بازگردانی.
 *
 * هیچ قاعده‌ای خودکار اعمال نمی‌شود؛ فقط با پیش‌نمایش و تأییدِ ادمین.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_link_fix_rules', function (Blueprint $table) {
            $table->id();
            $table->string('old_url', 700);
            $table->char('old_url_hash', 40)->unique(); // sha1(old_url) — یکتایی روی متنِ بلند
            $table->string('action', 10)->default('replace'); // replace | unlink
            $table->string('new_url', 700)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('source', 20)->nullable();          // 404 | redirect | manual
            $table->boolean('needs_review')->default(false);   // مقصدِ متفاوت در صفحاتِ مختلف — دسته‌ای اعمال نشود
            $table->string('status', 20)->default('pending');  // pending | applied
            $table->unsignedInteger('matches_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'needs_review']);
        });

        Schema::create('seo_link_fix_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->index();
            $table->string('entity_type', 20);  // article | device | brand | combo
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_label', 300)->nullable();
            $table->string('field', 60);
            $table->longText('before');          // مقدارِ کاملِ فیلد قبل از تغییر — برای بازگردانی
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_link_fix_changes');
        Schema::dropIfExists('seo_link_fix_rules');
    }
};
