<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_order_id')->constrained('warehouse_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('strategy')->default('round_robin'); // الگوریتم تقسیم
            $table->timestamps();

            $table->unique('warehouse_order_id'); // هر سفارش فقط به یک نفر
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_order_assignments');
    }
};
