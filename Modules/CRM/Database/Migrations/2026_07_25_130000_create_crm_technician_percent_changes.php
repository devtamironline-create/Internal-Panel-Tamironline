<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تغییراتِ زمان‌دارِ درصدِ کمیسیونِ تکنسین.
 *
 * هر ردیف می‌گوید «از تاریخ/ساعتِ effective_from، درصدِ کمیسیون (و/یا درصدِ دوم)
 * این مقدار است». محاسباتِ مالی (CommissionCalculator) درصدِ مؤثر در لحظهٔ
 * تکمیلِ سفارش را از همین تاریخچه می‌خوانند — پس تغییرِ درصد، تاریخِ مالیِ
 * گذشتهٔ تکنسین را بازنویسی نمی‌کند. ستون‌های فعلیِ percent/tech_per_of_all روی
 * خودِ تکنسین به‌عنوانِ مقدارِ پایه (قبل از اولین تغییرِ زمان‌دار) می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_technician_percent_changes')) {
            return;
        }

        Schema::create('crm_technician_percent_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            // null = «این فیلد تغییر نمی‌کند» (فقط فیلدِ دیگر عوض می‌شود)
            $table->unsignedTinyInteger('percent')->nullable();          // درصد کمیسیون (External)
            $table->unsignedTinyInteger('tech_per_of_all')->nullable();  // درصد دوم (Internal)
            $table->dateTime('effective_from');                          // از این لحظه معتبر است
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['technician_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_percent_changes');
    }
};
