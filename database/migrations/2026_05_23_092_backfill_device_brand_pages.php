<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: برای هر pivot موجود در crm_device_brands، یک رکورد در
 * crm_device_brand_pages بسازد (اگر هنوز نیست). تضمین می‌کند پس از
 * اضافه‌شدن قابلیت auto-create، pairهای قدیمی هم در پنل ادمین قابل
 * مشاهده‌اند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_device_brands') || ! Schema::hasTable('crm_device_brand_pages')) {
            return;
        }

        DB::statement('
            INSERT INTO crm_device_brand_pages (device_id, brand_id, is_active, created_at, updated_at)
            SELECT db.device_id, db.brand_id, 1, NOW(), NOW()
            FROM crm_device_brands db
            LEFT JOIN crm_device_brand_pages p
                ON p.device_id = db.device_id AND p.brand_id = db.brand_id
            WHERE p.id IS NULL
        ');
    }

    public function down(): void
    {
        // عمدتاً no-op؛ پاک‌کردن خودکار overrideها می‌تواند داده‌ی ادمین را از بین ببرد.
    }
};
