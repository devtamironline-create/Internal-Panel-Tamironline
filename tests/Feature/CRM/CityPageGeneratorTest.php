<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Models\Device;
use Modules\CRM\Services\CityPageGenerator;
use Modules\CRM\Services\ServiceCoverage;
use Tests\TestCase;

/**
 * تولیدِ خودکارِ درختِ صفحاتِ سئوِ شهری (SEO-024).
 *
 * ServiceCoverage اینجا با یک fake جایگزین می‌شود تا فقط منطقِ generator
 * سنجیده شود (نه موتورِ پوشش که تگِ تکنسین/استان می‌خواهد).
 */
class CityPageGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_device_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('device_id'), $x->unsignedBigInteger('brand_id'),
            $x->unsignedInteger('sort_order')->default(0),
        ]));
        Schema::create('crm_city_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('city_id'), $x->unsignedBigInteger('province_id')->nullable(),
            $x->string('type', 20), $x->unsignedBigInteger('device_id')->nullable(),
            $x->unsignedBigInteger('brand_id')->nullable(), $x->string('path')->unique(),
            $x->string('title')->nullable(), $x->string('h1')->nullable(),
            $x->text('meta_description')->nullable(), $x->longText('content')->nullable(),
            $x->string('status', 20)->default('draft'), $x->timestamp('published_at')->nullable(),
            $x->boolean('auto_generated')->default(true), $x->timestamps(),
        ]));
        // ساختِ شهر نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    /** generator با جدولِ پوششِ ساختگی. */
    private function generatorWithCoverage(array $table): CityPageGenerator
    {
        $fake = new class($table) extends ServiceCoverage
        {
            public function __construct(private array $fixture) {}

            public function table(): array
            {
                return $this->fixture;
            }
        };

        return new CityPageGenerator($fake);
    }

    public function test_it_builds_the_full_tree_with_only_valid_combos(): void
    {
        $mashhad = City::create(['name' => 'مشهد', 'slug' => 'mashhad', 'province_id' => 7]);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
        $bosch = Brand::create(['name' => 'بوش', 'slug' => 'bosch']);
        $samsung = Brand::create(['name' => 'سامسونگ', 'slug' => 'samsung']);

        // pivotِ اعتبارِ ترکیب: لباسشویی↔(بوش،سامسونگ)، یخچال↔بوش فقط.
        $washer->brands()->attach([$bosch->id, $samsung->id]);
        $fridge->brands()->attach([$bosch->id]);

        // پوشش: لباسشویی با برندهای صریح؛ یخچال با 'all' (→ فقط pivot=بوش).
        $coverage = [
            'coverage_data_complete' => true,
            'services' => [
                ['id' => $washer->id, 'provinces' => [['cities' => [
                    ['city_id' => $mashhad->id, 'brands' => ['bosch', 'samsung'], 'site_visible' => true],
                ]]]],
                ['id' => $fridge->id, 'provinces' => [['cities' => [
                    ['city_id' => $mashhad->id, 'brands' => 'all', 'site_visible' => true],
                ]]]],
            ],
        ];

        $this->generatorWithCoverage($coverage)->sync($mashhad);

        $paths = CityPage::pluck('path')->all();

        // سه صفحهٔ ثابت.
        $this->assertContains('/mashhad', $paths);
        $this->assertContains('/mashhad/services', $paths);
        $this->assertContains('/mashhad/brands', $paths);
        // صفحاتِ دستگاه.
        $this->assertContains('/mashhad/services/washer', $paths);
        $this->assertContains('/mashhad/services/fridge', $paths);
        // صفحاتِ برند (یک‌بار برای هر برندِ شهر).
        $this->assertContains('/mashhad/brands/bosch', $paths);
        $this->assertContains('/mashhad/brands/samsung', $paths);
        // صفحاتِ ترکیبیِ معتبر.
        $this->assertContains('/mashhad/services/washer/bosch', $paths);
        $this->assertContains('/mashhad/services/washer/samsung', $paths);
        $this->assertContains('/mashhad/services/fridge/bosch', $paths);

        // یخچال+سامسونگ معتبر نیست (سامسونگ به یخچال وصل نیست) → صفحه ندارد.
        $this->assertNotContains('/mashhad/services/fridge/samsung', $paths);

        // برندِ سامسونگ فقط یک صفحهٔ برند دارد (نه دوتا).
        $this->assertSame(1, CityPage::where('type', 'brand')->where('brand_id', $samsung->id)->count());
    }

    public function test_all_pages_are_created_as_draft_without_the_agency_word(): void
    {
        $mashhad = City::create(['name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $bosch = Brand::create(['name' => 'بوش', 'slug' => 'bosch']);
        $washer->brands()->attach([$bosch->id]);

        $coverage = ['coverage_data_complete' => true, 'services' => [
            ['id' => $washer->id, 'provinces' => [['cities' => [
                ['city_id' => $mashhad->id, 'brands' => ['bosch'], 'site_visible' => true],
            ]]]],
        ]];

        $this->generatorWithCoverage($coverage)->sync($mashhad);

        // هیچ صفحه‌ای منتشرشده نیست.
        $this->assertSame(0, CityPage::where('status', 'published')->count());
        $this->assertGreaterThan(0, CityPage::where('status', 'draft')->count());

        // عنوانِ صفحهٔ برند «نمایندگی» ندارد.
        $brandPage = CityPage::where('type', 'brand')->first();
        $this->assertSame('تعمیرات لوازم خانگی بوش در مشهد', $brandPage->title);
        $this->assertStringNotContainsString('نمایندگی', $brandPage->title);

        $combo = CityPage::where('type', 'combo')->first();
        $this->assertSame('تعمیر لباسشویی بوش در مشهد', $combo->title);
    }

    public function test_sync_is_idempotent(): void
    {
        $mashhad = City::create(['name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $bosch = Brand::create(['name' => 'بوش', 'slug' => 'bosch']);
        $washer->brands()->attach([$bosch->id]);
        $coverage = ['coverage_data_complete' => true, 'services' => [
            ['id' => $washer->id, 'provinces' => [['cities' => [
                ['city_id' => $mashhad->id, 'brands' => ['bosch'], 'site_visible' => true],
            ]]]],
        ]];
        $gen = $this->generatorWithCoverage($coverage);

        $first = $gen->sync($mashhad);
        $countAfterFirst = CityPage::count();
        $second = $gen->sync($mashhad);

        $this->assertGreaterThan(0, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame($countAfterFirst, CityPage::count());
    }

    public function test_with_no_coverage_only_the_three_static_pages_are_created(): void
    {
        $mashhad = City::create(['name' => 'مشهد', 'slug' => 'mashhad']);

        $this->generatorWithCoverage(['coverage_data_complete' => false, 'services' => []])->sync($mashhad);

        $this->assertSame(3, CityPage::count());
        $this->assertEqualsCanonicalizing(
            ['/mashhad', '/mashhad/services', '/mashhad/brands'],
            CityPage::pluck('path')->all()
        );
    }

    public function test_a_district_gets_no_pages(): void
    {
        $tehran = City::create(['name' => 'تهران', 'slug' => 'tehran']);
        $district = City::create(['name' => 'منطقه ۵', 'slug' => 'r5', 'parent_city_id' => $tehran->id]);

        $result = $this->generatorWithCoverage(['coverage_data_complete' => true, 'services' => []])->sync($district);

        $this->assertTrue($result['skipped'] ?? false);
        $this->assertSame(0, CityPage::where('city_id', $district->id)->count());
    }
}
