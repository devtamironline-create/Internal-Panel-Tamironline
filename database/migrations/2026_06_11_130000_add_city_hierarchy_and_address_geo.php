<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سلسله‌مراتب شهر ← منطقه + مختصات جغرافیایی آدرس‌ها.
 *
 * ۱) crm_cities.parent_city_id:
 *    «مناطق» (منطقه ۱ تهران، منطقه ۵ کرج، ...) که الان ردیف‌های flat در
 *    crm_cities هستند، فرزند شهر اصلی خود می‌شوند. شهرهای اصلی parent ندارند.
 *    backfill خودکار بر اساس الگوی slug «*-district-*» انجام می‌شود.
 *
 * ۲) crm_customer_addresses:
 *    - district_id  → FK به crm_cities (ردیف منطقه)
 *    - latitude/longitude → مختصات انتخاب‌شده از نقشه نشان
 *
 * هیچ داده‌ای حذف یا جابه‌جا نمی‌شود — آدرس‌های قبلی دست‌نخورده می‌مانند
 * (طبق تصمیم: بدون مهاجرت داده‌ی آدرس‌های موجود).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_cities', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_cities', 'parent_city_id')) {
                $t->unsignedBigInteger('parent_city_id')->nullable()->after('province_id');
                $t->index('parent_city_id');
            }
        });

        Schema::table('crm_cities', function (Blueprint $t) {
            try {
                $t->foreign('parent_city_id')->references('id')->on('crm_cities')->nullOnDelete();
            } catch (\Throwable $e) {
                // FK قبلاً موجود است
            }
        });

        // backfill: مناطق seed شده با slug «tehran-district-N» / «karaj-district-N»
        $this->linkDistricts('tehran-district-%', 'تهران', 'تهران');
        $this->linkDistricts('karaj-district-%', 'البرز', 'کرج');

        Schema::table('crm_customer_addresses', function (Blueprint $t) {
            if (! Schema::hasColumn('crm_customer_addresses', 'district_id')) {
                $t->unsignedBigInteger('district_id')->nullable()->after('city_id');
                $t->index('district_id');
            }
            if (! Schema::hasColumn('crm_customer_addresses', 'latitude')) {
                $t->decimal('latitude', 10, 7)->nullable()->after('full_address');
            }
            if (! Schema::hasColumn('crm_customer_addresses', 'longitude')) {
                $t->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('crm_customer_addresses', function (Blueprint $t) {
            try {
                $t->foreign('district_id')->references('id')->on('crm_cities')->nullOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_customer_addresses', function (Blueprint $t) {
            try {
                $t->dropForeign(['district_id']);
            } catch (\Throwable $e) {
            }
            $cols = array_filter(
                ['district_id', 'latitude', 'longitude'],
                fn ($c) => Schema::hasColumn('crm_customer_addresses', $c)
            );
            if (! empty($cols)) {
                $t->dropColumn($cols);
            }
        });

        Schema::table('crm_cities', function (Blueprint $t) {
            try {
                $t->dropForeign(['parent_city_id']);
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('crm_cities', 'parent_city_id')) {
                $t->dropColumn('parent_city_id');
            }
        });
    }

    /**
     * ردیف‌های منطقه (با الگوی slug) را فرزند شهر اصلی همان استان می‌کند.
     */
    private function linkDistricts(string $slugPattern, string $provinceName, string $cityName): void
    {
        $provinceId = (int) DB::table('crm_provinces')->where('name', $provinceName)->value('id');
        if ($provinceId <= 0) {
            return;
        }

        $mainCityId = (int) DB::table('crm_cities')
            ->where('province_id', $provinceId)
            ->where('name', $cityName)
            ->whereNull('parent_city_id')
            ->value('id');
        if ($mainCityId <= 0) {
            return;
        }

        DB::table('crm_cities')
            ->where('province_id', $provinceId)
            ->where('slug', 'like', $slugPattern)
            ->where('id', '!=', $mainCityId)
            ->whereNull('parent_city_id')
            ->update(['parent_city_id' => $mainCityId]);
    }
};
