<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * یکپارچه‌سازی «منطقه» روی crm_cities (سیستم B) — فاز ۲.
 *
 * تا کنون دو سیستمِ موازیِ منطقه وجود داشت:
 *   - سیستم A: جدول crm_regions (سفارش پنل + پوشش تکنسین)
 *   - سیستم B: ردیف‌های فرزندِ crm_cities با parent_city_id (اپ + آدرس مشتری)
 *
 * این مهاجرت همه را روی سیستم B جمع می‌کند:
 *   ۱) نگاشت هر crm_regions به منطقهٔ معادل در crm_cities (تطبیق با
 *      نرمال‌سازی ارقام فارسی/انگلیسی؛ اگر معادل نبود، ساخته می‌شود).
 *   ۲) backfill: crm_orders.district_id از روی region_id.
 *   ۳) ساخت pivot جدید crm_technician_districts و انتقال پوشش تکنسین‌ها.
 *
 * crm_regions و crm_orders.region_id و crm_technician_regions دست‌نخورده
 * می‌مانند (fallback/تاریخچه) و حذف فیزیکی به یک مهاجرت پاکسازیِ بعدی پس
 * از تأیید در محیط staging موکول می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pivot جدیدِ پوشش تکنسین بر پایهٔ منطقهٔ crm_cities
        if (! Schema::hasTable('crm_technician_districts')) {
            Schema::create('crm_technician_districts', function (Blueprint $t) {
                $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                $t->foreignId('district_id')->constrained('crm_cities')->cascadeOnDelete();
                $t->timestamp('created_at')->nullable();
                $t->primary(['technician_id', 'district_id']);
                $t->index('district_id');
            });
        }

        // بخش داده‌ایِ نگاشت به SQL/داده وابسته است — فقط وقتی جدول‌ها هستند.
        if (! Schema::hasTable('crm_regions') || ! Schema::hasTable('crm_cities')) {
            return;
        }

        $map = $this->buildRegionToDistrictMap(); // [region_id => district(crm_cities) id]

        if (empty($map)) {
            return;
        }

        // backfill district_id سفارش‌ها از region_id (فقط جاهای خالی)
        if (Schema::hasColumn('crm_orders', 'district_id')) {
            foreach ($map as $regionId => $districtId) {
                DB::table('crm_orders')
                    ->whereNull('district_id')
                    ->where('region_id', $regionId)
                    ->update(['district_id' => $districtId]);
            }
        }

        // انتقال پوشش منطقه‌ایِ تکنسین‌ها به pivot جدید
        if (Schema::hasTable('crm_technician_regions')) {
            foreach (DB::table('crm_technician_regions')->get() as $tr) {
                $districtId = $map[$tr->region_id] ?? null;
                if (! $districtId) {
                    continue;
                }
                DB::table('crm_technician_districts')->insertOrIgnore([
                    'technician_id' => $tr->technician_id,
                    'district_id' => $districtId,
                    'created_at' => $tr->created_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_districts');
    }

    /**
     * نگاشت crm_regions.id → crm_cities(district).id با تطبیق (شهر + نام نرمال‌شده).
     * اگر معادل وجود نداشت، ردیف منطقه در crm_cities ساخته می‌شود.
     *
     * @return array<int,int>
     */
    private function buildRegionToDistrictMap(): array
    {
        $regions = DB::table('crm_regions')->get(['id', 'city_id', 'name']);
        $districts = DB::table('crm_cities')->whereNotNull('parent_city_id')
            ->get(['id', 'parent_city_id', 'name', 'province_id']);

        // index: [parent_city_id][normalizedName] => district_id
        $idx = [];
        foreach ($districts as $d) {
            $idx[(int) $d->parent_city_id][$this->norm($d->name)] = (int) $d->id;
        }

        $map = [];
        foreach ($regions as $r) {
            $cityId = (int) $r->city_id;
            $key = $this->norm($r->name);
            $districtId = $idx[$cityId][$key] ?? null;

            if (! $districtId) {
                $parent = DB::table('crm_cities')->where('id', $cityId)->first(['id', 'province_id']);
                if (! $parent) {
                    continue;
                }
                $provinceId = (int) $parent->province_id;
                $districtId = (int) DB::table('crm_cities')->insertGetId([
                    'province_id' => $provinceId,
                    'parent_city_id' => $cityId,
                    'name' => $r->name,
                    'slug' => $this->uniqueSlug($r->name, $provinceId),
                    'sort_order' => 0,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $idx[$cityId][$key] = $districtId;
            }

            $map[(int) $r->id] = $districtId;
        }

        return $map;
    }

    /** نرمال‌سازی برای تطبیق نام: ارقام فارسی/عربی → انگلیسی، فشرده‌سازی فاصله. */
    private function norm(string $name): string
    {
        $name = strtr($name, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            'ي' => 'ی', 'ك' => 'ک',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    private function uniqueSlug(string $name, int $provinceId): string
    {
        $base = Str::slug($this->norm($name)) ?: ('area-'.Str::random(6));
        $slug = $base;
        $i = 2;
        while (DB::table('crm_cities')->where('province_id', $provinceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
};
