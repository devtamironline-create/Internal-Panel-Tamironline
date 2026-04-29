<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * یک سفارش می‌تواند چند فاکتور داشته باشد (در WP CRM فاکتور اصلاحی،
 * فاکتور اضافه، یا چند پست financial با همان order_id رایج است).
 * بنابراین قید unique روی order_id حذف می‌شود و به ایندکس ساده تنزل
 * می‌یابد. تطبیق sync همچنان روی wp_id انجام می‌شود (که unique است).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropUnique('crm_invoices_order_id_unique');
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->unique('order_id', 'crm_invoices_order_id_unique');
        });
    }
};
