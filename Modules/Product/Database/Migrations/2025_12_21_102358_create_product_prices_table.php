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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // نوع دوره: monthly, quarterly, semiannually, annually, biennially, onetime, hourly
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'onetime', 'hourly']);

            // قیمت اصلی
            $table->decimal('price', 12, 2);

            // تخفیف (اختیاری)
            $table->decimal('discount_amount', 12, 2)->nullable(); // مبلغ تخفیف
            $table->tinyInteger('discount_percent')->nullable(); // درصد تخفیف

            // قیمت نهایی (بعد از تخفیف) - محاسبه خودکار
            $table->decimal('final_price', 12, 2);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique: هر محصول فقط یک قیمت برای هر billing_cycle داره
            $table->unique(['product_id', 'billing_cycle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
