<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامتِ «ویرایش‌شده» برای پیام‌های چت — تا وقتی کاربر پیامش را ویرایش می‌کند،
 * زمانِ ویرایش ثبت و در UI نشان داده شود. nullable و بی‌خطر برای production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'edited_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('edited_at')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'edited_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('edited_at');
            });
        }
    }
};
