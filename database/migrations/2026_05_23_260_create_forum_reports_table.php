<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گزارش‌های کاربران از سوال/پاسخ (موارد نامناسب، تبلیغ، اسپم، ...).
 *
 * reporter می‌تواند ناشناس باشد (فقط IP) یا نام/ایمیل اختیاری.
 * ادمین در /admin/site/forum/reports گزارش‌ها را بررسی و resolve می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_forum_reports')) {
            return;
        }

        Schema::create('site_forum_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reportable_type', 60);       // 'question' | 'answer'
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 40);                // spam | inappropriate | wrong-info | duplicate | other
            $table->text('notes')->nullable();
            $table->string('reporter_name', 80)->nullable();
            $table->string('reporter_email', 150)->nullable();
            $table->string('reporter_ip', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->string('status', 20)->default('pending');   // pending | reviewed | dismissed | actioned
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
            $table->index('reporter_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forum_reports');
    }
};
