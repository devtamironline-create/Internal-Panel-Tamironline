<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // Check-in
            $table->time('check_in')->nullable();
            $table->string('check_in_ip')->nullable();
            $table->json('check_in_location')->nullable(); // {lat, lng}
            $table->string('check_in_selfie')->nullable(); // file path

            // Check-out
            $table->time('check_out')->nullable();
            $table->string('check_out_ip')->nullable();
            $table->json('check_out_location')->nullable();
            $table->string('check_out_selfie')->nullable();

            // Calculated fields
            $table->integer('late_minutes')->default(0); // دقایق تاخیر
            $table->integer('early_leave_minutes')->default(0); // دقایق زودرفت
            $table->integer('overtime_minutes')->default(0); // دقایق اضافه‌کاری
            $table->integer('work_minutes')->default(0); // کل دقایق کاری

            // Status
            $table->enum('status', ['present', 'absent', 'leave', 'holiday', 'incomplete'])->default('incomplete');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
