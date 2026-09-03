<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * کاتالوگِ استان‌محورِ اپ مشتری (خواستهٔ ۱۴۰۵/۰۶/۰۳ — «بر اساسِ استان،
 * نه شهر»):
 *
 *   - پوشش در هر شهری از استان ⇒ کلِ استان آن خدمت را می‌گیرد
 *     (لباسشویی در مشهد ⇒ نیشابور هم لباسشویی می‌بیند).
 *   - categories?city_id=N (استانِ شهر مبنا) یا ?state_id=P مستقیم.
 *   - brands: اجتماعِ برندهای تحتِ پوششِ استان برای آن دستگاه.
 *   - serviceable در استان‌ها و شهرها در سطحِ استان.
 *   - مخفی‌سازیِ ادمین («پوشش خدمات») اعمال می‌شود؛ تا کامل‌نبودنِ
 *     دادهٔ پوشش هیچ محدودیتی نیست.
 */
class ProvinceScopedCatalogTest extends TestCase
{
    private Province $khorasan;

    private Province $tehranProv;

    private City $mashhad;

    private City $neyshabur;

    private City $tehran;

    private Device $washer;

    private Device $fridge;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
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
            $x->json('service_types')->nullable(),
            $x->string('status', 20)->default('active'), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_service_types', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('slug'), $x->string('name'), $x->string('icon')->nullable(),
            $x->text('description')->nullable(), $x->integer('sort_order')->default(0),
            $x->boolean('is_active')->default(true), $x->timestamps(),
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

        $this->khorasan = Province::create(['name' => 'خراسان رضوی']);
        $this->tehranProv = Province::create(['name' => 'تهران']);
        $this->mashhad = City::create(['province_id' => $this->khorasan->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $this->neyshabur = City::create(['province_id' => $this->khorasan->id, 'name' => 'نیشابور', 'slug' => 'neyshabur']);
        $this->tehran = City::create(['province_id' => $this->tehranProv->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $this->washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $this->fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);

        foreach ([['repair', 'تعمیر', 1], ['service', 'سرویس دوره‌ای', 2], ['install', 'نصب', 3]] as [$slug, $name, $sort]) {
            \Modules\CRM\Models\ServiceType::create(['slug' => $slug, 'name' => $name, 'sort_order' => $sort, 'is_active' => true]);
        }
    }

    private function tech(array $cityIds, array $deviceIds = [], array $brandIds = [], ?array $serviceTypes = null): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
            'service_types' => $serviceTypes,
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);
        $t->brands()->sync($brandIds);

        return $t;
    }

    public function test_available_order_types_reflect_technician_service_types(): void
    {
        // لباسشویی در مشهد: فقط تعمیر و نصب. یخچال در مشهد: فقط سرویس.
        $this->tech([$this->mashhad->id], [$this->washer->id], [], ['install', 'repair']);
        $this->tech([$this->mashhad->id], [$this->fridge->id], [], ['service']);

        // نیشابور همان استانِ مشهد است.
        $rows = collect(
            $this->getJson('/v1/customer/services/categories?city_id='.$this->neyshabur->id)
                ->assertOk()->json('data')
        )->keyBy('slug');

        // به ترتیبِ استانداردِ نوع‌ها (repair=1, install=3).
        $this->assertSame(['repair', 'install'], $rows['washer']['available_order_types']);
        $this->assertSame(['service'], $rows['fridge']['available_order_types']);
    }

    public function test_technician_without_service_types_offers_all(): void
    {
        // بدونِ service_types (legacy) → همهٔ نوع‌ها ارائه می‌شود.
        $this->tech([$this->mashhad->id], [$this->washer->id], [], null);

        $rows = collect(
            $this->getJson('/v1/customer/services/categories?city_id='.$this->mashhad->id)
                ->assertOk()->json('data')
        )->keyBy('slug');

        $this->assertSame(['repair', 'service', 'install'], $rows['washer']['available_order_types']);
    }

