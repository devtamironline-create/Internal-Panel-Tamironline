<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to include 'channel'
        DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('private', 'group', 'channel') DEFAULT 'private'");
    }

    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('private', 'group') DEFAULT 'private'");
    }
};
