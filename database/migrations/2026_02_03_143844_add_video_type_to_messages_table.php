<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'video' to the type enum in messages table
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'file', 'audio', 'image', 'video', 'system') DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'video' from the type enum
        DB::statement("ALTER TABLE messages MODIFY COLUMN type ENUM('text', 'file', 'audio', 'image', 'system') DEFAULT 'text'");
    }
};
