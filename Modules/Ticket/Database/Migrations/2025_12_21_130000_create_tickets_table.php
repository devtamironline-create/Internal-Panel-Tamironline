<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Ticket Details
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');

            // Classification
            $table->enum('department', ['support', 'technical', 'billing', 'sales'])->default('support');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'pending', 'answered', 'closed'])->default('open');

            // Timestamps
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
