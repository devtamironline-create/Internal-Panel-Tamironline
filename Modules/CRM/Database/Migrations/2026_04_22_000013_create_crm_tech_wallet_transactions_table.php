<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tech_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable();
            // ^^ FK added after invoices table is created (below).

            // نوع تراکنش: commission / reward / penalty / payout / adjustment / credit
            $table->string('type', 30);

            // مبلغ علامت‌دار — مثبت = بستانکار تکنسین، منفی = بدهکار
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['technician_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tech_wallet_transactions');
    }
};
