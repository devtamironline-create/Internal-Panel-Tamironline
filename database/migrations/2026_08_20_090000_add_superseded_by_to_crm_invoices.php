<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اشاره‌گر «با کدام فاکتور جایگزین شد» — برای ریدایرکتِ دقیقِ لینکِ
 * قدیمیِ مشتری به فاکتورِ جدید (فلوی اصلاحِ مبلغ توسط ادمین).
 * additive و idempotent؛ داده‌ای تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_invoices', 'superseded_by_id')) {
                $table->unsignedBigInteger('superseded_by_id')->nullable()->after('superseded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('crm_invoices', 'superseded_by_id')) {
                $table->dropColumn('superseded_by_id');
            }
        });
    }
};
