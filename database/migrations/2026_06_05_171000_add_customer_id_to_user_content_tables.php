<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن customer_id (FK به crm_customers) به جداول محتوای کاربر:
 *   - site_forum_questions
 *   - site_forum_answers
 *   - site_comments
 *
 * ستون user_id قبلی (FK به users) دست‌نخورده می‌ماند برای backward compat
 * با هر داده‌ی موجود — null‌ پذیر می‌ماند. نوشتن‌های جدید فقط customer_id را
 * پر می‌کنند.
 */
return new class extends Migration
{
    private array $tables = ['site_forum_questions', 'site_forum_answers', 'site_comments'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'customer_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('customer_id')->nullable()->after('user_id');
                $t->index('customer_id');
            });

            if (Schema::hasTable('crm_customers')) {
                Schema::table($table, function (Blueprint $t) {
                    try {
                        $t->foreign('customer_id')->references('id')->on('crm_customers')->nullOnDelete();
                    } catch (\Throwable $e) {
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'customer_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                try {
                    $t->dropForeign(['customer_id']);
                } catch (\Throwable $e) {
                }
                $t->dropIndex(['customer_id']);
                $t->dropColumn('customer_id');
            });
        }
    }
};
