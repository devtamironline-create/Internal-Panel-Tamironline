<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_about_stats', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('value', 60); // عدد فرمت‌شده‌ی فارسی، آماده‌ی نمایش
            $table->string('label', 120);
            $table->string('tone', 20)->default('blue'); // blue | green | amber | rose | violet
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_about_stats');
    }
};
