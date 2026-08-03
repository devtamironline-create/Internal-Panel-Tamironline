<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های لازمِ نجوا روی جدولِ توکنِ پوش.
 *
 * جدول در تیرماه برای اپِ نیتیو ساخته شد (expo/pushe/chabok) و آن روز
 * هنوز هیچ سرویسی انتخاب نشده بود. حالا نجوا انتخاب شده و وب‌پوش است،
 * نه اپِ نیتیو — پس چهار ستونِ تازه لازم است:
 *
 *   najva_script_id — کدام سایتِ نجوا این توکن را ساخته. وقتی staging و
 *                     production هر دو توکن می‌فرستند، تنها راهِ تشخیص
 *                     همین است؛ وگرنه پوشِ آزمایشی روی گوشیِ تکنسینِ
 *                     واقعی می‌نشیند.
 *   last_status     — آخرین وضعیتی که نجوا برای این توکن برگرداند
 *                     (Sent / InvalidToken / …).
 *   failed_count    — خطای پیاپی. مبنای پاک‌سازیِ توکن‌های مرده؛ نجوا
 *                     برای هر ارسالِ ناموفق هم هزینه ثبت می‌کند.
 *   revoked_at      — حذفِ نرم. خروج از حساب نباید ردیف را نابود کند،
 *                     وگرنه هیچ‌وقت نمی‌فهمیم چند تکنسین اعلان را خاموش
 *                     کرده‌اند — و همین گزارش، خواستهٔ بخشِ «مشترکین»
 *                     پنل ادمین است.
 */
return new class extends Migration
{
    private const TABLE = 'crm_technician_push_tokens';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'najva_script_id')) {
                $table->string('najva_script_id', 64)->nullable()->after('platform');
            }
            if (! Schema::hasColumn(self::TABLE, 'last_status')) {
                $table->string('last_status', 40)->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn(self::TABLE, 'failed_count')) {
                $table->unsignedInteger('failed_count')->default(0)->after('last_status');
            }
            if (! Schema::hasColumn(self::TABLE, 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('failed_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (['najva_script_id', 'last_status', 'failed_count', 'revoked_at'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
