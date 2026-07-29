<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اولویتِ تخصص: تکنسینی که چند دستگاه را پوشش می‌دهد، همهٔ آن‌ها را به
 * یک اندازه دوست ندارد.
 *
 * تا امروز پوششِ دستگاه دودویی بود — یا بلد است یا نه. ولی تکنسینی که
 * هم جاروبرقی می‌زند هم مایکروویو، ممکن است در جاروبرقی بهتر/سریع‌تر
 * باشد و بخواهیم سفارشِ جاروبرقی ترجیحاً به او برسد.
 *
 * قرارداد عدد: هرچه بزرگ‌تر، مهم‌تر. صفر یعنی «بدون ترجیح» — یعنی
 * رفتارِ قبلی. پس این migration هیچ رفتاری را تغییر نمی‌دهد تا وقتی
 * مدیر عددی وارد کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_technician_devices')) {
            return;
        }

        if (Schema::hasColumn('crm_technician_devices', 'priority')) {
            return;
        }

        Schema::table('crm_technician_devices', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(0)->after('device_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_technician_devices', 'priority')) {
            Schema::table('crm_technician_devices', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }
};
