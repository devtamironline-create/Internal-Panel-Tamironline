<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change hours_count from integer to decimal(5,2) to support fractional hours
        DB::statement('ALTER TABLE leave_requests MODIFY COLUMN hours_count DECIMAL(5,2) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE leave_requests MODIFY COLUMN hours_count INT NULL');
    }
};
