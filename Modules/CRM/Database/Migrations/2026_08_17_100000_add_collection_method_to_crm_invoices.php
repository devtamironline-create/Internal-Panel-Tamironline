<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «روش دریافتِ وجه» فاکتور — انتخابِ تکنسین هنگام تکمیل:
 *   cash   نقدی — تکنسین پول را در محل گرفته؛ درگاه به مشتری نشان داده نمی‌شود
 *   online اعتباری — مشتری آنلاین می‌پردازد؛ تکنسین نباید نقدی بگیرد
 *   null   فاکتورهای قدیمی/کلاینت‌های به‌روزنشده — رفتارِ قبلی (درگاه باز)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->string('collection_method', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropColumn('collection_method');
        });
    }
};
