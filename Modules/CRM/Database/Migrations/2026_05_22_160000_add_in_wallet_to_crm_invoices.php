<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فلگ in_wallet روی فاکتورها — اگر true باشد، یعنی این فاکتور قبلاً
 * به‌صورت wallet transaction در کیف‌پول ثبت شده، پس در محاسبه
 * invoice_debt شرکت نمی‌کند (تا double-count نشود).
 *
 * فاکتورهای قدیمی in_wallet=false دارند → همچنان در invoice_debt
 * شمرده می‌شوند (backward-compat).
 *
 * فاکتورهای جدید (از این به بعد) با in_wallet=true ساخته می‌شوند و
 * یک wallet tx همراه دارند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->boolean('in_wallet')->default(false)->after('calc_type');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropColumn('in_wallet');
        });
    }
};
