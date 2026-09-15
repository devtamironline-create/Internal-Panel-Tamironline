<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفترِ تراکنش‌های کیف‌پولِ مشتری — هم‌الگو با crm_tech_wallet_transactions.
 * تنها مسیرِ نوشتن CustomerWalletService است (قفلِ ردیف + balance_after).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_customer_wallet_transactions')) {
            return;
        }

        Schema::create('crm_customer_wallet_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
            $t->unsignedBigInteger('invoice_id')->nullable();

            // نوع: topup / payment / referral_reward / withdrawal / withdrawal_refund / adjustment
            $t->string('type', 30);

            // مبلغِ علامت‌دار — + = بستانکارِ مشتری (شارژ/پاداش)، − = برداشت/پرداخت
            $t->bigInteger('amount');
            $t->bigInteger('balance_after');

            $t->text('note')->nullable();
            $t->json('meta')->nullable();

            // created_by کاربرِ پنل (اگر دستی) — عملیاتِ سیستمی null است.
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamps();

            $t->index(['customer_id', 'created_at']);
            $t->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_wallet_transactions');
    }
};
