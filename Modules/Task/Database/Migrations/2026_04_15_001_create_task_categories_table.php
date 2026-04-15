<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('blue');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pivot table: many-to-many tasks <-> categories
        Schema::create('task_category', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_category_id')->constrained('task_categories')->cascadeOnDelete();
            $table->primary(['task_id', 'task_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_category');
        Schema::dropIfExists('task_categories');
    }
};
