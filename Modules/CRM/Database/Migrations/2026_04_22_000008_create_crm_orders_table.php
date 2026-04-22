<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 50)->unique();

            // ارتباطات
            $table->foreignId('customer_id')->constrained('crm_customers')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('crm_brands')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('crm_devices')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('crm_technicians')->nullOnDelete();

            // Snapshot مشخصات مشتری (برای حفظ تاریخچه حتی اگر مشتری بعداً ویرایش شد)
            $table->string('customer_name')->nullable();
            $table->string('customer_mobile', 20);
            $table->string('customer_phone', 20)->nullable();

            // محل مراجعه
            $table->foreignId('province_id')->nullable()->constrained('crm_provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('crm_cities')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();

            // شرح ایراد
            $table->string('problem_title')->nullable();
            $table->text('problem_description')->nullable();

            // زمان‌بندی
            $table->timestamp('visit_scheduled_at')->nullable();

            // مالی پایه (فاز ۷ توسعه خواهد یافت)
            $table->unsignedBigInteger('estimated_price')->nullable();
            $table->unsignedBigInteger('final_price')->nullable();
            $table->unsignedBigInteger('deposit')->default(0);

            // وضعیت
            $table->string('status', 30)->default('draft');
            $table->string('cancel_reason')->nullable();

            // Meta
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('notes')->nullable(); // یادداشت داخلی

            $table->timestamps();

            $table->index('status');
            $table->index(['technician_id', 'status']);
            $table->index('visit_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_orders');
    }
};
