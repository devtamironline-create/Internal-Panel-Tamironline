<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تبدیل ستون description روی crm_devices و crm_brands به longText
 * تا متن‌های بلند HTML از TinyMCE (با تگ‌های قالب‌بندی، تصاویر inline و ...) جا بگیرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_devices') && Schema::hasColumn('crm_devices', 'description')) {
            Schema::table('crm_devices', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }
        if (Schema::hasTable('crm_brands') && Schema::hasColumn('crm_brands', 'description')) {
            Schema::table('crm_brands', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_devices') && Schema::hasColumn('crm_devices', 'description')) {
            Schema::table('crm_devices', function (Blueprint $table) {
                $table->text('description')->nullable()->change();
            });
        }
        if (Schema::hasTable('crm_brands') && Schema::hasColumn('crm_brands', 'description')) {
            Schema::table('crm_brands', function (Blueprint $table) {
                $table->text('description')->nullable()->change();
            });
        }
    }
};
