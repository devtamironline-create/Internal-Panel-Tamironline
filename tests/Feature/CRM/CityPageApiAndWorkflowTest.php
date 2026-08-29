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
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function mashhad(): City
    {
        return City::withoutEvents(fn () => City::create(['name' => 'مشهد', 'slug' => 'mashhad']));
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
