<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون‌های مورد نیاز اپ موبایل به crm_orders.
 *
 * همه nullable + bcompat — رفتار سفارش‌های موجود (پنل/WP) دست‌نخورده.
 *
 *   visit_scheduled_slot   enum-like (string 5) — '09-12'|'12-15'|'15-18'|'18-21'
 *                          بازه‌ی استاندارد. visit_scheduled_at همچنان datetime
 *                          باقی می‌ماند — slot اطلاعات اضافه‌تر است نه جایگزین.
 *   address_id             FK به crm_customer_addresses. اگر کاربر اپ آدرس
 *                          ذخیره‌شده انتخاب کرد، اینجا ذخیره می‌شود. snapshot
 *                          فیلدهای province_id/city_id/address همچنان پر می‌شوند.
 *   cancel_reason_id       کد عددی از config customerapp.cancel-reasons.
 *                          cancel_reason متن آزاد باقی می‌ماند (برای reason "دیگر").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_orders')) {
            return;
        }

        Schema::table('crm_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_orders', 'visit_scheduled_slot')) {
                $t->string('visit_scheduled_slot', 5)->nullable()->after('visit_scheduled_at');
                $t->index('visit_scheduled_slot');
            }
            if (! Schema::hasColumn('crm_orders', 'address_id')) {
                $t->unsignedBigInteger('address_id')->nullable()->after('postal_code');
                $t->index('address_id');
                // foreign بدون constrained — جدول هدف می‌تواند بعد ایجاد شود
                if (Schema::hasTable('crm_customer_addresses')) {
                    $t->foreign('address_id')->references('id')->on('crm_customer_addresses')->nullOnDelete();
                }
            }
            if (! Schema::hasColumn('crm_orders', 'cancel_reason_id')) {
                $t->unsignedInteger('cancel_reason_id')->nullable()->after('cancel_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_orders')) {
            return;
        }

        Schema::table('crm_orders', function (Blueprint $t) {
            if (Schema::hasColumn('crm_orders', 'cancel_reason_id')) {
                $t->dropColumn('cancel_reason_id');
            }
            if (Schema::hasColumn('crm_orders', 'address_id')) {
                try {
                    $t->dropForeign(['address_id']);
                } catch (\Throwable $e) {
                }
                $t->dropColumn('address_id');
            }
            if (Schema::hasColumn('crm_orders', 'visit_scheduled_slot')) {
                $t->dropColumn('visit_scheduled_slot');
            }
        });
    }
};
