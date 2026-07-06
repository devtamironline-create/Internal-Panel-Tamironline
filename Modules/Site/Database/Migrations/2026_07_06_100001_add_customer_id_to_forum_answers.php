<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پیوندِ پاسخِ انجمن به مشتری — storeAnswer از قبل customer_id می‌فرستاد ولی
 * ستون نبود و بی‌صدا حذف می‌شد. برای is_mine و «سوالاتِ منِ» اپ لازم است.
 * backfill از روی author_phone == crm_customers.mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (! Schema::hasColumn('site_forum_answers', 'customer_id')) {
                $t->unsignedBigInteger('customer_id')->nullable()->after('user_id')->index();
            }
        });

        try {
            DB::statement('
                UPDATE site_forum_answers a
                JOIN crm_customers c ON c.mobile = a.author_phone
                SET a.customer_id = c.id
                WHERE a.customer_id IS NULL AND a.author_phone IS NOT NULL AND a.author_phone != ""
            ');
        } catch (\Throwable $e) {
            // sqlite (تست) این syntax را ندارد — backfill فقط در MySQL لازم است.
        }
    }

    public function down(): void
    {
        Schema::table('site_forum_answers', function (Blueprint $t) {
            if (Schema::hasColumn('site_forum_answers', 'customer_id')) {
                $t->dropColumn('customer_id');
            }
        });
    }
};
