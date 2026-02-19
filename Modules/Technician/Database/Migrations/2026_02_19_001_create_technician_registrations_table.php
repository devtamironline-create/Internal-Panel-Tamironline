<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11)->unique();
            $table->string('national_code', 10)->unique();
            $table->string('birth_date', 10); // تاریخ شمسی: 1380/01/01
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('status', ['pending', 'incomplete', 'approved', 'rejected'])->default('incomplete');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_registrations');
    }
};
