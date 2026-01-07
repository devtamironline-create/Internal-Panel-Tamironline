<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('year'); // سال شمسی
            $table->integer('month'); // ماه شمسی

            // اطلاعات کارکرد (از حضور غیاب)
            $table->integer('work_days')->default(0); // روز کارکرد
            $table->integer('work_minutes')->default(0); // دقیقه کار
            $table->integer('late_minutes')->default(0); // دقیقه تاخیر
            $table->integer('early_leave_minutes')->default(0); // دقیقه خروج زودتر
            $table->integer('overtime_regular_minutes')->default(0); // دقیقه اضافه‌کاری عادی
            $table->integer('overtime_holiday_minutes')->default(0); // دقیقه اضافه‌کاری تعطیل
            $table->integer('leave_minutes')->default(0); // دقیقه مرخصی استفاده شده
            $table->integer('absent_days')->default(0); // روز غیبت

            // حقوق‌های پایه (از تنظیمات کارمند)
            $table->decimal('daily_agreed_wage', 15, 0)->default(0); // حقوق روزانه توافقی
            $table->decimal('daily_insurance_wage', 15, 0)->default(0); // حقوق روزانه بیمه‌ای
            $table->decimal('daily_declared_wage', 15, 0)->default(0); // حقوق روزانه اعلامی

            // مزایای بیمه‌ای (S-Y)
            $table->decimal('fixed_insurance_salary', 15, 0)->default(0); // حقوق ثابت بیمه‌ای (S)
            $table->decimal('housing_allowance', 15, 0)->default(0); // بن مسکن (T)
            $table->decimal('food_allowance', 15, 0)->default(0); // حق خواروبار (U)
            $table->decimal('marriage_allowance', 15, 0)->default(0); // حق تأهل (V)
            $table->decimal('seniority_daily', 15, 0)->default(0); // پایه سنوات روزانه (W)
            $table->decimal('seniority_monthly', 15, 0)->default(0); // حق سنوات ماهانه (X)
            $table->decimal('child_allowance', 15, 0)->default(0); // حق اولاد (Y)

            // جمع‌ها
            $table->decimal('total_benefits', 15, 0)->default(0); // جمع مزایای مشمول و غیرمشمول (Z)
            $table->decimal('total_insurance_base', 15, 0)->default(0); // جمع حقوق مشمول بیمه (AA)

            // مابه‌التفاوت‌ها
            $table->decimal('daily_difference_declared', 15, 0)->default(0); // تفاوت روزانه قبلی با اعلامی (K)
            $table->decimal('daily_difference_agreed', 15, 0)->default(0); // تفاوت توافقی روزانه (L)
            $table->decimal('monthly_non_insurance', 15, 0)->default(0); // ماهانه توافقی غیربیمه‌ای (M)

            // اضافه‌کاری
            $table->decimal('overtime_regular', 15, 0)->default(0); // اضافه‌کاری عادی
            $table->decimal('overtime_holiday', 15, 0)->default(0); // اضافه‌کاری تعطیل
            $table->decimal('total_overtime', 15, 0)->default(0); // کل اضافه‌کار (N)

            // پاداش و سایر
            $table->decimal('bonus', 15, 0)->default(0); // پاداش (O)
            $table->decimal('salary_difference', 15, 0)->default(0); // تفاوت حقوق توافقی (P)

            // کسورات
            $table->decimal('employee_insurance', 15, 0)->default(0); // بیمه 7% سهم کارگر (AD)
            $table->decimal('employer_insurance', 15, 0)->default(0); // بیمه 23% کارفرما (AJ)
            $table->decimal('late_penalty', 15, 0)->default(0); // جریمه تاخیر (AE)
            $table->decimal('excess_leave', 15, 0)->default(0); // مرخصی مازاد (Q)
            $table->decimal('used_leave', 15, 0)->default(0); // مرخصی استفاده شده (AI)
            $table->decimal('advance_insurance', 15, 0)->default(0); // مساعده بیمه‌ای (AH)
            $table->decimal('advance', 15, 0)->default(0); // مساعده (AG)
            $table->decimal('other_deductions', 15, 0)->default(0); // سایر کسورات (AF)
            $table->decimal('total_deductions', 15, 0)->default(0); // جمع کسورات

            // خالص پرداختی
            $table->decimal('net_insurance_payment', 15, 0)->default(0); // خالص پرداخت بیمه‌ای (AK)
            $table->decimal('net_agreed_payment', 15, 0)->default(0); // خالص پرداخت توافقی (AL)
            $table->decimal('total_net_salary', 15, 0)->default(0); // جمع خالص حقوق (AM)

            // وضعیت
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // هر کارمند در هر ماه فقط یک رکورد حقوق داشته باشد
            $table->unique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
