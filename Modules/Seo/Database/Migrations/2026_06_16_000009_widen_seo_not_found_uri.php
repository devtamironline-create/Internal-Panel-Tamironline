<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * اصلاح seo_not_found_logs: URLهای بلند (query string) قبلاً به‌خاطر
 * unique روی varchar(191) رد می‌شدند. حالا uri تا 2048 ذخیره می‌شود و
 * یکتاییِ ردیف با uri_hash (SHA-256) تأمین می‌شود تا upsertِ اتمیک ممکن
 * شود و URLهای بلند هم لاگ گردند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->char('uri_hash', 64)->nullable()->after('uri');
        });

        // backfill برای رکوردهای موجود.
        DB::statement('UPDATE seo_not_found_logs SET uri_hash = SHA2(uri, 256) WHERE uri_hash IS NULL');

        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->dropUnique(['uri']);          // unique قبلی روی uri
        });
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->string('uri', 2048)->change(); // اجازهٔ URLهای بلند
            $table->unique('uri_hash');            // یکتاییِ جدید
        });
    }

    public function down(): void
    {
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->dropUnique(['uri_hash']);
        });
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->string('uri', 191)->change();
        });
        Schema::table('seo_not_found_logs', function (Blueprint $table) {
            $table->dropColumn('uri_hash');
            $table->unique('uri');
        });
    }
};
