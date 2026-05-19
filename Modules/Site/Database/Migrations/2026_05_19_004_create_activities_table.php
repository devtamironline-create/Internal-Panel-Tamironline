<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('device_slug', 50)->index();
            $table->string('device_label', 80);
            $table->string('area', 80); // فقط شهر/محله — هیچ آدرس کامل
            $table->string('status', 20)->default('completed'); // completed | in_progress
            $table->timestamp('happened_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
