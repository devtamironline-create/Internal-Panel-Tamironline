<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * یادداشت‌های اپراتورهای پنل لاراول روی سفارش — هر یادداشت با اسم
 * اپراتور و زمان ثبت می‌شود تا قابل پیگیری باشد. این جدا از
 * order_note_content (یادداشت‌های WP) است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_order_admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_admin_notes');
    }
};
