<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پیوندِ سوالِ انجمن به مشتری — برای «سوالاتِ منِ» پروفایل.
 *
 * ForumController::store از قبل customer_id می‌فرستاد ولی ستون/fillable نبود و
 * بی‌صدا حذف می‌شد. سوال‌های قدیمی از روی author_phone == crm_customers.mobile
 * backfill می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_questions', 'customer_id')) {
                $t->unsignedBigInteger('customer_id')->nullable()->after('user_id')->index();
            }
        });

        // backfill: سوال‌های قدیمی که با شمارهٔ مشتریِ موجود ثبت شده‌اند.
        try {
            DB::statement('
                UPDATE site_forum_questions q
                JOIN crm_customers c ON c.mobile = q.author_phone
                SET q.customer_id = c.id
                WHERE q.customer_id IS NULL AND q.author_phone IS NOT NULL AND q.author_phone != ""
            ');
        } catch (\Throwable $e) {
            // sqlite (تست) syntax JOIN در UPDATE را ندارد — backfill فقط در MySQL لازم است.
        }
    }

    public function down(): void
    {
        Schema::table('site_forum_questions', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_questions', 'customer_id')) {
                $t->dropColumn('customer_id');
            }
        });
    }
};
