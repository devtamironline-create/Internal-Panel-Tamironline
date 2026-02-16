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
        Schema::create('reprint_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_order_id')->constrained('warehouse_orders')->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('reason')->nullable()->comment('دلیل درخواست چاپ مجدد');
            $table->text('rejection_reason')->nullable()->comment('دلیل رد درخواست');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable()->comment('زمان چاپ واقعی');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('warehouse_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reprint_requests');
    }
};
