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
        // MySQL اجازه نمی‌دهد unique index ای که FK به آن متکی است drop شود.
        // اول FK را موقتاً برمی‌داریم، unique را drop می‌کنیم، index ساده
        // اضافه می‌کنیم، سپس FK را دوباره به همان ستون می‌بندیم.
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropUnique('crm_invoices_order_id_unique');
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('crm_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->unique('order_id', 'crm_invoices_order_id_unique');
        });

        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('crm_orders')
                ->cascadeOnDelete();
        });
    }
};
