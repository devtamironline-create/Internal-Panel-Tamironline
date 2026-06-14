<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن snapshotِ ساختاریِ «منطقه» به سفارش‌ها.
 *
 * سفارش‌های اپ منطقه را به‌صورت district_id روی آدرس مشتری (crm_cities با
 * parent_city_id) دارند، اما تا کنون این منطقه روی خود سفارش ذخیره نمی‌شد و
 * فقط نامش به متن address چسبانده می‌شد — در نتیجه در پنل، منطقه در سلسله‌مراتب
 * «استان/شهر/منطقه» دیده نمی‌شد و داخل «نشانی کامل» تکرار می‌شد.
 *
 * این ستون، مثل province_id/city_id، یک snapshot از منطقهٔ آدرس روی سفارش
 * نگه می‌دارد (سیستم crm_cities). جدا از region_id قدیمی (crm_regions) است که
 * مخصوص سفارش‌های ثبت‌شده از خود پنل می‌ماند؛ نمایش‌ها هر دو را پشتیبانی می‌کنند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_orders')) {
            return;
        }

        if (! Schema::hasColumn('crm_orders', 'district_id')) {
            Schema::table('crm_orders', function (Blueprint $t) {
                $t->foreignId('district_id')->nullable()->after('city_id')
                    ->constrained('crm_cities')->nullOnDelete();
            });
        }

        // backfill فقط روی MySQL (محیط اجرای پروژه) — منطقه از آدرسِ مرتبط
        // پر می‌شود و در صورت تطابق، پیشوندِ تکراری از متن آدرس حذف می‌گردد.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // ۱) ست‌کردن district_id از آدرس مشتری
        DB::statement('
            UPDATE crm_orders o
            JOIN crm_customer_addresses a ON a.id = o.address_id
            SET o.district_id = a.district_id
            WHERE o.district_id IS NULL AND a.district_id IS NOT NULL
        ');

        // ۲) حذف پیشوندِ «<نام منطقه>، » از ابتدای متن address (که قبلاً
        //    در OrderController اپ به آدرس چسبانده می‌شد) تا منطقه دوبار دیده نشود.
        DB::statement("
            UPDATE crm_orders o
            JOIN crm_customer_addresses a ON a.id = o.address_id
            JOIN crm_cities d ON d.id = a.district_id
            SET o.address = TRIM(SUBSTRING(o.address, CHAR_LENGTH(CONCAT(d.name, '، ')) + 1))
            WHERE a.district_id IS NOT NULL
              AND o.address LIKE CONCAT(d.name, '، %')
        ");
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'district_id')) {
            Schema::table('crm_orders', function (Blueprint $t) {
                $t->dropConstrainedForeignId('district_id');
            });
        }
    }
};
