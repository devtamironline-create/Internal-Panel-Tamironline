<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن warranty_months به crm_order_items.
 *
 * بدون این، فاکتور mobile در فرانت نمی‌تواند گارانتی هر قطعه را نمایش دهد.
 * nullable + هیچ تأثیری روی منطق محاسبه‌ی موجود — فقط برچسب اضافه‌ی روی هر آیتم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_order_items') && ! Schema::hasColumn('crm_order_items', 'warranty_months')) {
            Schema::table('crm_order_items', function (Blueprint $t) {
                $t->unsignedTinyInteger('warranty_months')->nullable()->after('total_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_order_items') && Schema::hasColumn('crm_order_items', 'warranty_months')) {
            Schema::table('crm_order_items', function (Blueprint $t) {
                $t->dropColumn('warranty_months');
            });
        }
    }
};
