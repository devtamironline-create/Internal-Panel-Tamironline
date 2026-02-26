<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE technician_registrations MODIFY COLUMN status ENUM('pending','incomplete','approved','rejected','final_approved') DEFAULT 'incomplete'");

        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->timestamp('final_approved_at')->nullable()->after('biometric_verified_at');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE technician_registrations MODIFY COLUMN status ENUM('pending','incomplete','approved','rejected') DEFAULT 'incomplete'");

        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn('final_approved_at');
        });
    }
};
