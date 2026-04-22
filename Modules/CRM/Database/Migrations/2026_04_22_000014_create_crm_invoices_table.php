<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code', 50)->unique();

            $table->foreignId('order_id')->unique()->constrained('crm_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('crm_technicians')->nullOnDelete();

            $table->unsignedBigInteger('total_amount'); // مبلغ نهایی
            $table->unsignedBigInteger('tech_share')->default(0);  // سهم تکنسین (کمیسیون)
            $table->unsignedBigInteger('company_share')->default(0); // سهم شرکت

            // snapshot روش محاسبه و درصد موقع صدور فاکتور
            $table->string('calc_type', 30)->nullable();
            $table->unsignedTinyInteger('commission_percent')->default(0);

            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled'])->default('issued');

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index(['technician_id', 'status']);
        });

        // FK کمپوزیت: wallet transactions.invoice_id → invoices.id
        Schema::table('crm_tech_wallet_transactions', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('crm_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_tech_wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::dropIfExists('crm_invoices');
    }
};
