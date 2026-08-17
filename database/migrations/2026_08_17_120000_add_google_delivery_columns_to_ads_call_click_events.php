<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز ۲ ردیابی تماس — ستون‌های عملیاتیِ تحویل به Google Data Manager:
 * زمان‌بندی retry (backoff)، پیگیری poll وضعیت، و metadata غیرحساس پاسخ.
 * additive و idempotent — هیچ ستون/دادهٔ موجودی تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads_call_click_events', function (Blueprint $table) {
            if (! Schema::hasColumn('ads_call_click_events', 'google_last_attempt_at')) {
                $table->timestamp('google_last_attempt_at')->nullable()->after('google_attempts');
            }
            if (! Schema::hasColumn('ads_call_click_events', 'google_next_retry_at')) {
                $table->timestamp('google_next_retry_at')->nullable()->index()->after('google_last_attempt_at');
            }
            if (! Schema::hasColumn('ads_call_click_events', 'google_last_status_checked_at')) {
                $table->timestamp('google_last_status_checked_at')->nullable()->after('google_next_retry_at');
            }
            if (! Schema::hasColumn('ads_call_click_events', 'google_error_code')) {
                $table->string('google_error_code', 64)->nullable()->after('google_error');
            }
            if (! Schema::hasColumn('ads_call_click_events', 'google_response_meta')) {
                $table->json('google_response_meta')->nullable()->after('google_error_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads_call_click_events', function (Blueprint $table) {
            foreach (['google_last_attempt_at', 'google_next_retry_at', 'google_last_status_checked_at', 'google_error_code', 'google_response_meta'] as $column) {
                if (Schema::hasColumn('ads_call_click_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
