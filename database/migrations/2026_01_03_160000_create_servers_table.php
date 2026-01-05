<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام سرور
            $table->string('hostname')->nullable(); // آدرس سرور
            $table->string('ip_address')->nullable(); // آی‌پی سرور
            $table->enum('type', ['shared', 'vps', 'dedicated', 'reseller'])->default('shared');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Add server_id to services table
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->after('product_id')->constrained('servers')->nullOnDelete();
            $table->string('domain')->nullable()->after('order_number'); // دامنه سرویس
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropColumn(['server_id', 'domain']);
        });

        Schema::dropIfExists('servers');
    }
};
