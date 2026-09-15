<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برداشتِ سرمایه (مالک/شرکا) — نوعِ دومِ «خروج وجه» در کنارِ هزینه.
 *
 * عمداً جدولِ جدا از crm_expenses است تا هرگز در هیچ جمع/گزارشِ «هزینه» یا
 * محاسبهٔ «سود» وارد نشود. مثلِ هزینه به یک «حساب پرداخت» لینک می‌شود، اما
 * برداشتِ سرمایه هزینهٔ شرکت محسوب نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_owner_withdrawals')) {
            return;
        }

        Schema::create('crm_owner_withdrawals', function (Blueprint $t) {
            $t->id();
            $t->dateTime('withdrawn_at')->index();
            $t->unsignedBigInteger('amount'); // تومان
            $t->foreignId('payment_account_id')->constrained('crm_payment_accounts')->restrictOnDelete();
            $t->string('description', 500)->nullable();
            $t->string('payee', 190)->nullable()->index(); // مالک/شریکِ دریافت‌کننده
            $t->string('payment_method', 30)->nullable();
            $t->string('tracking_number', 100)->nullable();
            $t->string('attachment_path', 500)->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->softDeletes(); // سابقهٔ مالی واقعاً پاک نمی‌شود.
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_owner_withdrawals');
    }
};
