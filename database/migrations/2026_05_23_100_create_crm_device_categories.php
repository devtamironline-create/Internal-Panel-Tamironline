<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندی والد دستگاه‌ها — گروه‌بندی کلی‌تر مثل «لوازم آشپزخانه»،
 * «لوازم سرمایشی»، «لوازم گرمایشی». هر دستگاه به یک دسته تعلق دارد
 * (device_category_id nullable روی crm_devices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_device_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('icon', 60)->nullable();           // lucide icon key
            $table->string('tone', 30)->nullable();           // tone-* class
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('crm_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_devices', 'device_category_id')) {
                $table->unsignedBigInteger('device_category_id')->nullable()->after('id');
                $table->index('device_category_id');
                $table->foreign('device_category_id')
                    ->references('id')->on('crm_device_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_devices', function (Blueprint $table) {
            if (Schema::hasColumn('crm_devices', 'device_category_id')) {
                $table->dropForeign(['device_category_id']);
                $table->dropColumn('device_category_id');
            }
        });

        Schema::dropIfExists('crm_device_categories');
    }
};
