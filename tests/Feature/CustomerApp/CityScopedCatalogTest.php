<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * کاتالوگِ شهرمحورِ اپ مشتری (نامهٔ تیمِ اپ ۱۴۰۵/۰۶/۰۲):
 *
 *   - categories?city_id=N → فقط دستگاه‌های تحتِ پوششِ آن شهر
 *     (تگِ تکنسین + کنترلِ نمایشِ ادمین). بدونِ city_id مثلِ قبل.
 *     شهرِ بدونِ پوشش/نامعتبر → آرایهٔ خالی، نه خطا.
 *   - brands?device_id=D&city_id=N → محدود به برندهای دارای تکنسین در
 *     آن شهر (تگِ برندِ خالی = همهٔ برندها → بدونِ محدودیت).
 *   - locations/cities → فیلدِ serviceable.
 *   - تا کامل‌نبودنِ دادهٔ پوشش، هیچ محدودیتی اعمال نمی‌شود.
 */
class CityScopedCatalogTest extends TestCase
{
    private Province $province;

    private City $tehran;

    private City $mashhad;

    private Device $washer;

    private Device $fridge;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('province_id')->nullable(),
            $x->unsignedBigInteger('parent_city_id')->nullable(),
            $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->string('icon')->nullable(), $x->string('thumbnail')->nullable(),
            $x->text('description')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->boolean('is_active_app')->default(true),
            $x->boolean('is_featured')->default(false),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->string('logo')->nullable(), $x->string('tone')->nullable(), $x->string('bg')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->boolean('is_featured')->default(false),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_technicians', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('mobile')->nullable(),
            $x->string('status', 20)->default('active'), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_technician_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('city_id'),
        ]));
        Schema::create('crm_technician_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('device_id'),
            $x->integer('priority')->nullable(),
        ]));
        Schema::create('crm_technician_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('brand_id'),
        ]));
        // brands endpoint به این دو هم سر می‌زند.
        Schema::create('crm_device_brand_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('device_id'), $x->unsignedBigInteger('brand_id'),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_brand_device', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('brand_id'), $x->unsignedBigInteger('device_id'),
        ]));
        Schema::create('crm_orders', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('brand_id')->nullable(),
            $x->unsignedBigInteger('device_id')->nullable(), $x->timestamps(),
        ]));
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        Schema::create('crm_settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(), $x->timestamps(),
        ]));

        $this->province = Province::create(['name' => 'تهران']);
        $this->tehran = City::create(['province_id' => $this->province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $this->mashhad = City::create(['province_id' => $this->province->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $this->washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $this->fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
    }

    private function tech(array $cityIds, array $deviceIds = [], array $brandIds = []): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);
        $t->brands()->sync($brandIds);

        return $t;
    }

    public function test_categories_are_filtered_by_city_coverage(): void
    {
        // مشهد فقط لباسشویی دارد؛ تهران همه‌کاره.
        $this->tech([$this->mashhad->id], [$this->washer->id]);
        $this->tech([$this->tehran->id], []);

        $mashhad = $this->getJson('/v1/customer/services/categories?city_id='.$this->mashhad->id)
            ->assertOk()->json('data');
        $this->assertSame(['washer'], collect($mashhad)->pluck('slug')->all());

        $tehran = $this->getJson('/v1/customer/services/categories?city_id='.$this->tehran->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $tehran);

        // بدونِ city_id: رفتارِ قبلی (همه).
        $all = $this->getJson('/v1/customer/services/categories')->assertOk()->json('data');
        $this->assertCount(2, $all);

        // شهرِ بدونِ پوشش/نامعتبر → خالی، نه خطا.
        $empty = City::create(['province_id' => $this->province->id, 'name' => 'اردبیل', 'slug' => 'ardabil']);
        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id='.$empty->id)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id=999999')->assertOk()->json('data'));
    }

    public function test_categories_are_unfiltered_until_coverage_data_is_complete(): void
    {
        // هیچ تکنسینی تگِ شهر ندارد → فیچر بدونِ داده → بدونِ محدودیت.
        $this->tech([], [$this->washer->id]);

        $rows = $this->getJson('/v1/customer/services/categories?city_id='.$this->mashhad->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $rows);
    }

    public function test_an_admin_hidden_service_disappears_from_that_city_catalog(): void
    {
        $this->tech([$this->mashhad->id], [$this->washer->id]);
        $this->tech([$this->tehran->id], []);

        \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($this->washer->id, $this->mashhad->id);

        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id='.$this->mashhad->id)->assertOk()->json('data'));
        // تهران دست‌نخورده.
        $this->assertCount(2, $this->getJson('/v1/customer/services/categories?city_id='.$this->tehran->id)->assertOk()->json('data'));
    }

    public function test_brands_are_limited_to_the_citys_covered_brands(): void
    {
        $samsung = Brand::create(['name' => 'سامسونگ', 'slug' => 'samsung']);
        $lg = Brand::create(['name' => 'ال‌جی', 'slug' => 'lg']);
        // هر دو برند برای لباسشویی صفحهٔ ترکیبیِ فعال دارند.
        \Illuminate\Support\Facades\DB::table('crm_device_brand_pages')->insert([
            ['device_id' => $this->washer->id, 'brand_id' => $samsung->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['device_id' => $this->washer->id, 'brand_id' => $lg->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // مشهد: فقط تکنسینِ سامسونگ‌کار؛ تهران: تکنسینِ بدونِ تگِ برند.
        $this->tech([$this->mashhad->id], [$this->washer->id], [$samsung->id]);
        $this->tech([$this->tehran->id], [$this->washer->id]);

        $mashhad = $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id.'&city_id='.$this->mashhad->id)
            ->assertOk()->json('data');
        $this->assertSame(['samsung'], collect($mashhad)->pluck('slug')->all());

        $tehran = $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id.'&city_id='.$this->tehran->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $tehran);

        // بدونِ city_id: رفتارِ قبلی.
        $all = $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $all);
    }

    public function test_cities_report_a_serviceable_flag(): void
    {
        $this->tech([$this->mashhad->id], [$this->washer->id]);

        $rows = collect($this->getJson('/v1/customer/locations/cities')->assertOk()->json('data'))
            ->keyBy('slug');
        $this->assertTrue($rows['mashhad']['serviceable']);
        $this->assertFalse($rows['tehran']['serviceable']);
    }

    public function test_toggling_visibility_bumps_the_app_cache_version(): void
    {
        // bump با timestamp ثانیه‌ای است — مقدارِ ثابت می‌گذاریم تا هم‌ثانیه‌بودن
        // با bumpهای setUp (ساختِ شهر) تست را نشکند.
        \App\Models\Setting::set(\Modules\CustomerApp\Support\AppCacheVersion::KEY, 'fixed-for-test');
        \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($this->washer->id);
        $this->assertNotSame('fixed-for-test', \Modules\CustomerApp\Support\AppCacheVersion::current());
    }
}
