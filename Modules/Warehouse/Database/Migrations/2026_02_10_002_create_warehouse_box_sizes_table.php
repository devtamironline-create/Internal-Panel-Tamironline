<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_box_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // سایز کارتن (1, 1.5, 2, ...)
            $table->decimal('length', 8, 1);     // طول (cm)
            $table->decimal('width', 8, 1);      // عرض (cm)
            $table->decimal('height', 8, 1);     // ارتفاع (cm)
            $table->unsignedInteger('weight');    // وزن کارتن (g)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // داده‌های پیش‌فرض کارتن‌ها
        DB::table('warehouse_box_sizes')->insert([
            ['name' => '1',   'length' => 15, 'width' => 10, 'height' => 10, 'weight' => 46,   'sort_order' => 1,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '1.5', 'length' => 18, 'width' => 11, 'height' => 9,  'weight' => 55,   'sort_order' => 2,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '2',   'length' => 20, 'width' => 15, 'height' => 10, 'weight' => 81,   'sort_order' => 3,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '3',   'length' => 20, 'width' => 20, 'height' => 15, 'weight' => 132,  'sort_order' => 4,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '4',   'length' => 30, 'width' => 20, 'height' => 20, 'weight' => 175,  'sort_order' => 5,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '5',   'length' => 30, 'width' => 25, 'height' => 20, 'weight' => 503,  'sort_order' => 6,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '6',   'length' => 45, 'width' => 25, 'height' => 20, 'weight' => 520,  'sort_order' => 7,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '7',   'length' => 40, 'width' => 30, 'height' => 25, 'weight' => 612,  'sort_order' => 8,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '8',   'length' => 45, 'width' => 40, 'height' => 30, 'weight' => 927,  'sort_order' => 9,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '9',   'length' => 55, 'width' => 45, 'height' => 35, 'weight' => 1240, 'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_box_sizes');
    }
};
