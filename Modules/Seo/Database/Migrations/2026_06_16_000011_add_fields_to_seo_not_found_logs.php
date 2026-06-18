<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تکمیل مانیتور ۴۰۴ (§12): افزودن first_seen_at (اولین مشاهده)، ignored_at
 * (نادیده‌گرفته‌شده) و is_bot (تشخیص ربات از روی User-Agent). برای رکوردهای
 * موجود، first_seen_at با created_at پر می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->timestamp('first_seen_at')->nullable()->after('last_seen_at');
            $table->timestamp('ignored_at')->nullable()->after('first_seen_at');
            $table->boolean('is_bot')->default(false)->after('ignored_at');
        });

        // backfill: اولین مشاهده = زمان ساخت رکورد.
        DB::statement('UPDATE seo_not_found_logs SET first_seen_at = created_at WHERE first_seen_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->dropColumn(['first_seen_at', 'ignored_at', 'is_bot']);
        });
    }
};
