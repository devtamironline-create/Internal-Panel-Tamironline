<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->string('repair_skill', 50)->nullable()->after('transportation_method');
            $table->string('board_repair_experience', 50)->nullable()->after('repair_skill');
            $table->text('additional_notes')->nullable()->after('board_repair_experience');
        });
    }

    public function down(): void
    {
        Schema::table('technician_registrations', function (Blueprint $table) {
            $table->dropColumn(['repair_skill', 'board_repair_experience', 'additional_notes']);
        });
    }
};
