<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیام‌های چت ۱:۱ بین تکنسین و اپراتور assign‌شده‌اش.
 *
 *   sender_type: 'tech' یا 'operator'
 *   sender_id : ID تکنسین یا کاربر (بسته به sender_type)
 *   read_at   : زمان مشاهدهٔ پیام توسط طرف مقابل (null = نخوانده)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tech_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['technician_id', 'created_at'], 'tech_chat_thread_idx');
            $table->index(['technician_id', 'sender_type', 'read_at'], 'tech_chat_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tech_chat_messages');
    }
};
