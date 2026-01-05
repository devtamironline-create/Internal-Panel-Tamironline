<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();

            // Date/Time
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable(); // برای مرخصی ساعتی
            $table->time('end_time')->nullable(); // برای مرخصی ساعتی

            // Duration
            $table->decimal('days_count', 5, 2)->default(1); // تعداد روز (0.5 برای نیم روز)
            $table->integer('hours_count')->nullable(); // تعداد ساعت (برای مرخصی ساعتی)

            // Request details
            $table->text('reason')->nullable(); // دلیل مرخصی
            $table->string('document_path')->nullable(); // مدرک پیوست

            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable(); // یادداشت تایید/رد

            // Substitute
            $table->foreignId('substitute_id')->nullable()->constrained('users')->nullOnDelete(); // جایگزین

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
