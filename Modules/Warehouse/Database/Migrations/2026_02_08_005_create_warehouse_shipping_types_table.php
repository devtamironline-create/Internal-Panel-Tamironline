<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_shipping_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('timer_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default shipping types
        DB::table('warehouse_shipping_types')->insert([
            ['name' => 'پست', 'slug' => 'post', 'timer_minutes' => 60, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'پیک', 'slug' => 'courier', 'timer_minutes' => 420, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_shipping_types');
    }
};
