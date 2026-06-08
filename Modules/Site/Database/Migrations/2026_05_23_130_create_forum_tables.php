<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ماژول forum — جداول اصلی:
 *   - site_forum_experts: کارشناسان (slug/name/title/avatar/rating)
 *   - site_forum_questions: سوالات کاربران (با approval workflow)
 *   - site_forum_answers: پاسخ‌ها (با pending → approved + flag پاسخ کارشناس)
 *   - site_forum_question_upvotes / site_forum_answer_upvotes: لایک IP-based
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── کارشناسان ─────────────────────────────────────────────
        Schema::create('site_forum_experts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->string('title', 200)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();  // لینک به حساب کاربری ادمین
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('answers_count')->default(0); // denormalized
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // ─── سوالات ───────────────────────────────────────────────
        Schema::create('site_forum_questions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 250)->unique();
            $table->string('title', 250);
            $table->longText('body');
            $table->string('model', 80)->nullable();
            $table->json('tags')->nullable();

            // طبقه‌بندی
            $table->unsignedBigInteger('device_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();

            // نویسنده
            $table->string('author_name', 80);
            $table->string('author_email', 120)->nullable();
            $table->string('author_token', 64)->index(); // برای accept پاسخ توسط مالک سوال

            // moderation
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|spam
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            // وضعیت پاسخ‌گویی — denormalized از answers
            $table->string('resolution_status', 30)->default('unanswered')
                ->comment('unanswered|answered|expert-answered|solved');

            // تقدیر و شمارش
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->boolean('is_hot')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();

            // anti-spam
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['device_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('resolution_status');

            $table->foreign('device_id')->references('id')->on('crm_devices')->nullOnDelete();
            $table->foreign('brand_id')->references('id')->on('crm_brands')->nullOnDelete();
        });

        // ─── پاسخ‌ها ──────────────────────────────────────────────
        Schema::create('site_forum_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->longText('body');

            // نویسنده — یا متن آزاد یا لینک به کارشناس
            $table->string('author_name', 120);
            $table->string('author_email', 120)->nullable();
            $table->unsignedBigInteger('expert_id')->nullable();
            $table->boolean('is_expert_reply')->default(false)->index();

            // moderation
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            // مارک accept توسط مالک سوال
            $table->boolean('is_accepted')->default(false);
            $table->timestamp('accepted_at')->nullable();

            $table->unsignedInteger('upvotes_count')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['question_id', 'status']);
            $table->index(['question_id', 'is_accepted']);
            $table->index(['expert_id', 'status']);

            $table->foreign('question_id')->references('id')->on('site_forum_questions')->cascadeOnDelete();
            $table->foreign('expert_id')->references('id')->on('site_forum_experts')->nullOnDelete();
        });

        // ─── upvote tables (IP-based unique) ─────────────────────
        Schema::create('site_forum_answer_upvotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('answer_id');
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['answer_id', 'ip'], 'uk_forum_ans_ip');
            $table->foreign('answer_id')->references('id')->on('site_forum_answers')->cascadeOnDelete();
        });

        Schema::create('site_forum_question_upvotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['question_id', 'ip'], 'uk_forum_q_ip');
            $table->foreign('question_id')->references('id')->on('site_forum_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forum_question_upvotes');
        Schema::dropIfExists('site_forum_answer_upvotes');
        Schema::dropIfExists('site_forum_answers');
        Schema::dropIfExists('site_forum_questions');
        Schema::dropIfExists('site_forum_experts');
    }
};
