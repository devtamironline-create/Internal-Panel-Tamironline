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

    public function test_changing_city_slug_recomputes_all_child_paths(): void
    {
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->unsignedBigInteger('parent_city_id')->nullable(), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(), $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_city_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('city_id'), $x->unsignedBigInteger('province_id')->nullable(),
            $x->string('type', 20), $x->unsignedBigInteger('device_id')->nullable(), $x->unsignedBigInteger('brand_id')->nullable(),
            $x->string('path')->unique(), $x->string('title')->nullable(), $x->string('status', 20)->default('draft'),
            $x->timestamp('published_at')->nullable(), $x->boolean('auto_generated')->default(true), $x->timestamps(),
        ]));
        // ساختِ شهر/دستگاه نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));

        $city = City::create(['name' => 'کرج', 'slug' => 'karaj']);
        $washer = \Modules\CRM\Models\Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);

        // مسیرهای «قدیمی» (انگار slug قبلاً فارسی/دیگر بوده).
        CityPage::create(['city_id' => $city->id, 'type' => 'city', 'path' => '/old', 'status' => 'draft']);
        CityPage::create(['city_id' => $city->id, 'type' => 'services', 'path' => '/old/services', 'status' => 'draft']);
        CityPage::create(['city_id' => $city->id, 'type' => 'device', 'device_id' => $washer->id, 'path' => '/old/services/washing-machine', 'status' => 'draft']);

        $count = (new CityPageGenerator(new ServiceCoverage))->recomputePathsForCity($city);

        $this->assertSame(3, $count);
        $this->assertEqualsCanonicalizing(
            ['/karaj', '/karaj/services', '/karaj/services/washing-machine'],
            CityPage::pluck('path')->all()
        );
    }
}
