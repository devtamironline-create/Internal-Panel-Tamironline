<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_order_id')->unique();
            $table->string('order_number')->nullable();
            $table->string('status');
            $table->string('currency', 10)->default('IRR');
            $table->decimal('total', 15, 2);
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('total_tax', 15, 2)->nullable();
            $table->decimal('shipping_total', 15, 2)->nullable();
            $table->decimal('discount_total', 15, 2)->nullable();

            // Customer Info
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();

            // Billing Address
            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_address_1')->nullable();
            $table->string('billing_address_2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postcode')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_phone')->nullable();

            // Shipping Address
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('shipping_address_1')->nullable();
            $table->string('shipping_address_2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postcode')->nullable();
            $table->string('shipping_country')->nullable();

            // Payment & Shipping Method
            $table->string('payment_method')->nullable();
            $table->string('payment_method_title')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('shipping_method')->nullable();

            // Notes & Meta
            $table->text('customer_note')->nullable();
            $table->json('meta_data')->nullable();
            $table->json('coupon_lines')->nullable();
            $table->json('fee_lines')->nullable();

            // Internal fields
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable();
            $table->string('internal_status')->nullable();
            $table->boolean('is_printed')->default(false);
            $table->boolean('is_packed')->default(false);
            $table->boolean('is_shipped')->default(false);
            $table->string('tracking_code')->nullable();
            $table->string('shipping_company')->nullable();

            $table->timestamp('date_created')->nullable();
            $table->timestamp('date_modified')->nullable();
            $table->timestamp('date_paid')->nullable();
            $table->timestamp('date_completed')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
            $table->index('date_created');
            $table->index('internal_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_orders');
    }
};
