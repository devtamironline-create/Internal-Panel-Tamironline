<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شهرها از سمت WP بدون اطلاعات استان سینک می‌شوند (taxonomy ساده با
 * فقط wp_id/name/slug). تا زمانی که در WP ربط شهر→استان اضافه شود،
 * province_id باید nullable باشد تا upsert تاکسونومی نشکند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_cities', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
        });

        Schema::table('crm_cities', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->change();
        });

        Schema::table('crm_cities', function (Blueprint $table) {
            $table->foreign('province_id')
                ->references('id')->on('crm_provinces')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_cities', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
        });

        Schema::table('crm_cities', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable(false)->change();
        });

        Schema::table('crm_cities', function (Blueprint $table) {
            $table->foreign('province_id')
                ->references('id')->on('crm_provinces')
                ->cascadeOnDelete();
        });
    }
};
