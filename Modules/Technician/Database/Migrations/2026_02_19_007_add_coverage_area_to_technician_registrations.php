<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->json('tehran_districts')->nullable()->after('certificates');
            $table->json('tehran_province_cities')->nullable()->after('tehran_districts');
            $table->json('alborz_cities')->nullable()->after('tehran_province_cities');
            $table->text('other_provinces_cities')->nullable()->after('alborz_cities');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['tehran_districts', 'tehran_province_cities', 'alborz_cities', 'other_provinces_cities']);
        });
    }
};
