<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\TehranCoverageMap;
use Tests\TestCase;

/**
 * دادهٔ «نقشهٔ پوشش تهران» — قواعد:
 *
 *   - فقط تکنسینِ فعالِ با تگِ صریحِ تهران روی نقشه می‌آید (هم‌راستا با
 *     محدودیتِ پوششِ فرمِ ثبتِ سفارش).
 *   - بدونِ تگِ منطقه = کلِ ۲۲ منطقه؛ با تگ = فقط همان مناطق.
 *   - تگِ دستگاهِ خالی = همه‌کاره (device_ids کاملِ منطقه).
 *   - تکنسین‌های بدونِ تگِ شهر جدا شمارش می‌شوند (بنر هشدار).
 */
class TehranCoverageMapTest extends TestCase
{
    private City $tehran;

    /** @var array<int, City> */
    private array $districts = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_cities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('parent_city_id')->nullable();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

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

        // ساختِ کشِ نسخهٔ اپ هنگام ساختِ شهر.
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));

        $this->tehran = City::create(['name' => 'تهران', 'slug' => 'tehran']);
        foreach ([1, 5, 22] as $n) {
            $fa = str_replace(
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
                (string) $n
            );
            $this->districts[$n] = City::create([
                'parent_city_id' => $this->tehran->id, 'name' => 'منطقه '.$fa, 'slug' => 'region-'.$n,
            ]);
        }
    }

    private function tech(array $cityIds, array $districtIds = [], array $deviceIds = [], string $status = 'active'): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => $status,
        ]);
        $t->cities()->sync($cityIds);
        $t->regions()->sync($districtIds);
        $t->devices()->sync($deviceIds);

        return $t;
    }

    public function test_it_returns_null_when_tehran_is_not_defined(): void
    {
        City::whereKey($this->tehran->id)->delete();

        $this->assertNull(app(TehranCoverageMap::class)->build());
    }

    public function test_a_tehran_tech_without_region_tags_covers_all_22_districts(): void
    {
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $this->tech([$this->tehran->id], [], [$washer->id]);

        $data = app(TehranCoverageMap::class)->build();

        $this->assertSame(22, $data['covered_count']);
        $this->assertSame(1, $data['districts'][7]['tech_count']);
        $this->assertSame([$washer->id], $data['districts'][7]['device_ids']);
        $this->assertTrue($data['districts'][7]['technicians'][0]['whole_city']);
    }

    public function test_a_region_tagged_tech_covers_only_those_districts(): void
    {
        $this->tech([$this->tehran->id], [$this->districts[5]->id]);

        $data = app(TehranCoverageMap::class)->build();

        $this->assertSame(1, $data['covered_count']);
        $this->assertSame(1, $data['districts'][5]['tech_count']);
        $this->assertSame(0, $data['districts'][1]['tech_count']);
        $this->assertSame(0, $data['districts'][22]['tech_count']);
    }

    public function test_untagged_and_inactive_technicians_stay_off_the_map(): void
    {
        $this->tech([]); // فعال ولی بدونِ تگِ شهر
        $this->tech([$this->tehran->id], [], [], status: 'inactive');

        $data = app(TehranCoverageMap::class)->build();

        $this->assertSame(0, $data['covered_count']);
        $this->assertSame(0, $data['tehran_tech_count']);
        $this->assertSame(1, $data['untagged_tech_count']);
    }

    public function test_an_all_rounder_marks_every_device_covered(): void
    {
        Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
        $this->tech([$this->tehran->id]); // بدونِ تگِ دستگاه = همه‌کاره

        $data = app(TehranCoverageMap::class)->build();

        $this->assertTrue($data['districts'][3]['all_devices']);
        $this->assertCount(2, $data['districts'][3]['device_ids']);
    }

    public function test_district_numbers_are_parsed_from_persian_and_latin_names(): void
    {
        $map = app(TehranCoverageMap::class);

        $this->assertSame(12, $map->districtNumber('منطقه ۱۲'));
        $this->assertSame(3, $map->districtNumber('منطقه 3'));
        $this->assertSame(22, $map->districtNumber('منطقه ۲۲ تهران'));
        $this->assertNull($map->districtNumber('منطقه سی')); // بدونِ رقم
        $this->assertNull($map->districtNumber('منطقه ۹۹')); // خارجِ بازه
    }

    public function test_the_static_geojson_ships_all_22_districts(): void
    {
        $geo = app(TehranCoverageMap::class)->geojson();

        $this->assertNotNull($geo);
        $this->assertCount(22, $geo['features']);
        $numbers = collect($geo['features'])->pluck('properties.district')->sort()->values()->all();
        $this->assertSame(range(1, 22), $numbers);
    }
}
