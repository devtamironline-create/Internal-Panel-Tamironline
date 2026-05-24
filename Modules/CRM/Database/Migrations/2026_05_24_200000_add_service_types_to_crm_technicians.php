<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * service_types: JSON آرایه از نوع‌های سفارش که تکنسین انجام می‌دهد.
 * مقادیر مجاز: 'repair' (تعمیر)، 'service' (سرویس)، 'install' (نصب).
 *
 * در TechnicianSuggestionService وقتی سفارش order_type مشخصی دارد،
 * فقط تکنسین‌هایی که آن نوع را در service_types خود دارند پیشنهاد
 * می‌شوند. اگر service_types خالی/null باشد، رفتار قبلی (همه نوع
 * را قبول می‌کند) حفظ می‌شود تا backward-compatible باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->json('service_types')->nullable()->after('type_tech');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('service_types');
        });
    }
};
