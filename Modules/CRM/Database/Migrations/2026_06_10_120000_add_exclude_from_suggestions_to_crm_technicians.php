<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فلگ exclude_from_suggestions روی crm_technicians:
 *
 * بعضی رکوردهای تکنسین واقعی نیستند — placeholder عملیاتی‌اند (مثل
 * «سفارش کنسل شده» که سفارش‌های کنسلی به آن تخصیص داده می‌شوند). چون
 * این رکوردها هیچ تگ تخصصی ندارند، قانون «تگ خالی = پوشش همه» باعث
 * می‌شد همیشه در پیشنهاد هوشمند ظاهر شوند. با این فلگ به‌کلی از
 * suggestion engine حذف می‌شوند ولی در تخصیص دستی قابل انتخاب می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_technicians', 'exclude_from_suggestions')) {
            Schema::table('crm_technicians', function (Blueprint $t) {
                $t->boolean('exclude_from_suggestions')->default(false)->after('ready_for_delivery');
            });
        }

        // Backfill: رکورد placeholder شناخته‌شدهٔ کنسلی («سفارش کنسل شده»).
        DB::table('crm_technicians')
            ->where(function ($q) {
                $q->where('first_name', 'like', '%کنسل%')
                  ->orWhere('firstname_tech', 'like', '%کنسل%');
            })
            ->update(['exclude_from_suggestions' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_technicians', 'exclude_from_suggestions')) {
            Schema::table('crm_technicians', function (Blueprint $t) {
                $t->dropColumn('exclude_from_suggestions');
            });
        }
    }
};
