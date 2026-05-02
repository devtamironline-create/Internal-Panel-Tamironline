<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن فیلد address (آدرس محل سکونت) به technician_registrations.
 *
 * آدرس محل سکونت تکنسین — جدا از shop_address (آدرس مغازه که فقط برای
 * کسانی که مغازه دارند). این فیلد در مرحلهٔ ۲ ثبت‌نام (کنار استان و
 * شهر) به‌صورت اجباری گرفته می‌شود.
 *
 * در DB nullable می‌گذاریم تا رکوردهای موجود (که قبل از این تغییر
 * ثبت شده‌اند) خراب نشوند. اجبار سمت form/controller اعمال می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ستون address ممکن است در یک نسخهٔ قبلی schema (یا migration دستی)
        // قبلاً ست شده باشد — فقط در صورت نبود ایجاد می‌کنیم.
        if (! Schema::hasColumn('technician_registrations', 'address')) {
            Schema::table('technician_registrations', function (Blueprint $table) {
                $table->text('address')->nullable()->after('city');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('technician_registrations', 'address')) {
            Schema::table('technician_registrations', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
};
