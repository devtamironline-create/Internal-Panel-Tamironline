<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودنِ SoftDelete به موجودیت‌های عمومیِ سایت تا حذفِ یک صفحه/برند/دستگاه
 * بازگشت‌پذیر شود و در «سطلِ بازیافت» قابلِ بازگردانی باشد.
 *
 * جدول‌ها: برندها، دستگاه‌ها، صفحاتِ ترکیبیِ دستگاه×برند، صفحاتِ شهر.
 * هر ستون فقط در صورتِ نبود اضافه می‌شود (روی پروداکشنِ قبلاً migrate‌شده امن است).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'crm_brands',
        'crm_devices',
        'crm_device_brand_pages',
        'crm_city_pages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
