<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دو قابلیتِ اطلاع‌رسانی/شفافیت:
 *
 * - rejection_reason روی سوال/پاسخِ انجمن و کامنت: علتِ ردِ قابلِ‌نمایش به
 *   کاربر (تولیدشده توسطِ مودریشنِ AI یا ادمین) — فرانت وضعیت + علت را نشان می‌دهد.
 * - sms_sent_at روی پاسخ‌های انجمن: ضدِ تکرارِ پیامکِ «سوالِ شما پاسخ گرفت»
 *   (همان الگوی reply_sms_sent_at کامنت‌ها).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_questions', 'rejection_reason')) {
                $t->string('rejection_reason', 500)->nullable()->after('status');
            }
        });
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_answers', 'rejection_reason')) {
                $t->string('rejection_reason', 500)->nullable()->after('status');
            }
            if (! Schema::hasColumn('site_forum_answers', 'sms_sent_at')) {
                $t->timestamp('sms_sent_at')->nullable()->after('approved_at');
            }
        });
        Schema::table('site_comments', function (Blueprint $t) {
            if (! Schema::hasColumn('site_comments', 'rejection_reason')) {
                $t->string('rejection_reason', 500)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_questions', 'rejection_reason')) {
                $t->dropColumn('rejection_reason');
            }
        });
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_answers', 'rejection_reason')) {
                $t->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('site_forum_answers', 'sms_sent_at')) {
                $t->dropColumn('sms_sent_at');
            }
        });
        Schema::table('site_comments', function (Blueprint $t) {
            if (Schema::hasColumn('site_comments', 'rejection_reason')) {
                $t->dropColumn('rejection_reason');
            }
        });
    }
};
