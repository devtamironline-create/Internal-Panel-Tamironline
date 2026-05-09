<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اضافه کردن technician_id و purpose به crm_payments تا پرداخت‌های شارژ
 * کیف‌پول تکنسین (هم‌ارز Tech_Payment پنل WP) از پرداخت‌های فاکتور
 * مشتری قابل تشخیص باشند. بدون این، callback Zibal نمی‌تواند بفهمد چه
 * چیزی را verify کرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            $table->foreignId('technician_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('crm_technicians')
                ->nullOnDelete();

            // 'invoice' (پیش‌فرض، پرداخت فاکتور) | 'wallet_charge' (شارژ
            // دستی تکنسین). بقیهٔ purpose ها در آینده.
            $table->string('purpose', 30)->default('invoice')->after('gateway');

            $table->index(['technician_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_payments', function (Blueprint $table) {
            $table->dropIndex(['technician_id', 'purpose', 'status']);
            $table->dropConstrainedForeignId('technician_id');
            $table->dropColumn('purpose');
        });
    }
};
