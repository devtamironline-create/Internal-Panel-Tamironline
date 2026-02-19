<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('education_level')->nullable()->after('field_of_study');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn('education_level');
        });
    }
};
