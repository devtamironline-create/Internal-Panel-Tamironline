<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سیستم کامنت polymorphic — الان روی مقالات بلاگ کار می‌کند، در آینده
 * هر entity دیگری (Device, Brand, ...) می‌تواند بدون migration جدید
 * کامنت بپذیرد فقط با اضافه‌کردن morphMany روی مدل خود.
 *
 * threading با parent_id self-referential.
 * status workflow: pending → approved | rejected | spam
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_comments', function (Blueprint $table) {
            $table->id();

            // polymorphic owner — مثلاً ('Modules\Site\Models\Article', 42)
            $table->string('commentable_type', 191);
            $table->unsignedBigInteger('commentable_id');

            // threading
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('root_id')->nullable(); // برای flat-fetch درخت

            // نویسنده
            $table->unsignedBigInteger('user_id')->nullable();    // کاربر ثبت‌نام‌کرده
            $table->string('author_name', 80);
            $table->string('author_email', 120)->nullable();
            $table->string('author_avatar', 500)->nullable();
            $table->boolean('is_admin_reply')->default(false);

            // محتوا
            $table->text('content');

            // moderation
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|spam
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);

            // anti-spam
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // moderation metadata
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            $table->timestamps();

            // index برای کوئری‌های شایع
            $table->index(['commentable_type', 'commentable_id', 'status'], 'idx_commentable_status');
            $table->index('parent_id');
            $table->index('root_id');
            $table->index(['status', 'created_at']);

            $table->foreign('parent_id')->references('id')->on('site_comments')->cascadeOnDelete();
            $table->foreign('root_id')->references('id')->on('site_comments')->cascadeOnDelete();
        });

        // برای جلوگیری از like تکراری هر IP فقط یک‌بار
        Schema::create('site_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->string('ip', 45);
            $table->string('reaction', 10)->default('like'); // like|dislike
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['comment_id', 'ip'], 'uk_comment_ip');
            $table->index('comment_id');

            $table->foreign('comment_id')->references('id')->on('site_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_comment_likes');
        Schema::dropIfExists('site_comments');
    }
};
