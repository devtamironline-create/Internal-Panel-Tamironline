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
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Service Details
            $table->string('order_number')->unique(); // شماره سفارش

            // Pricing
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'onetime', 'hourly']);
            $table->decimal('price', 12, 2); // قیمت دوره‌ای
            $table->decimal('setup_fee', 12, 2)->default(0); // هزینه راه‌اندازی
            $table->decimal('discount_amount', 12, 2)->default(0); // مبلغ تخفیف

            // Dates
            $table->date('start_date'); // تاریخ شروع
            $table->date('next_due_date')->nullable(); // تاریخ تمدید بعدی
            $table->date('end_date')->nullable(); // تاریخ پایان

            // Status
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled', 'expired'])->default('pending');

            // Auto Renew
            $table->boolean('auto_renew')->default(false);

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('next_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
