<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حافظهٔ نوبت برای چرخشِ پخش سفارش.
 *
 * چرخش باید بین *اجراهای* مختلفِ کران هم حفظ شود. بدون این ستون، هر
 * اجرا از صفر شروع می‌کرد و در تساوی همیشه همان نفرِ اول برنده می‌شد.
 *
 * مقدار اولیه از آخرین سفارشِ تخصیص‌یافتهٔ هر تکنسین پر می‌شود تا نوبت
 * از همان جایی که واقعاً هست ادامه پیدا کند، نه از صفر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->timestamp('last_assigned_at')->nullable()->after('ready_for_delivery');
            $table->index('last_assigned_at');
        });

        if (! Schema::hasTable('crm_orders')) {
            return;
        }

        DB::statement('
            UPDATE crm_technicians t
            INNER JOIN (
                SELECT technician_id, MAX(assigned_at) AS last_at
                FROM crm_orders
                WHERE technician_id IS NOT NULL AND assigned_at IS NOT NULL
                GROUP BY technician_id
            ) o ON o.technician_id = t.id
            SET t.last_assigned_at = o.last_at
        ');
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $table) {
            $table->dropIndex(['last_assigned_at']);
            $table->dropColumn('last_assigned_at');
        });
    }
};
