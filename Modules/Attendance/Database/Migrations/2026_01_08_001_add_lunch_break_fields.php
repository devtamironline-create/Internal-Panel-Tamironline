<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add lunch fields to attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('lunch_start')->nullable()->after('check_out_selfie');
            $table->time('lunch_end')->nullable()->after('lunch_start');
            $table->integer('lunch_minutes')->default(0)->after('lunch_end');
        });

        // Add lunch duration to attendance_settings
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->integer('lunch_duration_minutes')->default(30)->after('late_tolerance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['lunch_start', 'lunch_end', 'lunch_minutes']);
        });

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('lunch_duration_minutes');
        });
    }
};
