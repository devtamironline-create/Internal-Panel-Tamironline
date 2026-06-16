<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول ریدایرکت‌ها — فرانت (middleware.ts) این فهرست را از API می‌خواند و
 * اعمال می‌کند. انواع 301/302/307/410/451 و تطبیق exact/contains/start/end/regex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source', 2048);                 // مسیر مبدأ (یا الگو)
            $table->string('target', 2048)->nullable();      // مقصد (برای 410/451 خالی)
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('match_type', 20)->default('exact'); // exact|contains|start|end|regex
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
    }
};
