<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * crm_order_reviews — نظرسنجی اجباری مشتری برای سفارش‌های completed.
 *
 * هر سفارش حداکثر یک نظر دارد (unique(order_id)). معیارها:
 *   - rating          ۱..۵ اجباری
 *   - criteria        JSON با کلیدهای punctuality, quality, behavior, pricing
 *   - comment         اختیاری، تا ۱۰۰۰ کاراکتر
 *   - would_recommend اختیاری
 *
 * status: 'pending' | 'approved' | 'rejected'
 *   - pending  وقتی مشتری ثبت می‌کند
 *   - approved توسط ادمین یا policy خودکار
 *   - rejected ادمین رد می‌کند (مثلاً توهین)
 *
 * Technician.satisfaction_score بر اساس میانگین rating سفارش‌های approved
 * این تکنسین به‌روز می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_order_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained('crm_orders')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $t->foreignId('technician_id')->nullable()->constrained('crm_technicians')->nullOnDelete();

            $t->unsignedTinyInteger('rating');                      // 1..5
            $t->json('criteria')->nullable();                       // {punctuality, quality, behavior, pricing}
            $t->text('comment')->nullable();
            $t->boolean('would_recommend')->nullable();

            $t->string('status', 12)->default('pending')->index();  // pending|approved|rejected
            $t->text('moderation_note')->nullable();
            $t->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('moderated_at')->nullable();

            $t->ipAddress('ip')->nullable();
            $t->string('user_agent', 255)->nullable();

            $t->timestamps();

            $t->index(['technician_id', 'status']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_order_reviews');
    }
};
