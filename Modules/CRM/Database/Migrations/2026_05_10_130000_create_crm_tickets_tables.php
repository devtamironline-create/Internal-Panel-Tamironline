<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تیکت‌های پشتیبانی تکنسین — تکنسین مشکلش را روی یک سفارش (یا کلی)
 * ثبت می‌کند، اپراتور پاسخ می‌دهد. ساختار شبیه threadهای ساده با
 * یک تیکت + چند پاسخ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('crm_orders')->nullOnDelete();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('priority', 16)->default('normal'); // low | normal | high | urgent
            $table->string('status', 16)->default('open');     // open | replied | closed
            $table->string('image_path')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['technician_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('order_id');
        });

        Schema::create('crm_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('crm_tickets')->cascadeOnDelete();
            $table->string('sender_type', 16); // tech | admin
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_ticket_replies');
        Schema::dropIfExists('crm_tickets');
    }
};
