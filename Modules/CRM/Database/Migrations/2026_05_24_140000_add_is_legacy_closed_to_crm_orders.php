<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون is_legacy_closed برای علامت‌گذاری سفارش‌هایی که از روی لاگ
 * قدیمی پنل WP بسته شده‌اند ولی هرگز نباید برایشان Invoice یا
 * WalletTransaction ساخته شود.
 *
 * این برای retro-close سفارش‌های قبل از reset مالی است:
 *   - status=Completed نمایش داده می‌شود
 *   - فیلدهای price_customer/cost_price/total_invoice از لاگ پر می‌شوند
 *   - دکمهٔ «صدور فاکتور» در صفحهٔ سفارش مخفی می‌شود
 *   - anomaly detector در گزارش روزانه این‌ها را flag نمی‌کند
 *   - InvoiceService/WalletTransaction هیچ‌جا فراخوانی نمی‌شود
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->boolean('is_legacy_closed')->default(false)->index()->after('save_as_draft');
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn('is_legacy_closed');
        });
    }
};
