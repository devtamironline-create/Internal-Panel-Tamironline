<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دیس‌لایک (downvote) برای سوال/پاسخِ انجمن + لاگِ جستجو برای «جستجوهای محبوب».
 *
 * downvote دقیقاً مثلِ upvote است: IP-based با uniqueِ دیتابیسی (ضدِ تکرار).
 * جستجوها برای endpointِ داینامیکِ popular-searches ثبت می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_questions', 'downvotes_count')) {
                $t->unsignedInteger('downvotes_count')->default(0)->after('upvotes_count');
            }
        });
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_answers', 'downvotes_count')) {
                $t->unsignedInteger('downvotes_count')->default(0)->after('upvotes_count');
            }
        });

        if (! Schema::hasTable('site_forum_question_downvotes')) {
            Schema::create('site_forum_question_downvotes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('question_id');
                $t->string('ip', 45);
                $t->timestamp('created_at')->nullable();
                $t->unique(['question_id', 'ip'], 'uk_forum_q_down_ip');
            });
        }
        if (! Schema::hasTable('site_forum_answer_downvotes')) {
            Schema::create('site_forum_answer_downvotes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('answer_id');
                $t->string('ip', 45);
                $t->timestamp('created_at')->nullable();
                $t->unique(['answer_id', 'ip'], 'uk_forum_a_down_ip');
            });
        }

        if (! Schema::hasTable('site_forum_search_logs')) {
            Schema::create('site_forum_search_logs', function (Blueprint $t) {
                $t->id();
                $t->string('query', 190);
                $t->timestamp('created_at')->nullable()->index();
                $t->index('query');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forum_search_logs');
        Schema::dropIfExists('site_forum_answer_downvotes');
        Schema::dropIfExists('site_forum_question_downvotes');
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_questions', 'downvotes_count')) {
                $t->dropColumn('downvotes_count');
            }
        });
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_answers', 'downvotes_count')) {
                $t->dropColumn('downvotes_count');
            }
        });
    }
};
