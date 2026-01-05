<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained('okr_key_results')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('previous_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->text('blockers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_check_ins');
    }
};
