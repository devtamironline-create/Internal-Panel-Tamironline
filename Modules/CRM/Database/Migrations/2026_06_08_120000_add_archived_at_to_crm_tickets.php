<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن وضعیت «بایگانی» (archived) به تیکت‌ها + ستون archived_at.
 *
 * طبق نیاز عملیات:
 *   - وقتی اپراتور پاسخ می‌دهد، تیکت در حال انتظار پاسخ تکنسین می‌ماند.
 *   - اگر ۷۲ ساعت بدون پاسخ تکنسین گذشت، تیکت خودکار به archived می‌رود
 *     (توسط Scheduled Command روزانه).
 *   - اپراتور هم می‌تواند دستی تیکت را بایگانی کند.
 *
 * archived با closed تفاوت دارد: closed یعنی «حل شد، عمداً بستیم»،
 * archived یعنی «بی‌پاسخ ماند، از فهرست فعال خارج شد».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_tickets', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('closed_at');
                $table->index('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('crm_tickets', 'archived_at')) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            }
        });
    }
};
