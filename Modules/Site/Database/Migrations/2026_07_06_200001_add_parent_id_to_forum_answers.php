<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ریپلایِ رشته‌ای (threaded) در پاسخ‌های انجمن: parent_id یعنی این پیام
 * ریپلایِ کدام پاسخ است. حذفِ والد → SET NULL (پیام سطحِ بالا می‌شود).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_answers', 'parent_id')) {
                $t->unsignedBigInteger('parent_id')->nullable()->after('question_id')->index();
                $t->foreign('parent_id')->references('id')->on('site_forum_answers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_answers', 'parent_id')) {
                try {
                    $t->dropForeign(['parent_id']);
                } catch (\Throwable $e) {
                    // sqlite تست
                }
                $t->dropColumn('parent_id');
            }
        });
    }
};
