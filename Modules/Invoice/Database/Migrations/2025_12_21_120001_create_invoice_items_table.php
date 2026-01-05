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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Item Details
            $table->string('description'); // شرح آیتم
            $table->integer('quantity')->default(1); // تعداد
            $table->decimal('unit_price', 12, 2); // قیمت واحد
            $table->decimal('total', 12, 2); // جمع کل (quantity * unit_price)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
