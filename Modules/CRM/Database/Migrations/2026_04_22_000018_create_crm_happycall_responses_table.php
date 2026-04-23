<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_happycall_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('crm_orders')->cascadeOnDelete();
            $table->enum('audience', ['customer', 'technician']);

            // پاسخ‌ها به‌صورت آرایه‌ای از {question_id, question_text, answer}
            $table->json('answers')->nullable();

            // امتیاز کلی 1 تا 5 (اختیاری)
            $table->unsignedTinyInteger('overall_rating')->nullable();

            $table->text('note')->nullable();
            $table->foreignId('called_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->useCurrent();

            $table->timestamps();

            $table->index(['order_id', 'audience']);
            $table->index('overall_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_happycall_responses');
    }
};
