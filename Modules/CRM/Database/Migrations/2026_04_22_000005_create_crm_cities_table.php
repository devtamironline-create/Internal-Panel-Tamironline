<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('crm_provinces')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['province_id', 'slug']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_cities');
    }
};
