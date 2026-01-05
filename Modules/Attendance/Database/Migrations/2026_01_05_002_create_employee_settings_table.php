<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Custom work hours (override global)
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();

            // Salary info
            $table->decimal('base_salary', 12, 0)->default(0); // حقوق پایه ماهانه
            $table->decimal('hourly_rate', 10, 0)->default(0); // نرخ ساعتی

            // Leave balance (days)
            $table->integer('annual_leave_balance')->default(26); // مرخصی استحقاقی سالانه
            $table->integer('sick_leave_balance')->default(12); // مرخصی استعلاجی

            // Supervisor
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_settings');
    }
};
