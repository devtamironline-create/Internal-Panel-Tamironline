<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * در WP CRM، technician_id یک فیلد دلخواه است که اپراتور پر می‌کند و
 * در عمل اکثر تکنسین‌ها مقدار '0'، '1' یا حتی ارقام فارسی '۰' دارند
 * (داده ناهنجار). در schema اولیهٔ لاراول این ستون (وقتی هنوز tech_code
 * بود) با unique تعریف شده بود؛ پس از rename به technician_id، نام
 * ایندکس همان crm_technicians_tech_code_unique باقی ماند و باعث می‌شود
 * فقط اولین تکنسین با مقدار تکراری sync شود (1062 duplicate entry).
 *
 * این مهاجرت unique را به ایندکس ساده تنزل می‌دهد. تطبیق sync همچنان
 * با wp_id انجام می‌شود که خود unique است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropUnique('crm_technicians_tech_code_unique');
        });

        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->index('technician_id', 'crm_technicians_technician_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropIndex('crm_technicians_technician_id_index');
        });

        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->unique('technician_id', 'crm_technicians_tech_code_unique');
        });
    }
};
