<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\IranCoverageMap;
use Tests\TestCase;

/**
 * دادهٔ «نقشهٔ پوشش ایران» + درختِ «مدیریت پوشش»:
 *
 *   - استانی روی نقشه می‌آید که حداقل یک شهر با تکنسینِ تگ‌خوردهٔ فعال
 *     داشته باشد (مثل مشهد در خراسان رضوی).
 *   - کلیدِ استان‌ها نرمال‌شده است تا با نامِ GeoJSON تطبیق کند.
 *   - درختِ مدیریت همهٔ سطح‌ها (حتی بدونِ تکنسین) را با شمارش می‌دهد؛
 *     شمارشِ منطقه معنای سیستمِ تخصیص را دارد (بدونِ تگ = کلِ شهر).
 *   - فایلِ GeoJSON هر ۳۱ استان را دارد.
 */
class IranCoverageMapTest extends TestCase
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
            $x->id(), $x->string('first_name')->nullable(), $x->string('firstname_tech')->nullable(),
            $x->string('mobile')->nullable(),
            $x->string('status', 20)->default('active'), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_technician_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('city_id'),
        ]));
        Schema::create('crm_technician_districts', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('district_id'),
        ]));
        Schema::create('crm_technician_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('device_id'),
            $x->integer('priority')->nullable(),
        ]));
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function tech(array $cityIds, array $deviceIds = [], array $districtIds = []): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);
        $t->regions()->sync($districtIds);

        return $t;
    }

    public function test_mashhad_appears_under_its_normalized_province_key(): void
    {
        $khorasan = Province::create(['name' => 'خراسان رضوی']);
        $mashhad = City::create(['province_id' => $khorasan->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        Province::create(['name' => 'اردبیل']); // بدونِ تکنسین — نباید بیاید
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);

        $this->tech([$mashhad->id], [$washer->id]);

        $data = app(IranCoverageMap::class)->build();

        $key = IranCoverageMap::normalizeName('خراسان رضوی');
        $this->assertArrayHasKey($key, $data['provinces']);
        $this->assertSame(1, $data['covered_province_count']);
        $this->assertSame(1, $data['provinces'][$key]['tech_count']);
        $this->assertSame('مشهد', $data['provinces'][$key]['cities'][0]['name']);
        $this->assertSame(['لباسشویی'], $data['provinces'][$key]['cities'][0]['device_names']);
        // نرمال‌سازی، فاصله و نیم‌فاصله را یکی می‌کند (تطبیق با GeoJSON).
        $this->assertSame($key, IranCoverageMap::normalizeName('خراسان‌رضوی'));
    }

    public function test_the_manage_tree_counts_district_coverage_like_the_assignment_rules(): void
    {
        $province = Province::create(['name' => 'تهران']);
        $tehran = City::create(['province_id' => $province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $d1 = City::create(['province_id' => $province->id, 'parent_city_id' => $tehran->id, 'name' => 'منطقه ۱', 'slug' => 'r1']);
        $d2 = City::create(['province_id' => $province->id, 'parent_city_id' => $tehran->id, 'name' => 'منطقه ۲', 'slug' => 'r2']);

        $this->tech([$tehran->id]);                    // کل شهر → هر دو منطقه
        $this->tech([$tehran->id], [], [$d1->id]);     // فقط منطقه ۱

        $tree = app(IranCoverageMap::class)->manageTree();
        $city = collect($tree)->firstWhere('name', 'تهران')['cities'][0];

        $this->assertSame(2, $city['tech_count']);
        $districts = collect($city['districts'])->keyBy('name');
        $this->assertSame(2, $districts['منطقه ۱']['tech_count']);
        $this->assertSame(1, $districts['منطقه ۲']['tech_count']);
    }

    public function test_the_static_geojson_ships_all_31_provinces(): void
    {
        $geo = app(IranCoverageMap::class)->geojson();

        $this->assertNotNull($geo);
        $this->assertCount(31, $geo['features']);
        $names = collect($geo['features'])->pluck('properties.name');
        $this->assertTrue($names->contains('خراسان رضوی'));
        $this->assertTrue($names->contains('تهران'));
        $this->assertTrue($names->contains('کهگیلویه و بویراحمد'));
    }
}
