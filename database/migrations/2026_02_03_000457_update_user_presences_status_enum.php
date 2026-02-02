<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update enum to include all activity statuses
        DB::statement("ALTER TABLE user_presences MODIFY COLUMN status ENUM('online', 'away', 'busy', 'offline', 'meeting', 'remote', 'lunch', 'break', 'leave') DEFAULT 'offline'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE user_presences MODIFY COLUMN status ENUM('online', 'away', 'busy', 'offline') DEFAULT 'offline'");
    }
};
