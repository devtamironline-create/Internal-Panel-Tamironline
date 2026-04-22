<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->nullable()->constrained('crm_invoices')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('crm_customers')->nullOnDelete();

            $table->string('gateway', 20)->default('zibal');     // zibal | manual
            $table->unsignedBigInteger('amount');                  // به تومان

            $table->string('track_id')->nullable()->index();       // trackId از Zibal
            $table->string('ref_number')->nullable();              // refNumber پس از verify
            $table->string('card_number')->nullable();             // شماره کارت masked
            $table->string('hash_card_number')->nullable();

            $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'verified'])->default('pending');
            $table->integer('result_code')->nullable();
            $table->string('result_message')->nullable();
            $table->json('gateway_response')->nullable();          // پاسخ کامل گیت‌وی

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_payments');
    }
};
