<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\CRM\Livewire\OrderWizard;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * «تشخیص خودکار منطقه از آدرس» در ویزارد (۱۴۰۵/۰۶/۰۲):
 *
 *   - اگر متنِ آدرس «منطقه N» دارد، بدونِ نشان همان انتخاب می‌شود.
 *   - وگرنه نشان: آدرس → مختصات (v6/geocoding) → منطقهٔ شهرداری
 *     (v5/reverse → municipality_zone) → ردیفِ منطقهٔ شهر.
 *   - منطقهٔ تشخیصیِ بدونِ تکنسینِ فعال ست نمی‌شود — فقط هشدار
 *     (همان قانونِ «بدونِ تکنسین = فقط لید»).
 *   - اگر نقطهٔ پیداشده در شهرِ دیگری باشد، تشخیص رد می‌شود.
 */
class OrderWizardRegionDetectTest extends TestCase
{
    private Province $province;

    private City $mashhad;

    private City $d3;

    private City $d5;

    private City $d9;

    private Brand $brand;

    private Device $washer;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', function ($t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

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

        foreach (['crm_brands', 'crm_devices'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('is_featured')->default(false);
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_cities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('city_id');
        });

        Schema::create('crm_technician_devices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('device_id');
            $t->integer('priority')->nullable();
        });

        Schema::create('crm_technician_districts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('district_id');
        });

        // ساخت شهر، نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_lead_reasons', function ($t) {
            $t->id();
            $t->string('title')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('phone')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        $this->province = Province::create(['name' => 'خراسان رضوی']);
        $this->mashhad = City::create(['province_id' => $this->province->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $this->d3 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۳', 'slug' => 'r3']);
        $this->d5 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۵', 'slug' => 'r5']);
        $this->d9 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۹', 'slug' => 'r9']);
        $this->brand = Brand::create(['name' => 'اسنوا', 'slug' => 'snowa']);
        $this->washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
    }

    /** تکنسین فعال با تگ شهر/دستگاه/منطقه. */
    private function technician(array $cityIds, array $deviceIds = [], array $districtIds = []): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);
        $t->regions()->sync($districtIds);

        return $t;
    }

    private function wizard()
    {
        return Livewire::test(OrderWizard::class)
            ->set('provinceId', $this->province->id)
            ->set('cityId', $this->mashhad->id)
            ->set('brandId', $this->brand->id)
            ->set('deviceId', $this->washer->id);
    }

    private function fakeNeshan(array $reverse): void
    {
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/v6/geocoding*' => Http::response([
                'status' => 'OK',
                'location' => ['x' => 59.6062, 'y' => 36.2972],
            ]),
            'api.neshan.org/v5/reverse*' => Http::response($reverse),
        ]);
    }

    public function test_a_zone_number_written_in_the_address_selects_the_region_without_neshan(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard()
            ->set('address', 'منطقه ۵، بلوار وکیل‌آباد، پلاک ۱۰')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', $this->d5->id)
            ->assertSet('regionDetectStatus', 'ok');

        // هیچ درخواستی به نشان نرفته (کلید هم config نشده).
        Http::fake();
        Http::assertNothingSent();
    }

    public function test_an_uncovered_detected_region_is_not_selected_and_warns(): void
    {
        // تکنسینِ لباسشویی فقط منطقهٔ ۵ را پوشش می‌دهد؛ آدرس در منطقهٔ ۹ است.
        $this->technician([$this->mashhad->id], [$this->washer->id], [$this->d5->id]);

        $this->wizard()
            ->set('address', 'منطقه ۹، خیابان تست')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'warn');
    }

    public function test_neshan_municipality_zone_selects_the_region(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'مشهد، احمدآباد',
            'state' => 'خراسان رضوی',
            'city' => 'مشهد',
            'municipality_zone' => '3',
            'neighbourhood' => 'احمدآباد',
        ]);

        $this->wizard()
            ->set('address', 'بلوار احمدآباد، خیابان قائم، پلاک ۷')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', $this->d3->id)
            ->assertSet('regionDetectStatus', 'ok');
    }

    public function test_a_point_found_in_another_city_is_rejected(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'تهران، آزادی',
            'state' => 'تهران',
            'city' => 'تهران',
            'municipality_zone' => '9',
            'neighbourhood' => 'آزادی',
        ]);

        $this->wizard()
            ->set('address', 'خیابان آزادی، پلاک ۱')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'warn');
    }

    public function test_without_a_configured_neshan_key_it_fails_gracefully(): void
    {
        config(['services.neshan.service_key' => '']);
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard()
            ->set('address', 'بلوار سجاد، پلاک ۲')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'fail');
    }

    public function test_a_geocoding_key_misconfiguration_gets_a_specific_admin_hint(): void
    {
        // 485 = سرویس geocoding روی کلید فعال نیست — پیام باید بگوید چه
        // چیزی را در پنل نشان فعال کند، نه «آدرس پیدا نشد».
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/v6/geocoding*' => Http::response(['code' => 485], 485),
        ]);
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $wizard = $this->wizard()
            ->set('address', 'بلوار سجاد، پلاک ۲')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'fail');

        $this->assertStringContainsString('تبدیل آدرس به مختصات', $wizard->get('regionDetectMessage'));
    }

    // ─── انتخاب نقطه روی نقشهٔ داخل ویزارد ───────────────────────

    public function test_picking_a_point_on_the_map_selects_the_region_and_fills_an_empty_address(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'مشهد، احمدآباد، خیابان قائم',
            'state' => 'خراسان رضوی',
            'city' => 'مشهد',
            'municipality_zone' => '3',
            'neighbourhood' => 'احمدآباد',
        ]);

        $this->wizard()
            ->call('selectPointOnMap', 36.2972, 59.6067)
            ->assertSet('regionId', $this->d3->id)
            ->assertSet('regionDetectStatus', 'ok')
            ->assertSet('address', 'مشهد، احمدآباد، خیابان قائم')
            // مختصاتِ نقطه برای ذخیره روی آدرسِ مشتری نگه داشته می‌شود.
            ->assertSet('pickedLat', 36.2972)
            ->assertSet('pickedLng', 59.6067);

        // فقط reverse — مسیرِ نقشه به سرویسِ geocoding نیازی ندارد.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'v6/geocoding'));
    }

    public function test_picking_a_point_does_not_overwrite_a_typed_address(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'مشهد، احمدآباد',
            'city' => 'مشهد',
            'municipality_zone' => '5',
            'neighbourhood' => 'احمدآباد',
        ]);

        $this->wizard()
            ->set('address', 'آدرسی که اپراتور تایپ کرده')
            ->call('selectPointOnMap', 36.2972, 59.6067)
            ->assertSet('regionId', $this->d5->id)
            ->assertSet('address', 'آدرسی که اپراتور تایپ کرده');
    }

    public function test_a_map_point_in_another_city_is_rejected(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'تهران، آزادی',
            'city' => 'تهران',
            'municipality_zone' => '9',
            'neighbourhood' => 'آزادی',
        ]);

        $this->wizard()
            ->call('selectPointOnMap', 35.6892, 51.3890)
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'warn')
            // نقطهٔ خارج از شهر ذخیره هم نمی‌شود.
            ->assertSet('pickedLat', null);
    }

    public function test_an_uncovered_region_from_a_map_point_warns_instead_of_selecting(): void
    {
        // پوشش فقط منطقهٔ ۵؛ نقطه در منطقهٔ ۹ → ست نمی‌شود، هشدار.
        $this->technician([$this->mashhad->id], [$this->washer->id], [$this->d5->id]);
        $this->fakeNeshan([
            'formatted_address' => 'مشهد، الهیه',
            'city' => 'مشهد',
            'municipality_zone' => '9',
            'neighbourhood' => 'الهیه',
        ]);

        $this->wizard()
            ->call('selectPointOnMap', 36.35, 59.53)
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'warn');
    }

    // ─── سرچ خیابان/محله روی نقشه ────────────────────────────────

    public function test_map_search_returns_neshan_results(): void
    {
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/v1/search*' => Http::response([
                'count' => 1,
                'items' => [[
                    'title' => 'خیابان قائم',
                    'address' => 'مشهد، احمدآباد، خیابان قائم',
                    'location' => ['x' => 59.61, 'y' => 36.30],
                ]],
            ]),
        ]);
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $wizard = $this->wizard()->set('mapSearchTerm', 'خیابان قائم');

        $results = $wizard->get('mapSearchResults');
        $this->assertCount(1, $results);
        $this->assertSame('خیابان قائم', $results[0]['title']);
        $this->assertSame(36.30, $results[0]['lat']);
    }

    public function test_map_search_falls_back_to_nominatim_when_neshan_is_unavailable(): void
    {
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            // 485 = سرویس جستجو روی کلید فعال نیست → fallback رایگان OSM.
            'api.neshan.org/v1/search*' => Http::response(['code' => 485], 485),
            'nominatim.openstreetmap.org/*' => Http::response([[
                'name' => 'خیابان قائم',
                'display_name' => 'خیابان قائم، احمدآباد، مشهد، ایران',
                'lat' => '36.301',
                'lon' => '59.612',
            ]]),
        ]);
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $wizard = $this->wizard()->set('mapSearchTerm', 'خیابان قائم');

        $results = $wizard->get('mapSearchResults');
        $this->assertCount(1, $results);
        $this->assertSame('خیابان قائم', $results[0]['title']);
        $this->assertSame(36.301, $results[0]['lat']);
    }

    public function test_choosing_a_search_result_runs_the_point_detection(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/v1/search*' => Http::response([
                'count' => 1,
                'items' => [[
                    'title' => 'خیابان قائم',
                    'address' => 'مشهد، احمدآباد',
                    'location' => ['x' => 59.61, 'y' => 36.30],
                ]],
            ]),
            'api.neshan.org/v5/reverse*' => Http::response([
                'formatted_address' => 'مشهد، احمدآباد، خیابان قائم',
                'city' => 'مشهد',
                'municipality_zone' => '3',
                'neighbourhood' => 'احمدآباد',
            ]),
        ]);

        $this->wizard()
            ->set('mapSearchTerm', 'خیابان قائم')
            ->call('chooseSearchResult', 0)
            ->assertSet('regionId', $this->d3->id)
            ->assertSet('regionDetectStatus', 'ok')
            ->assertSet('mapSearchResults', [])
            ->assertSet('mapSearchTerm', '')
            ->assertDispatched('map-goto');
    }

    public function test_a_short_search_term_clears_the_results(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard()
            ->set('mapSearchResults', [['title' => 'x', 'address' => '', 'lat' => 1.0, 'lng' => 1.0]])
            ->set('mapSearchTerm', 'قا')
            ->assertSet('mapSearchResults', []);
    }

    public function test_a_city_without_districts_still_gets_the_map_and_address_autofill(): void
    {
        // شهرِ بدونِ ردیفِ منطقه (مثل ردیف‌های قدیمی/کوچک) — نقشه باید کار
        // کند: موقعیت ثبت و آدرسِ خالی پر می‌شود، بدونِ انتخابِ منطقه.
        $plainCity = City::create(['province_id' => $this->province->id, 'name' => 'نیشابور', 'slug' => 'neyshabur']);
        $this->technician([$plainCity->id], [$this->washer->id]);
        $this->fakeNeshan([
            'formatted_address' => 'نیشابور، خیابان امام',
            'city' => 'نیشابور',
            'municipality_zone' => null,
            'neighbourhood' => null,
        ]);

        Livewire::test(OrderWizard::class)
            ->set('provinceId', $this->province->id)
            ->set('cityId', $plainCity->id)
            ->set('brandId', $this->brand->id)
            ->set('deviceId', $this->washer->id)
            ->call('selectPointOnMap', 36.21, 58.79)
            ->assertSet('regionId', null)
            ->assertSet('regionDetectStatus', 'ok')
            ->assertSet('address', 'نیشابور، خیابان امام');
    }

    // ─── سهمیهٔ تمام‌شدهٔ نشان (481) → مسیرِ کاملاً رایگانِ OSM ─────

    public function test_a_map_point_still_resolves_via_osm_when_the_neshan_quota_is_exhausted(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/*' => Http::response(['status' => 'ERROR', 'code' => 481, 'message' => 'API Key limit exceeded.'], 481),
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'خیابان قائم، احمدآباد، منطقه ۳، مشهد، ایران',
                'address' => [
                    'road' => 'خیابان قائم',
                    'neighbourhood' => 'احمدآباد',
                    'city_district' => 'منطقه ۳',
                    'city' => 'مشهد',
                    'state' => 'خراسان رضوی',
                ],
            ]),
        ]);

        $this->wizard()
            ->call('selectPointOnMap', 36.2972, 59.6067)
            ->assertSet('regionId', $this->d3->id)
            ->assertSet('regionDetectStatus', 'ok');
    }

    public function test_typed_address_detection_works_fully_free_when_the_neshan_quota_is_exhausted(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        config([
            'services.neshan.service_key' => 'test-key',
            'services.neshan.base_url' => 'https://api.neshan.org',
        ]);
        Http::fake([
            'api.neshan.org/*' => Http::response(['status' => 'ERROR', 'code' => 481, 'message' => 'API Key limit exceeded.'], 481),
            'nominatim.openstreetmap.org/search*' => Http::response([[
                'name' => 'خیابان قائم',
                'display_name' => 'خیابان قائم، مشهد',
                'lat' => '36.2972',
                'lon' => '59.6067',
            ]]),
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'خیابان قائم، احمدآباد، منطقه ۳، مشهد، ایران',
                'address' => [
                    'city_district' => 'منطقه ۳',
                    'neighbourhood' => 'احمدآباد',
                    'city' => 'مشهد',
                ],
            ]),
        ]);

        $this->wizard()
            ->set('address', 'بلوار احمدآباد، خیابان قائم، پلاک ۷')
            ->call('detectRegionFromAddress')
            ->assertSet('regionId', $this->d3->id)
            ->assertSet('regionDetectStatus', 'ok');
    }
}
