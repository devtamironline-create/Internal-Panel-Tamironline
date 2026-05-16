<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * منبع داده هر سفارش — مشخص می‌کند کدام سمت برای این سفارش اصل است.
 *
 *   auto  (پیش‌فرض) — تابع order_sync_direction تکنسین
 *   panel — پنل لاراول اصل است؛ WP فقط می‌خواند، نمی‌نویسد
 *   crm   — WP CRM اصل است؛ Laravel push بلاک می‌شود
 *
 * این فیلد روی هر سفارش ثبت می‌شود و اگر ست شود، اولویت بالاتری از
 * تنظیم تکنسین دارد. اگر روی auto باشد، رفتار قبلی حاکم است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->enum('source_of_truth', ['auto', 'panel', 'crm'])
                ->default('auto')
                ->after('technician_wp_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn('source_of_truth');
        });
    }
};
