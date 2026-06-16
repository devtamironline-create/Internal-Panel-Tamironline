<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تنظیمات سراسری سئو به‌صورت key/value گروه‌دار (هم‌الگو با site_settings).
 * گروه‌ها: general, social, verification, knowledge_graph, …
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
    }
};
