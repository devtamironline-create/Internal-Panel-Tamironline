<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('customer_name', 80);
            $table->string('topic', 120);
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('audio_url', 500)->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
