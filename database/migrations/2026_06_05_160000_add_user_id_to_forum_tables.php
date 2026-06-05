<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * انجمن از این به بعد فقط با لاگین (Sanctum) قابل پست کردن است.
 * ستون user_id به سوال و پاسخ اضافه می‌شود تا هویت کاربر استاندارد باشد.
 *
 * فیلدهای anonymous قبلی (author_name، author_email، author_phone)
 * برای backward compat نگه داشته می‌شوند — رکوردهای موجود دست‌نخورده می‌مانند.
 */
return new class extends Migration
{
    private array $tables = ['site_forum_questions', 'site_forum_answers'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'user_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('author_phone');
                $t->index('user_id');
            });

            // FK جدا تا اگر users نبود migration fail نشود
            if (Schema::hasTable('users')) {
                Schema::table($table, function (Blueprint $t) {
                    try {
                        $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                    } catch (\Throwable $e) {
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                try {
                    $t->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                }
                $t->dropIndex(['user_id']);
                $t->dropColumn('user_id');
            });
        }
    }
};
