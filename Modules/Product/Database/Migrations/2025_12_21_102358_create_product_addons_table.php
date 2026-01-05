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
        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete(); // null = کلی (برای همه محصولات)
            $table->string('name'); // Backup روزانه، IP اضافی، فضای اضافی
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // نوع addon: onetime (یکباره), recurring (تکرارشونده با دوره)
            $table->enum('type', ['onetime', 'recurring'])->default('recurring');

            // دوره تکرار (فقط برای recurring)
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semiannually', 'annually'])->nullable();

            // قیمت
            $table->decimal('price', 12, 2);

            // تنظیمات اضافی (JSON) - مثلاً حجم فضای اضافی
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_addons');
    }
};
