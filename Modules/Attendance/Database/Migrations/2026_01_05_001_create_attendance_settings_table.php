<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();

            // Work hours
            $table->time('work_start_time')->default('08:00:00');
            $table->time('work_end_time')->default('17:00:00');
            $table->integer('late_tolerance_minutes')->default(15); // تلرانس تاخیر

            // Verification methods (JSON array: ip, gps, selfie)
            $table->json('verification_methods')->nullable();
            $table->json('allowed_ips')->nullable(); // IP های مجاز
            $table->decimal('allowed_location_lat', 10, 8)->nullable();
            $table->decimal('allowed_location_lng', 11, 8)->nullable();
            $table->integer('allowed_location_radius')->default(100); // متر

            // Salary settings
            $table->enum('salary_type', ['monthly', 'hourly'])->default('monthly');
            $table->decimal('overtime_rate', 3, 2)->default(1.40); // ضریب اضافه‌کاری
            $table->decimal('late_deduction_per_minute', 10, 0)->default(0); // کسری به ازای هر دقیقه
            $table->decimal('absence_deduction_per_day', 10, 0)->default(0); // کسری غیبت روزانه

            // Working days (JSON array of weekdays: 0=Saturday, 6=Friday)
            $table->json('working_days')->nullable(); // روزهای کاری

            $table->timestamps();
        });

        // Insert default settings
        DB::table('attendance_settings')->insert([
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'verification_methods' => json_encode(['trust']),
            'working_days' => json_encode([0, 1, 2, 3, 4]), // شنبه تا چهارشنبه
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