    public function test_coverage_in_one_city_opens_the_whole_province(): void
    {
        // فقط مشهد تکنسینِ لباسشویی دارد؛ تهران همه‌کاره.
        $this->tech([$this->mashhad->id], [$this->washer->id]);
        $this->tech([$this->tehran->id], []);

        // نیشابور (بدونِ تکنسین، همان استانِ مشهد) → لباسشویی را می‌بیند.
        $neyshabur = $this->getJson('/v1/customer/services/categories?city_id='.$this->neyshabur->id)
            ->assertOk()->json('data');
        $this->assertSame(['washer'], collect($neyshabur)->pluck('slug')->all());

        // state_id مستقیم هم همان است.
        $byState = $this->getJson('/v1/customer/services/categories?state_id='.$this->khorasan->id)
            ->assertOk()->json('data');
        $this->assertSame(['washer'], collect($byState)->pluck('slug')->all());

        // استانِ تهران: همه‌کاره → هر دو دستگاه.
        $tehran = $this->getJson('/v1/customer/services/categories?city_id='.$this->tehran->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $tehran);

        // بدونِ پارامتر: رفتارِ قبلی (همه).
        $this->assertCount(2, $this->getJson('/v1/customer/services/categories')->assertOk()->json('data'));

        // استانِ بدونِ پوشش یا شهرِ نامعتبر → خالی، نه خطا.
        $alborz = Province::create(['name' => 'البرز']);
        $karaj = City::create(['province_id' => $alborz->id, 'name' => 'کرج', 'slug' => 'karaj']);
        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id='.$karaj->id)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id=999999')->assertOk()->json('data'));
    }

    public function test_app_shows_merged_subtypes_not_the_umbrella_parent(): void
    {
        // مدلِ ادغام: والدِ «گاز» برای سایت/مدیریتِ پوشش فعال است ولی در اپ
        // مخفی؛ زیرمجموعه‌ها در سایت مخفی‌اند ولی در اپ نمایش داده می‌شوند.
        $gas = Device::create(['name' => 'گاز', 'slug' => 'gas', 'is_active' => true, 'is_active_app' => false]);
        $stove = Device::create(['name' => 'اجاق گاز', 'slug' => 'stove', 'is_active' => false, 'is_active_app' => true]);
        $cooktop = Device::create(['name' => 'گاز رومیزی', 'slug' => 'cooktop', 'is_active' => false, 'is_active_app' => true]);

        \Modules\CRM\Models\CrmSetting::setJson('coverage.device_aliases', [
            $gas->id => [$stove->id, $cooktop->id],
        ]);

        // تکنسینِ مشهد با تگِ زیرمجموعه (اجاق گاز) → والدِ گاز پوشش می‌گیرد.
        $this->tech([$this->mashhad->id], [$stove->id]);

        $slugs = collect(
            $this->getJson('/v1/customer/services/categories?state_id='.$this->khorasan->id)
                ->assertOk()->json('data')
        )->pluck('slug')->all();

        // اپ باید زیرمجموعه‌ها را نشان دهد، نه والدِ «گاز» را.
        $this->assertContains('stove', $slugs);
        $this->assertContains('cooktop', $slugs);
        $this->assertNotContains('gas', $slugs);
    }

    public function test_no_filtering_until_coverage_data_is_complete(): void
    {
        // هیچ تکنسینی تگِ شهر ندارد → فیچر بدونِ داده → بدونِ محدودیت.
        $this->tech([], [$this->washer->id]);

        $rows = $this->getJson('/v1/customer/services/categories?city_id='.$this->neyshabur->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $rows);
    }

    public function test_an_admin_hidden_service_disappears_from_the_whole_province(): void
    {
        $this->tech([$this->mashhad->id], [$this->washer->id]);
        $this->tech([$this->tehran->id], []);

        // مخفی‌کردنِ لباسشویی در مشهد = تنها شهرِ پوشش‌دهندهٔ استان → کلِ استان خالی.
        \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($this->washer->id, $this->mashhad->id);

        $this->assertSame([], $this->getJson('/v1/customer/services/categories?city_id='.$this->neyshabur->id)->assertOk()->json('data'));
        // استانِ تهران دست‌نخورده.
        $this->assertCount(2, $this->getJson('/v1/customer/services/categories?city_id='.$this->tehran->id)->assertOk()->json('data'));
    }

    public function test_brands_are_the_union_of_the_provinces_covered_brands(): void
    {
        $samsung = Brand::create(['name' => 'سامسونگ', 'slug' => 'samsung']);
        $lg = Brand::create(['name' => 'ال‌جی', 'slug' => 'lg']);
        DB::table('crm_device_brand_pages')->insert([
            ['device_id' => $this->washer->id, 'brand_id' => $samsung->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['device_id' => $this->washer->id, 'brand_id' => $lg->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // خراسان: فقط تکنسینِ سامسونگ‌کار (در مشهد)؛ تهران: بدونِ تگِ برند.
        $this->tech([$this->mashhad->id], [$this->washer->id], [$samsung->id]);
        $this->tech([$this->tehran->id], [$this->washer->id]);

        // نیشابور همان محدودیتِ استانش را می‌گیرد.
        $khorasan = $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id.'&city_id='.$this->neyshabur->id)
            ->assertOk()->json('data');
        $this->assertSame(['samsung'], collect($khorasan)->pluck('slug')->all());

        // تهران: تکنسینِ بدونِ تگِ برند = بدونِ محدودیت.
        $tehran = $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id.'&city_id='.$this->tehran->id)
            ->assertOk()->json('data');
        $this->assertCount(2, $tehran);

        // بدونِ city/state: رفتارِ قبلی.
        $this->assertCount(2, $this->getJson('/v1/customer/services/brands?device_id='.$this->washer->id)->assertOk()->json('data'));
    }

    public function test_states_and_cities_report_province_level_serviceable(): void
    {
        $this->tech([$this->mashhad->id], [$this->washer->id]);

        $states = collect($this->getJson('/v1/customer/locations/states')->assertOk()->json('data'))->keyBy('name');
        $this->assertTrue($states['خراسان رضوی']['serviceable']);
        $this->assertFalse($states['تهران']['serviceable']);

        $cities = collect($this->getJson('/v1/customer/locations/cities')->assertOk()->json('data'))->keyBy('slug');
        $this->assertTrue($cities['mashhad']['serviceable']);
        // نیشابور تکنسین ندارد ولی استانش پوشش دارد → serviceable (استان‌محور).
        $this->assertTrue($cities['neyshabur']['serviceable']);
        $this->assertFalse($cities['tehran']['serviceable']);
    }

    public function test_the_province_capital_comes_first_in_city_lists(): void
    {
        // «تربت حیدریه» الفبایی قبل از «مشهد» است — ولی مرکزِ استان اول می‌آید.
        City::create(['province_id' => $this->khorasan->id, 'name' => 'تربت حیدریه', 'slug' => 'torbat']);
        $this->tech([$this->mashhad->id], [$this->washer->id]);

        $khorasanCities = collect($this->getJson('/v1/customer/locations/cities')->assertOk()->json('data'))
            ->where('state_id', $this->khorasan->id)->pluck('slug')->values();
        $this->assertSame('mashhad', $khorasanCities->first());
    }

    public function test_toggling_visibility_bumps_the_app_cache_version(): void
    {
        \App\Models\Setting::set(\Modules\CustomerApp\Support\AppCacheVersion::KEY, 'fixed-for-test');
        \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($this->washer->id);
        $this->assertNotSame('fixed-for-test', \Modules\CustomerApp\Support\AppCacheVersion::current());
    }
}
