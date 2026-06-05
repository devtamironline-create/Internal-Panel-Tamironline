<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن visibility (public/private) به site_media.
 * Private files فقط برای ادمین قابل دسترسی هستند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_media', function (Blueprint $table) {
            if (! Schema::hasColumn('site_media', 'visibility')) {
                $table->string('visibility', 10)->default('public')->after('kind');
                $table->index(['kind', 'visibility']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_media', function (Blueprint $table) {
            if (Schema::hasColumn('site_media', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
