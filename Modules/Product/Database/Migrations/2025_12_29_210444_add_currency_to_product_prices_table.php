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
        Schema::table('product_prices', function (Blueprint $table) {
            $table->enum('currency', ['IRR', 'USD'])->default('IRR')->after('billing_cycle');
            $table->decimal('usd_price', 12, 2)->nullable()->after('currency')->comment('Original USD price if currency is USD');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'usd_price']);
        });
    }
};
