<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('okr_cycles')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('level', ['organization', 'team', 'individual'])->default('team');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('okr_objectives')->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->decimal('progress', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_objectives');
    }
};
