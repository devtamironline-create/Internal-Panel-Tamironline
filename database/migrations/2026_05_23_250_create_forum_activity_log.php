<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول audit log برای انجمن — هر عملی که ادمین روی سوال یا پاسخ انجام می‌دهد
 * در این جدول ثبت می‌شود تا بشود ردیابی کرد چه کسی چه تغییری داده.
 *
 * action: enum-ish (approve, reject, spam, delete, hot, featured, edit, reply, ...)
 * meta:   JSON اختیاری برای نگه‌داری مقادیر قبل/بعد یا توضیحات اضافی
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_forum_activity_log')) {
            return;
        }

        Schema::create('site_forum_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();      // ادمینی که action انجام داده
            $table->string('action', 40);                            // approve|reject|spam|delete|hot|...
            $table->unsignedBigInteger('question_id')->nullable();
            $table->unsignedBigInteger('answer_id')->nullable();
            $table->json('meta')->nullable();                        // before/after, reason, …
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['question_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('question_id')->references('id')->on('site_forum_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forum_activity_log');
    }
};
