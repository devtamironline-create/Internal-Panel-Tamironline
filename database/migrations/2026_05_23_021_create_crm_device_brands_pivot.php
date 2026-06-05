<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول pivot برای ارتباط many-to-many بین crm_devices و crm_brands.
 * جایگزین crm_brands.supported_device_slugs (JSON column که بدین ترتیب
 * dropped می‌شود — هنوز هیچ داده‌ی واقعی روی آن seed نشده).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'brand_id'], 'uk_device_brand');
            $table->index('device_id');
            $table->index('brand_id');

            $table->foreign('device_id')->references('id')->on('crm_devices')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('crm_brands')->cascadeOnDelete();
        });

        // پاکسازی ستون JSON منسوخ
        if (Schema::hasColumn('crm_brands', 'supported_device_slugs')) {
            Schema::table('crm_brands', function (Blueprint $table) {
                $table->dropColumn('supported_device_slugs');
            });
        }
    }

    public function down(): void
    {
        Schema::table('crm_brands', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_brands', 'supported_device_slugs')) {
                $table->json('supported_device_slugs')->nullable();
            }
        });

        Schema::dropIfExists('crm_device_brands');
    }
};
