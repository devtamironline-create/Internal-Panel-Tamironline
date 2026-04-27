<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون wp_id به جدول‌های اصلی CRM.
 *
 * این ستون شناسهٔ معادل همان رکورد در سمت وردپرس را نگه می‌دارد و
 * موقع سینک به‌عنوان کلید تطبیق (upsert key) استفاده می‌شود.
 *
 * Nullable است چون رکوردهایی که مستقیم در لاراول ساخته می‌شوند
 * مرجعی در WP ندارند. Unique است تا یک رکورد WP فقط یک‌بار سینک شود.
 */
return new class extends Migration
{
    /** @var array<string> */
    private array $tables = [
        'crm_customers',
        'crm_technicians',
        'crm_orders',
        'crm_invoices',
        'crm_brands',
        'crm_devices',
        'crm_provinces',
        'crm_cities',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->unsignedBigInteger('wp_id')->nullable()->after('id');
                $t->unique('wp_id', $table . '_wp_id_unique');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table . '_wp_id_unique');
                $t->dropColumn('wp_id');
            });
        }
    }
};
