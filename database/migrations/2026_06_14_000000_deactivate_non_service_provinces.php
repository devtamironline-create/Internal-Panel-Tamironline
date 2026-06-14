<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * انتقال «محدودهٔ سرویس‌دهی» قدیمی به is_active.
 *
 * تا پیش از این، استان‌های قابل‌نمایش در اپ با تنظیم
 * `customer_app_service_provinces` (پیش‌فرض «1,18» = تهران، البرز) محدود
 * می‌شدند و LocationController علاوه بر is_active همین لیست را هم چک می‌کرد.
 *
 * آن گیت حذف شد و منبعِ واحدِ نمایش در اپ، پرچم is_active شد. اما چون
 * is_active برای همهٔ استان‌ها پیش‌فرض true است، با حذف گیت ناگهان همهٔ ۳۱
 * استان در اپ ظاهر می‌شدند. این migration همان انتخابِ قبلی را یک‌بار به
 * is_active منتقل می‌کند: استان‌های خارج از لیست را غیرفعال می‌کند تا رفتار
 * اپ (فقط استان‌های سرویس‌دهی) حفظ شود. تنظیم سطح استان از این پس فقط با
 * تاگل پنل انجام می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_provinces') || ! Schema::hasColumn('crm_provinces', 'is_active')) {
            return;
        }

        $raw = DB::table('settings')->where('key', 'customer_app_service_provinces')->value('value');
        $raw = ($raw !== null && trim((string) $raw) !== '') ? (string) $raw : '1,18';

        $ids = array_values(array_filter(
            array_map(fn ($v) => (int) trim($v), explode(',', $raw)),
            fn ($v) => $v > 0
        )) ?: [1, 18];

        // استان‌های خارج از لیستِ سرویس‌دهی قبلی → غیرفعال.
        // استان‌های داخل لیست دست‌نخورده می‌مانند (همان رفتار قبلی: active AND in-list).
        DB::table('crm_provinces')->whereNotIn('id', $ids)->update(['is_active' => false]);
    }

    public function down(): void
    {
        // انتقالِ یک‌طرفهٔ داده است؛ وضعیت فعال/غیرفعالِ قبلی هر استان قابل
        // بازیابی نیست، پس down عمداً no-op است. در صورت نیاز از پنل
        // «مناطق و محدودهٔ سرویس» استان‌ها را دستی فعال کنید.
    }
};
