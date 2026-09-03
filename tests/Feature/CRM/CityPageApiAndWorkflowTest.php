<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Tests\TestCase;

/**
 * وضعیتِ انتشار + API عمومیِ صفحاتِ سئوِ شهری (SEO-024):
 *   - فقط صفحاتِ منتشرشده در API می‌آیند؛ پیش‌نویس ۴۰۴ می‌شود.
 *   - publish/unpublish وضعیت و published_at را درست تنظیم می‌کنند.
 *   - گذارِ نامعتبرِ وضعیت خطا می‌دهد.
 */
class CityPageApiAndWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('faqs', fn ($t) => tap($t, fn ($x) => [
            $x->ulid('id')->primary(), $x->string('question'), $x->longText('answer')->nullable(),
            $x->timestamps(),
        ]));
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('province_id')->nullable(),
            $x->unsignedBigInteger('parent_city_id')->nullable(),
            $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_city_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('city_id'), $x->unsignedBigInteger('province_id')->nullable(),
            $x->string('type', 20), $x->unsignedBigInteger('device_id')->nullable(),
            $x->unsignedBigInteger('brand_id')->nullable(), $x->string('path')->unique(),
            $x->string('title')->nullable(), $x->string('h1')->nullable(),
            $x->string('meta_title')->nullable(), $x->text('meta_description')->nullable(),
            $x->longText('content')->nullable(),
            $x->string('eyebrow')->nullable(), $x->string('subtitle')->nullable(), $x->string('caption')->nullable(),
            $x->json('hero_image')->nullable(), $x->json('sections_enabled')->nullable(),
            $x->string('cta_primary_label')->nullable(), $x->string('cta_primary_url')->nullable(), $x->string('cta_primary_icon')->nullable(),
            $x->string('cta_secondary_label')->nullable(), $x->string('cta_secondary_url')->nullable(), $x->string('cta_secondary_icon')->nullable(),
            $x->string('steps_image_desktop')->nullable(), $x->string('steps_image_mobile')->nullable(),
            $x->string('status', 20)->default('draft'), $x->timestamp('published_at')->nullable(),
            $x->boolean('auto_generated')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_city_page_faqs', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('page_id'), $x->ulid('faq_id'), $x->unsignedTinyInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_city_page_faq_categories', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('page_id'), $x->unsignedBigInteger('taxonomy_id'), $x->unsignedTinyInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_city_page_reviews', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('page_id'), $x->ulid('review_id'), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(), $x->boolean('is_active')->default(true),
            $x->json('hero_image')->nullable(), $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->json('hero_image')->nullable(), $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    public function test_dynamic_tokens_are_resolved_in_page_text(): void
    {
        $city = $this->mashhad();
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_CITY, 'path' => '/mashhad',
            'title' => 'تعمیرات در {city}', 'caption' => 'خدماتِ ما در {city}',
            'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad')
            ->assertOk()
            ->assertJsonPath('data.title', 'تعمیرات در مشهد')
            ->assertJsonPath('data.caption', 'خدماتِ ما در مشهد');
    }

    public function test_empty_hero_falls_back_to_the_device_image(): void
    {
        $city = $this->mashhad();
        $washer = \Modules\CRM\Models\Device::create([
            'name' => 'لباسشویی', 'slug' => 'washing-machine',
            'hero_image' => ['mobile' => ['url' => 'https://cdn/washer.jpg', 'alt' => 'لباسشویی']],
        ]);
        // صفحهٔ «لباسشویی در مشهد» بدونِ عکس → باید از عکسِ دستگاه بخواند.
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $washer->id,
            'path' => '/mashhad/services/washing-machine', 'title' => 'تعمیر لباسشویی در مشهد',
            'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(), 'hero_image' => null,
        ]);

        $res = $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services/washing-machine');
        $res->assertOk()->assertJsonPath('data.hero_image.mobile.url', 'https://cdn/washer.jpg');
    }

    public function test_services_page_lists_published_device_children(): void
    {
        $city = $this->mashhad();
        $washer = \Modules\CRM\Models\Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        $fridge = \Modules\CRM\Models\Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
        $oven = \Modules\CRM\Models\Device::create(['name' => 'اجاق', 'slug' => 'oven']);

        CityPage::create(['city_id' => $city->id, 'type' => CityPage::TYPE_SERVICES, 'path' => '/mashhad/services',
            'title' => 'خدمات در مشهد', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now()]);
        CityPage::create(['city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $washer->id,
            'path' => '/mashhad/services/washing-machine', 'title' => 'لباسشویی', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now()]);
        CityPage::create(['city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $fridge->id,
            'path' => '/mashhad/services/fridge', 'title' => 'یخچال', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now()]);
        // پیش‌نویس → نباید در فرزندان بیاید.
        CityPage::create(['city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $oven->id,
            'path' => '/mashhad/services/oven', 'title' => 'اجاق', 'status' => CityPage::STATUS_DRAFT]);

        $res = $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services')->assertOk();
        $paths = collect($res->json('data.children'))->pluck('path')->all();

        $this->assertContains('/mashhad/services/washing-machine', $paths);
        $this->assertContains('/mashhad/services/fridge', $paths);
        $this->assertNotContains('/mashhad/services/oven', $paths); // پیش‌نویس
        $this->assertCount(2, $paths);
    }

    public function test_combo_hero_falls_back_to_the_device_in_city_page(): void
    {
        $city = $this->mashhad();
        $washer = \Modules\CRM\Models\Device::create([
            'name' => 'لباسشویی', 'slug' => 'washing-machine',
            'hero_image' => ['mobile' => ['url' => 'https://cdn/global-washer.jpg', 'alt' => 'x']],
        ]);
        $bosch = \Modules\CRM\Models\Brand::create(['name' => 'بوش', 'slug' => 'bosch']);

        // «لباسشویی در مشهد» عکسِ اختصاصیِ شهری دارد.
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $washer->id,
            'path' => '/mashhad/services/washing-machine', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
            'hero_image' => ['mobile' => ['url' => 'https://cdn/washer-in-mashhad.jpg', 'alt' => 'لباسشویی مشهد']],
        ]);
        // «لباسشویی بوش در مشهد» عکس ندارد → باید از «لباسشویی در مشهد» بخواند، نه دستگاهِ سراسری.
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_COMBO, 'device_id' => $washer->id, 'brand_id' => $bosch->id,
            'path' => '/mashhad/services/washing-machine/bosch', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
            'hero_image' => null,
        ]);

        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services/washing-machine/bosch')
            ->assertOk()
            ->assertJsonPath('data.hero_image.mobile.url', 'https://cdn/washer-in-mashhad.jpg');
    }

    public function test_combo_page_has_auto_breadcrumbs_and_works_standalone(): void
    {
        $city = $this->mashhad();
        $washer = \Modules\CRM\Models\Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        $bosch = \Modules\CRM\Models\Brand::create(['name' => 'بوش', 'slug' => 'bosch']);

        // فقط همین یک صفحه لایو است — هیچ صفحهٔ والدی منتشر نشده.
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_COMBO,
            'device_id' => $washer->id, 'brand_id' => $bosch->id,
            'path' => '/mashhad/services/washing-machine/bosch',
            'title' => 'تعمیر لباسشویی بوش در مشهد',
            'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        $res = $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services/washing-machine/bosch');
        $res->assertOk();
        $crumbs = $res->json('data.breadcrumbs');

        // خانه ← مشهد ← خدمات ← لباسشویی ← (فعلی) — مسیرها زیرِ /city (جز خانه)
        $this->assertCount(5, $crumbs);
        $this->assertSame('/', $crumbs[0]['path']);
        $this->assertSame('/city/mashhad', $crumbs[1]['path']);
        $this->assertSame('/city/mashhad/services', $crumbs[2]['path']);
        $this->assertSame('/city/mashhad/services/washing-machine', $crumbs[3]['path']);
        $this->assertSame('/city/mashhad/services/washing-machine/bosch', $crumbs[4]['path']);
        $this->assertTrue($crumbs[4]['current']);
        $this->assertFalse($crumbs[0]['current']);
    }

    private function mashhad(): City
    {
        return City::withoutEvents(fn () => City::create(['name' => 'مشهد', 'slug' => 'mashhad']));
    }

    public function test_payload_exposes_province_object_and_hub_faq_with_tokens(): void
    {
        $province = \Modules\CRM\Models\Province::create(['name' => 'خراسان رضوی', 'slug' => 'khorasan-razavi']);
        $city = City::withoutEvents(fn () => City::create(['name' => 'مشهد', 'slug' => 'mashhad', 'province_id' => $province->id]));

        $page = CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_SERVICES, 'path' => '/mashhad/services',
            'title' => 'خدمات در {city}', 'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        $faq = \Modules\Site\Models\Faq::create([
            'question' => 'در {city} چطور سفارش دهم؟',
            'answer' => 'در {province} خدمت می‌دهیم.',
        ]);
        $page->faqs()->attach($faq->id, ['sort_order' => 1]);

        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services')
            ->assertOk()
            ->assertJsonPath('data.province.name', 'خراسان رضوی')
            ->assertJsonPath('data.province.slug', 'khorasan-razavi')
            ->assertJsonPath('data.faq.0.q', 'در مشهد چطور سفارش دهم؟')
            ->assertJsonPath('data.faq.0.a', 'در خراسان رضوی خدمت می‌دهیم.');
    }

    public function test_device_page_has_no_hub_faq(): void
    {
        $city = $this->mashhad();
        $dev = \Modules\CRM\Models\Device::create(['name' => 'لباسشویی', 'slug' => 'washing-machine']);
        CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_DEVICE, 'device_id' => $dev->id,
            'path' => '/mashhad/services/washing-machine', 'title' => 't',
            'status' => CityPage::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        // صفحهٔ دستگاه FAQ را از کاتالوگ می‌خواند؛ payloadِ city نباید faq بدهد.
        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services/washing-machine')
            ->assertOk()
            ->assertJsonPath('data.faq', null);
    }

    private function page(City $city, string $path, string $status = CityPage::STATUS_DRAFT): CityPage
    {
        return CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_CITY, 'path' => $path,
            'title' => 'تعمیرات لوازم خانگی در مشهد', 'status' => $status,
            'published_at' => $status === CityPage::STATUS_PUBLISHED ? now() : null,
        ]);
    }

    public function test_publish_sets_status_and_timestamp_then_unpublish_clears_it(): void
    {
        $page = $this->page($this->mashhad(), '/mashhad');

        $this->assertFalse($page->isPublished());
        $page->publish();
        $this->assertTrue($page->fresh()->isPublished());
        $this->assertNotNull($page->fresh()->published_at);

        $page->unpublish();
        $this->assertFalse($page->fresh()->isPublished());
        $this->assertNull($page->fresh()->published_at);
        $this->assertSame(CityPage::STATUS_DRAFT, $page->fresh()->status);
    }

    public function test_invalid_status_transition_throws(): void
    {
        $page = $this->page($this->mashhad(), '/mashhad', CityPage::STATUS_ARCHIVED);

        // بایگانی فقط به پیش‌نویس می‌رود، مستقیم به منتشرشده نه.
        $this->expectException(ValidationException::class);
        $page->transitionStatusTo(CityPage::STATUS_PUBLISHED);
    }

    public function test_api_returns_only_published_pages(): void
    {
        $city = $this->mashhad();
        $this->page($city, '/mashhad', CityPage::STATUS_PUBLISHED);
        $this->page($city, '/mashhad/services', CityPage::STATUS_DRAFT);

        $res = $this->getJson('/v1/customer/seo/city-pages');
        $res->assertOk();
        $paths = collect($res->json('data'))->pluck('path')->all();

        $this->assertContains('/mashhad', $paths);
        $this->assertNotContains('/mashhad/services', $paths); // پیش‌نویس
        $this->assertCount(1, $res->json('data'));
    }

    public function test_api_path_lookup_404s_for_unpublished_or_unknown(): void
    {
        $city = $this->mashhad();
        $this->page($city, '/mashhad', CityPage::STATUS_PUBLISHED);
        $this->page($city, '/mashhad/services', CityPage::STATUS_DRAFT);

        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad')->assertOk()
            ->assertJsonPath('data.path', '/mashhad');

        // پیش‌نویس → ۴۰۴ واقعی
        $this->getJson('/v1/customer/seo/city-pages?path=/mashhad/services')->assertNotFound();
        // مسیرِ ناموجود → ۴۰۴
        $this->getJson('/v1/customer/seo/city-pages?path=/tehran')->assertNotFound();
    }
}
