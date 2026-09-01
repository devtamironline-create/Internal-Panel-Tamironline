<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * snapshotِ شرح/اقلامِ فاکتور در لحظهٔ صدور.
 *
 * تا کنون فاکتور فقط مبلغ را داشت و چاپ، شرح را از فیلدِ زندهٔ سفارش
 * (invoice_descripotion) می‌خواند؛ در سفارشِ بازگشتیِ جمع‌شونده که چند
 * فاکتور دارند، همه یک شرح (آخرین) را نشان می‌دادند. این دو ستون شرحِ
 * مستقلِ هر فاکتور را نگه می‌دارند. افزایشی و بی‌خطر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_invoices')) {
            return;
        }

        Schema::table('crm_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_invoices', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('crm_invoices', 'items_snapshot')) {
                $table->json('items_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        // ستون‌ها را حذف نمی‌کنیم تا دادهٔ snapshot از دست نرود.
    }
};
