<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page_slug', 50)->index();
            $table->string('section_key', 50);
            $table->json('payload')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['page_slug', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_sections');
    }
};
