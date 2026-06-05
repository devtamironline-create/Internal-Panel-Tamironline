<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن:
 *   - site_forum_questions.author_phone   (موبایل نویسنده، nullable)
 *   - site_forum_answers.author_phone     (موبایل پاسخ‌دهنده، nullable)
 *
 * کاربران از طریق موبایل وارد می‌شوند؛ این ستون برای ban بر اساس موبایل و
 * اطلاع‌رسانی SMS آینده استفاده می‌شود.
 */
return new class extends Migration
{
    private array $tables = ['site_forum_questions', 'site_forum_answers'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'author_phone')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->string('author_phone', 20)->nullable()->after('author_email');
                $t->index('author_phone');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'author_phone')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['author_phone']);
                    $t->dropColumn('author_phone');
                });
            }
        }
    }
};
