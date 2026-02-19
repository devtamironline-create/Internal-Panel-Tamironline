<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->after('shenasname_number');
            $table->enum('marital_status', ['single', 'married'])->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['gender', 'marital_status']);
        });
    }
};
