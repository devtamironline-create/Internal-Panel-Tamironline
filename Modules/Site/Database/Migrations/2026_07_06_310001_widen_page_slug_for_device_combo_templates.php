<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الگوی ترکیبیِ اختصاصیِ هر دستگاه با slug مجازیِ device_brand:{device_slug}
 * در همین جدول ذخیره می‌شود — page_slug از ۵۰ به ۱۲۰ پهن می‌شود تا slugهای
 * بلندِ دستگاه هم جا شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_page_sections', function (Blueprint $t) {
            $t->string('page_slug', 120)->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_page_sections', function (Blueprint $t) {
            $t->string('page_slug', 50)->change();
        });
    }
};
