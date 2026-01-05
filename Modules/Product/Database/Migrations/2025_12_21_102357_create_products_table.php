<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('name'); // نام محصول
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('features')->nullable(); // امکانات به صورت JSON

            // مشخصات فنی (JSON) - فضا، ترافیک، CPU، RAM، و...
            $table->json('specifications')->nullable();

            // قیمت پایه (بدون دوره)
            $table->decimal('base_price', 12, 2)->default(0);

            // Setup Fee
            $table->decimal('setup_fee', 12, 2)->default(0);

            // محدودیت به دسته مشتری
            $table->json('allowed_customer_categories')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // محصول ویژه
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
