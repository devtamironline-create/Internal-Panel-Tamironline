<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Services\CityPageGenerator;
use Modules\CRM\Services\ServiceCoverage;
use Modules\CRM\Support\CitySlug;
use Tests\TestCase;

/**
 * slugِ انگلیسیِ شهر (کرج → karaj) + بازسازیِ مسیرِ صفحاتِ سئو با تغییرِ slug.
 */
class CitySlugTest extends TestCase
{
    public function test_persian_city_names_map_to_english_slugs(): void
    {
        $this->assertSame('karaj', CitySlug::fromName('کرج'));
        $this->assertSame('tehran', CitySlug::fromName('تهران'));
        $this->assertSame('mashhad', CitySlug::fromName('مشهد'));
        // ورودیِ انگلیسیِ معتبرِ ادمین حفظ می‌شود.
        $this->assertSame('karaj', CitySlug::make('کرج', 'karaj'));
        // ورودیِ فارسی نادیده گرفته و از نام ساخته می‌شود.
        $this->assertSame('karaj', CitySlug::make('کرج', 'کرج'));
        // شهرِ خارج از نگاشت → ترنسلیتِ حرف‌به‌حرف (ascii و غیرخالی).
        $this->assertTrue(CitySlug::isValid(CitySlug::fromName('فلان‌شهر')));
    }

    public function test_rebuild_paths_replaces_the_city_segment(): void
    {
        Schema::create('crm_city_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('city_id'), $x->unsignedBigInteger('province_id')->nullable(),
            $x->string('type', 20), $x->unsignedBigInteger('device_id')->nullable(), $x->unsignedBigInteger('brand_id')->nullable(),
            $x->string('path')->unique(), $x->string('title')->nullable(), $x->string('status', 20)->default('draft'),
            $x->timestamp('published_at')->nullable(), $x->boolean('auto_generated')->default(true), $x->timestamps(),
        ]));

        $city = new City;
        $city->setAttribute('id', 1);
        $city->slug = 'karaj';

        foreach (['/karaj-old', '/karaj-old/services', '/karaj-old/services/washer'] as $p) {
            CityPage::create(['city_id' => 1, 'type' => 'city', 'path' => $p, 'status' => 'draft']);
        }

        $count = (new CityPageGenerator(new ServiceCoverage))->rebuildPathsForCity($city, 'karaj-old');

        $this->assertSame(3, $count);
        $this->assertEqualsCanonicalizing(
            ['/karaj', '/karaj/services', '/karaj/services/washer'],
            CityPage::pluck('path')->all()
        );
    }
}
