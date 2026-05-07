<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_training_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('crm_training_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('crm_training_categories')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // is_local=false → video_url یک URL خارجی است (آپارات/یوتیوب/...)
            // is_local=true  → video_url یک مسیر نسبی روی public disk
            $table->boolean('is_local')->default(false);
            $table->string('video_url', 1000)->nullable();
            $table->string('thumbnail')->nullable();

            $table->integer('duration_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_training_videos');
        Schema::dropIfExists('crm_training_categories');
    }
};
