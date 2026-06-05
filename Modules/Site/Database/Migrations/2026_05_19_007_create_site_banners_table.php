<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_banners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->string('subtitle', 300)->nullable();
            $table->string('image_url', 500);
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 80)->nullable();
            $table->string('position', 40)->default('home_hero')->index(); // home_hero | home_secondary | ...
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_banners');
    }
};
