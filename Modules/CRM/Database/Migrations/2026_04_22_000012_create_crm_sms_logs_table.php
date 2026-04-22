<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sms_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
            $table->string('trigger_key', 50)->nullable(); // null for manual sends

            $table->string('recipient_mobile', 20);
            $table->string('recipient_role', 20)->nullable(); // customer | technician | other
            $table->text('body');

            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('response')->nullable(); // پیام بازگشتی API در صورت خطا

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); // null for automatic

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sms_logs');
    }
};
