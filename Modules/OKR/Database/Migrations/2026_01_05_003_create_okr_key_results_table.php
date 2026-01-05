<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('okr_objectives')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('metric_type', ['number', 'percentage', 'currency', 'boolean'])->default('number');
            $table->decimal('start_value', 15, 2)->default(0);
            $table->decimal('target_value', 15, 2)->default(100);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'on_track', 'at_risk', 'behind', 'completed'])->default('not_started');
            $table->decimal('progress', 5, 2)->default(0);
            $table->decimal('confidence', 5, 2)->default(100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_key_results');
    }
};
