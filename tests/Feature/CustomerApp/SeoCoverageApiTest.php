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
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function tech(array $cityIds, array $deviceIds = []): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);

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
}
