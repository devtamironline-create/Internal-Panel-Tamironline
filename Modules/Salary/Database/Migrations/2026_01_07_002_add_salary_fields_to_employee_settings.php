<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_settings', function (Blueprint $table) {
            // حقوق‌های روزانه
            $table->decimal('daily_agreed_wage', 15, 0)->nullable()->after('hourly_rate'); // حقوق پایه روزانه توافقی (H)
            $table->decimal('daily_insurance_wage', 15, 0)->nullable()->after('daily_agreed_wage'); // حقوق روزانه بیمه‌ای (I)
            $table->decimal('daily_declared_wage', 15, 0)->nullable()->after('daily_insurance_wage'); // روزانه طبق اعلام بیمه (J)

            // اطلاعات شخصی برای مزایا
            $table->boolean('is_married')->default(false)->after('daily_declared_wage'); // متأهل
            $table->integer('children_count')->default(0)->after('is_married'); // تعداد فرزند
            $table->integer('seniority_years')->default(0)->after('children_count'); // سنوات (سال)

            // حساب بانکی
            $table->string('bank_name')->nullable()->after('seniority_years');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('sheba_number')->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('employee_settings', function (Blueprint $table) {
            $table->dropColumn([
                'daily_agreed_wage',
                'daily_insurance_wage',
                'daily_declared_wage',
                'is_married',
                'children_count',
                'seniority_years',
                'bank_name',
                'bank_account',
                'sheba_number',
            ]);
        });
    }
};
