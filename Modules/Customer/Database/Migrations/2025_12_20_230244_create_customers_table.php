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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile', 11)->unique();
            $table->string('business_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_code', 10)->nullable()->unique();
            $table->foreignId('customer_category_id')->nullable()->constrained('customer_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable(); // یادداشت‌های داخلی
            $table->timestamps();
            $table->softDeletes(); // حذف نرم

            $table->index('mobile');
            $table->index('national_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
