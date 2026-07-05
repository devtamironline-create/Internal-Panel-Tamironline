<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * سوال‌های انجمنی که توسطِ AI تأیید شده بودند published_at نداشتند و در هیچ
 * لیستِ عمومی‌ای نمایش داده نمی‌شدند (scopeApproved هر دو را می‌خواهد).
 * backfill: published_at = approved_at (یا اکنون).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_forum_questions')
            ->where('status', 'approved')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(approved_at, created_at)')]);
    }

    public function down(): void
    {
        // برگشت لازم نیست — داده‌ی اصلاح‌شده صحیح است.
    }
};
