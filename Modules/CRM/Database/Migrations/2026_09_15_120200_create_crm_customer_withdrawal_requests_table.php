<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * درخواستِ برداشتِ وجهِ مشتری از کیف‌پول. کاربر در اپ درخواست می‌دهد؛ مبلغ همان
 * لحظه از کیف‌پول «رزرو» (کسر) می‌شود تا دوباره خرج نشود. ادمین در پنل تایید و
 * واریز می‌کند (status=paid) یا رد می‌کند (status=rejected → مبلغ برمی‌گردد).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_customer_withdrawal_requests')) {
            return;
        }

        Schema::create('crm_customer_withdrawal_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $t->bigInteger('amount'); // تومان (>۰)

            // pending → paid | rejected | canceled
            $t->string('status', 20)->default('pending');

            // اطلاعاتِ واریز که مشتری وارد می‌کند.
            $t->string('card_number', 30)->nullable();
            $t->string('sheba', 34)->nullable();
            $t->string('account_holder', 190)->nullable();

            // تراکنشِ کسرِ رزرو (برداشت) و تراکنشِ بازگشت (در صورتِ رد).
            $t->unsignedBigInteger('debit_tx_id')->nullable();
            $t->unsignedBigInteger('refund_tx_id')->nullable();

            $t->text('customer_note')->nullable();
            $t->text('admin_note')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('processed_at')->nullable();

            $t->softDeletes();
            $t->timestamps();

            $t->index(['customer_id', 'status']);
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_withdrawal_requests');
    }
};
