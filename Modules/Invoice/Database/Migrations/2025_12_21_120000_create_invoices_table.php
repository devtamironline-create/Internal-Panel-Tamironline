<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();

            // Invoice Details
            $table->string('invoice_number')->unique(); // شماره فاکتور
            $table->date('invoice_date'); // تاریخ صدور
            $table->date('due_date'); // سررسید

            // Amounts
            $table->decimal('subtotal', 12, 2)->default(0); // جمع کل
            $table->decimal('tax_amount', 12, 2)->default(0); // مالیات
            $table->decimal('discount_amount', 12, 2)->default(0); // تخفیف
            $table->decimal('total_amount', 12, 2); // مبلغ نهایی

            // Status
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');

            // Payment Info
            $table->date('paid_at')->nullable(); // تاریخ پرداخت
            $table->string('payment_method')->nullable(); // روش پرداخت

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
