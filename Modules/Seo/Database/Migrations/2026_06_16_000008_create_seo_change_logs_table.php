<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log تغییرات سئو (تنظیمات، ریدایرکت، متای آیتم‌ها).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50);          // updated|created|deleted|imported
            $table->string('subject', 100);        // settings|redirect|meta:{type}:{id}
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('subject');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_change_logs');
    }
};
