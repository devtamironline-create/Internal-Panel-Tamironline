<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لاگ صفحات ۴۰۴ — فرانت هر بازدید ۴۰۴ را به /v1/seo/404 می‌فرستد و اینجا
 * بر اساس uri تجمیع (upsert + افزایش hits) می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uri', 191)->unique();
            $table->string('referrer', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedBigInteger('hits')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_not_found_logs');
    }
};
