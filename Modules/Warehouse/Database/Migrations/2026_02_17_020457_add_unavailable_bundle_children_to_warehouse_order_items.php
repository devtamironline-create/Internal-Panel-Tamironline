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
        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->json('unavailable_bundle_children')->nullable()->after('available_at')
                ->comment('آرایه شناسه محصولات ناموجود داخل پک (برای محصولات باندل)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->dropColumn('unavailable_bundle_children');
        });
    }
};
