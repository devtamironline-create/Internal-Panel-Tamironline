<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * جدولِ پوششِ خدمات (source-of-truth سئو — SEO-007/014/023):
 *
 *   - فقط شهرهایی که تکنسینِ فعال با تگِ صریحِ همان شهر دارند می‌آیند.
 *   - دستگاه‌های هر شهر از تگِ مهارتِ تکنسین‌ها (خالی = همه‌کاره).
 *   - بدونِ هیچ تگِ شهری، coverage_data_complete=false تا سایت به دادهٔ
 *     ناقص برای schema تکیه نکند.
 */
class SeoCoverageApiTest extends TestCase
{
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
            $x->boolean('is_active')->default(true),
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
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_technician_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('brand_id'),
        ]));
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        // کنترلِ «نمایش در سایت» (coverage.site_hidden) اینجا می‌نشیند.
        Schema::create('crm_settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(), $x->timestamps(),
        ]));
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

    public function test_only_cities_with_explicitly_tagged_technicians_are_listed(): void
    {
        $province = Province::create(['name' => 'تهران']);
        $tehran = City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $district = City::create(['province_id' => $province->id, 'parent_city_id' => $tehran->id, 'name' => 'منطقه ۵', 'slug' => 'region-5']);
        $mashhad = City::create(['province_id' => $province->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        Device::create(['name' => 'یخچال', 'slug' => 'refrigerator']);

        $this->tech([$tehran->id], [$washer->id]);
        $this->tech([], []); // بدونِ تگ شهر — نباید مشهد را باز کند

        $json = $this->getJson('/v1/customer/seo/coverage')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->json('data');

        $this->assertTrue($json['coverage_data_complete']);
        $cities = collect($json['cities']);
        $this->assertCount(1, $cities);
        $this->assertSame('تهران', $cities[0]['city']);
        $this->assertSame(['منطقه ۵'], $cities[0]['districts']);
        $this->assertFalse($cities[0]['all_devices']);
        $this->assertSame([['name' => 'لباسشویی', 'slug' => 'washing-machine']], $cities[0]['devices']);
        $this->assertNull($cities->firstWhere('city', 'مشهد'));
    }

    public function test_a_generalist_covers_all_devices_and_missing_tags_flag_the_data_incomplete(): void
    {
        $province = Province::create(['name' => 'البرز']);
        $karaj = City::create(['province_id' => $province->id, 'name' => 'کرج', 'slug' => 'karaj']);
        Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        Device::create(['name' => 'ظرفشویی', 'slug' => 'dishwasher']);

        // همه‌کارهٔ تگ‌خورده در کرج → هر دو دستگاه.
        $this->tech([$karaj->id], []);

        $json = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');
        $karajRow = collect($json['cities'])->firstWhere('city', 'کرج');
        $this->assertTrue($karajRow['all_devices']);
        $this->assertCount(2, $karajRow['devices']);

        // بدونِ هیچ تگِ شهری در کلِ سیستم → پرچمِ ناقص‌بودنِ داده.
        \Modules\CRM\Services\ServiceCoverage::forget();
        \Illuminate\Support\Facades\DB::table('crm_technician_cities')->delete();

        $json2 = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');
        $this->assertFalse($json2['coverage_data_complete']);
        $this->assertSame([], $json2['cities']);
    }

    /**
     * نمای خدمت‌محور (۱۴۰۵/۰۶/۰۲): خدمت → استان → شهرها + برندها — برای
     * صفحهٔ هر خدمت و صفحاتِ ترکیبی («لباسشویی سامسونگ در مشهد»).
     */
    public function test_the_service_view_groups_cities_by_province_with_brand_restrictions(): void
    {
        $tehranProv = Province::create(['name' => 'تهران']);
        $khorasan = Province::create(['name' => 'خراسان رضوی']);
        $tehran = City::create(['province_id' => $tehranProv->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $mashhad = City::create(['province_id' => $khorasan->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        $fridge = Device::create(['name' => 'یخچال', 'slug' => 'refrigerator']);
        $samsung = \Modules\CRM\Models\Brand::create(['name' => 'سامسونگ', 'slug' => 'samsung']);
        \Modules\CRM\Models\Brand::create(['name' => 'ال‌جی', 'slug' => 'lg']);

        // تهران: تکنسینِ لباسشویی بدونِ تگِ برند → همهٔ برندها.
        $this->tech([$tehran->id], [$washer->id]);
        // مشهد: تکنسینِ لباسشویی فقط با تگِ سامسونگ → صفحهٔ ترکیبیِ فقط سامسونگ.
        $this->tech([$mashhad->id], [$washer->id], [$samsung->id]);

        $json = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');

        $services = collect($json['services']);
        $this->assertCount(1, $services); // یخچال هیچ‌جا پوشش ندارد → نمی‌آید
        $washerRow = $services->firstWhere('slug', 'washing-machine');
        $this->assertSame(2, $washerRow['province_count']);
        $this->assertSame(2, $washerRow['city_count']);

        $byProvince = collect($washerRow['provinces'])->keyBy('name');
        $this->assertSame('all', $byProvince['تهران']['cities'][0]['brands']);
        $this->assertSame(['samsung'], $byProvince['خراسان رضوی']['cities'][0]['brands']);
        $this->assertSame(1, $byProvince['خراسان رضوی']['cities'][0]['technician_count']);

        // لیستِ برندها برای نگاشتِ slug → نام در مصرف‌کننده.
        $this->assertEqualsCanonicalizing(['سامسونگ', 'ال‌جی'], collect($json['brands'])->pluck('name')->all());

        // forDevice — کارتِ «پوشش این خدمت» در ویرایشِ دستگاه.
        $coverage = app(\Modules\CRM\Services\ServiceCoverage::class);
        $this->assertSame($washerRow['city_count'], $coverage->forDevice($washer->id)['city_count']);
        $this->assertNull($coverage->forDevice($fridge->id));
    }

    /**
     * کنترلِ دستیِ ادمین (۱۴۰۵/۰۶/۰۲): «مخفی از سایت» فقط خروجیِ API را
     * کم می‌کند — پنل (table) همه را با فلگِ site_visible می‌بیند.
     */
    public function test_hiding_a_service_city_removes_it_from_the_site_api_but_not_the_panel_view(): void
    {
        $province = Province::create(['name' => 'تهران']);
        $tehran = City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $mashhad = City::create(['province_id' => $province->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);

        $this->tech([$tehran->id], [$washer->id]);
        $this->tech([$mashhad->id], [$washer->id]);

        // مخفی‌کردنِ «لباسشویی در مشهد» از سایت.
        $visible = \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($washer->id, $mashhad->id);
        $this->assertFalse($visible);

        // API سایت: مشهد برای لباسشویی نیست؛ تهران هست.
        $json = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');
        $washerRow = collect($json['services'])->firstWhere('slug', 'washing-machine');
        $cityNames = collect($washerRow['provinces'])->flatMap(fn ($p) => collect($p['cities'])->pluck('name'));
        $this->assertFalse($cityNames->contains('مشهد'));
        $this->assertTrue($cityNames->contains('تهران'));
        $this->assertSame(1, $washerRow['city_count']);
        // نمای شهرمحورِ سایت هم مشهد را برای لباسشویی ندارد → ردیفش حذف.
        $this->assertNull(collect($json['cities'])->firstWhere('city', 'مشهد'));

        // پنل: هر دو شهر دیده می‌شوند؛ مشهد با فلگِ site_visible=false.
        $panel = app(\Modules\CRM\Services\ServiceCoverage::class)->table();
        $panelCities = collect(collect($panel['services'])->firstWhere('slug', 'washing-machine')['provinces'])
            ->flatMap(fn ($p) => $p['cities'])->keyBy('name');
        $this->assertTrue($panelCities['تهران']['site_visible']);
        $this->assertFalse($panelCities['مشهد']['site_visible']);

        // تاگلِ دوباره → برمی‌گردد.
        $this->assertTrue(\Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($washer->id, $mashhad->id));
        $json2 = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');
        $this->assertSame(2, collect($json2['services'])->firstWhere('slug', 'washing-machine')['city_count']);
    }

    public function test_hiding_a_whole_service_removes_it_from_the_site_api(): void
    {
        $province = Province::create(['name' => 'تهران']);
        $tehran = City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        $fridge = Device::create(['name' => 'یخچال', 'slug' => 'refrigerator']);

        $this->tech([$tehran->id], [$washer->id, $fridge->id]);

        \Modules\CRM\Services\ServiceCoverage::toggleSiteVisibility($fridge->id);

        $json = $this->getJson('/v1/customer/seo/coverage')->assertOk()->json('data');
        $this->assertNull(collect($json['services'])->firstWhere('slug', 'refrigerator'));
        $this->assertNotNull(collect($json['services'])->firstWhere('slug', 'washing-machine'));
        // در نمای شهرمحور هم یخچال از لیستِ تهران حذف شده.
        $tehranRow = collect($json['cities'])->firstWhere('city', 'تهران');
        $this->assertSame(['washing-machine'], collect($tehranRow['devices'])->pluck('slug')->all());
    }
}
