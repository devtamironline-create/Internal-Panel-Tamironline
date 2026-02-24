<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تغییر ترتیب مراحل بعد از تایید:
     * قبلی: 7=ویدیو، 8=قرارداد، 9=مدارک
     * جدید: 7=قرارداد، 8=مدارک، 9=ویدیو
     */
    public function up(): void
    {
        DB::table('technician_registrations')
            ->whereIn('current_step', [7, 8, 9])
            ->update([
                'current_step' => DB::raw("CASE
                    WHEN current_step = 7 THEN 9
                    WHEN current_step = 8 THEN 7
                    WHEN current_step = 9 THEN 8
                    ELSE current_step
                END"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('technician_registrations')
            ->whereIn('current_step', [7, 8, 9])
            ->update([
                'current_step' => DB::raw("CASE
                    WHEN current_step = 9 THEN 7
                    WHEN current_step = 7 THEN 8
                    WHEN current_step = 8 THEN 9
                    ELSE current_step
                END"),
            ]);
    }
};
