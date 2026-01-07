<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_settings', function (Blueprint $table) {
            $table->id();

            // مقادیر ثابت ماهانه
            $table->decimal('housing_allowance', 15, 0)->default(9000000); // بن مسکن
            $table->decimal('food_allowance', 15, 0)->default(22000000); // حق خواروبار
            $table->decimal('marriage_allowance', 15, 0)->default(5000000); // حق تأهل
            $table->decimal('child_allowance', 15, 0)->default(20781936); // حق اولاد (به ازای هر فرزند)
            $table->decimal('seniority_daily_rate', 15, 0)->default(0); // پایه سنوات روزانه

            // نرخ‌های بیمه
            $table->decimal('employee_insurance_rate', 5, 2)->default(7.00); // سهم بیمه کارگر %
            $table->decimal('employer_insurance_rate', 5, 2)->default(23.00); // سهم بیمه کارفرما %

            // ضرایب اضافه‌کاری
            $table->decimal('overtime_regular_rate', 5, 2)->default(100.00); // نرخ اضافه‌کاری عادی %
            $table->decimal('overtime_holiday_rate', 5, 2)->default(140.00); // نرخ اضافه‌کاری تعطیل %

            // ساعت کاری روزانه (برای محاسبه نرخ دقیقه)
            $table->integer('daily_work_hours')->default(9); // ساعت کاری در روز
            $table->integer('monthly_work_days')->default(30); // روز کاری در ماه

            // تنظیمات اضافی
            $table->boolean('auto_calculate')->default(true); // محاسبه خودکار
            $table->integer('calculation_day')->default(1); // روز محاسبه حقوق ماهانه

            $table->timestamps();
        });

        // Insert default settings
        DB::table('salary_settings')->insert([
            'housing_allowance' => 9000000,
            'food_allowance' => 22000000,
            'marriage_allowance' => 5000000,
            'child_allowance' => 20781936,
            'seniority_daily_rate' => 0,
            'employee_insurance_rate' => 7.00,
            'employer_insurance_rate' => 23.00,
            'overtime_regular_rate' => 100.00,
            'overtime_holiday_rate' => 140.00,
            'daily_work_hours' => 9,
            'monthly_work_days' => 30,
            'auto_calculate' => true,
            'calculation_day' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_settings');
    }
};
