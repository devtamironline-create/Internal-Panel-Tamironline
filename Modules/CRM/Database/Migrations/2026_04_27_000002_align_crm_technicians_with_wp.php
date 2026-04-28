<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هم‌سو سازی ستون‌های crm_technicians با CRM وردپرسی.
 *
 * مرجع نام‌گذاری: libs/technician.php در ریپوی WP
 *   technician_id, percent, max_order, max_price, type_tech,
 *   type_of_calc_tech, img_personal, cart_img, firstname_tech,
 *   tech_per_of_all, province (string), status (string)
 *
 * این مهاجرت داده‌محافظ است:
 *  - ستون‌های هم‌نام WP که فقط اسمشان فرق دارد، renameColumn می‌شوند.
 *  - ستون‌های جدید اضافه می‌شوند و از داده‌های موجود پر می‌شوند
 *    (status از is_active، province از crm_provinces.name).
 *  - ستون‌های مازادِ Laravel (last_name, email, gender, city_id, bank_*,
 *    notes, province_id, is_active) دست‌نخورده باقی می‌مانند تا داده گم
 *    نشود. در یک کامیت بعدی پس از تأیید می‌توان آن‌ها را drop کرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── ۱) drop ایندکس‌هایی که قبل از rename/drop ستون‌ها لازمند ─
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropIndex(['tech_type']); // ایندکس قبل از تبدیل enum
        });

        // ─── ۲) rename ستون‌های string ───────────────────────────────
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->renameColumn('tech_code', 'technician_id');
            $table->renameColumn('commission_percent', 'percent');
            $table->renameColumn('max_concurrent_orders', 'max_order');
            $table->renameColumn('max_order_price', 'max_price');
            $table->renameColumn('personal_image', 'img_personal');
            $table->renameColumn('card_image', 'cart_img');
        });

        // ─── ۳) تبدیل enum tech_type → string type_tech ─────────────
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->string('type_tech', 30)->nullable()->after('specialty');
        });
        DB::statement('UPDATE crm_technicians SET type_tech = tech_type');
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('tech_type');
        });

        // ─── ۴) تبدیل enum calc_type → string type_of_calc_tech ─────
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->string('type_of_calc_tech', 50)->nullable()->after('max_price');
        });
        DB::statement('UPDATE crm_technicians SET type_of_calc_tech = calc_type');
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('calc_type');
        });

        // ─── ۵) ستون‌های جدید WP-only ───────────────────────────────
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->string('firstname_tech')->nullable()->after('first_name');
            $table->unsignedSmallInteger('tech_per_of_all')->nullable()->after('percent');
            $table->string('province')->nullable()->after('province_id');
            $table->string('status', 30)->nullable()->after('is_active');
        });

        // ─── ۶) backfill ستون‌های جدید از داده‌های موجود ────────────
        DB::statement("
            UPDATE crm_technicians
            SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END
            WHERE status IS NULL
        ");
        DB::statement('
            UPDATE crm_technicians t
            LEFT JOIN crm_provinces p ON p.id = t.province_id
            SET t.province = p.name
            WHERE t.province_id IS NOT NULL AND t.province IS NULL
        ');

        // ─── ۷) ایندکس روی ستون‌های جدید ────────────────────────────
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->index('status');
            $table->index('type_tech');
        });
    }

    public function down(): void
    {
        // ۱) drop ایندکس‌های جدید
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropIndex(['type_tech']);
            $table->dropIndex(['status']);
        });

        // ۲) drop ستون‌های جدید WP-only
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn(['firstname_tech', 'tech_per_of_all', 'province', 'status']);
        });

        // ۳) برگرداندن enum calc_type
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->enum('calc_type', ['percent_of_customer', 'percent_of_total'])
                ->default('percent_of_customer')
                ->after('max_price');
        });
        DB::statement("
            UPDATE crm_technicians
            SET calc_type = CASE
                WHEN type_of_calc_tech = 'percent_of_total' THEN 'percent_of_total'
                ELSE 'percent_of_customer'
            END
        ");
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('type_of_calc_tech');
        });

        // ۴) برگرداندن enum tech_type
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->enum('tech_type', ['regular', 'senior', 'expert', 'freelance'])
                ->default('regular')
                ->after('specialty');
        });
        DB::statement("
            UPDATE crm_technicians
            SET tech_type = CASE
                WHEN type_tech IN ('regular', 'senior', 'expert', 'freelance') THEN type_tech
                ELSE 'regular'
            END
        ");
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropColumn('type_tech');
        });

        // ۵) برگرداندن نام ستون‌های rename‌شده
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->renameColumn('cart_img', 'card_image');
            $table->renameColumn('img_personal', 'personal_image');
            $table->renameColumn('max_price', 'max_order_price');
            $table->renameColumn('max_order', 'max_concurrent_orders');
            $table->renameColumn('percent', 'commission_percent');
            $table->renameColumn('technician_id', 'tech_code');
        });

        // ۶) ایندکس tech_type در حالت enum
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->index('tech_type');
        });
    }
};
