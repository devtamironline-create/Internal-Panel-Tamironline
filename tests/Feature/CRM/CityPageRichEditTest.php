<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Tests\TestCase;

/**
 * ویرایشگرِ محتوایِ غنیِ صفحهٔ سئوِ شهری (فاز B): فرمِ ویرایش همان
 * فیلدهایِ صفحاتِ اصلی (eyebrow/subtitle/caption/hero/CTA/مراحل/سکشن‌ها)
 * را ذخیره می‌کند و پس از ویرایشِ دستی، صفحه دیگر «خودکار» نیست.
 */
class CityPageRichEditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--path' => 'database/migrations/0001_01_01_000000_create_users_table.php', '--force' => true]);
        Artisan::call('migrate', ['--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php', '--force' => true]);

        Schema::table('users', function ($t) {
            foreach (['first_name', 'mobile'] as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $t->string($c)->nullable();
                }
            }
        });

        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('province_id')->nullable(), $x->unsignedBigInteger('parent_city_id')->nullable(),
            $x->string('name'), $x->string('slug')->nullable(), $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_city_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('city_id'), $x->unsignedBigInteger('province_id')->nullable(),
            $x->string('type', 20), $x->unsignedBigInteger('device_id')->nullable(), $x->unsignedBigInteger('brand_id')->nullable(),
            $x->string('path')->unique(), $x->string('title')->nullable(), $x->string('h1')->nullable(),
            $x->string('meta_title')->nullable(), $x->text('meta_description')->nullable(), $x->longText('content')->nullable(),
            $x->string('eyebrow')->nullable(), $x->string('subtitle')->nullable(), $x->string('caption')->nullable(),
            $x->json('hero_image')->nullable(), $x->json('sections_enabled')->nullable(),
            $x->string('cta_primary_label')->nullable(), $x->string('cta_primary_url')->nullable(), $x->string('cta_primary_icon')->nullable(),
            $x->string('cta_secondary_label')->nullable(), $x->string('cta_secondary_url')->nullable(), $x->string('cta_secondary_icon')->nullable(),
            $x->string('steps_image_desktop')->nullable(), $x->string('steps_image_mobile')->nullable(),
            $x->string('status', 20)->default('draft'), $x->timestamp('published_at')->nullable(),
            $x->boolean('auto_generated')->default(true), $x->timestamps(),
        ]));
        foreach (['crm_city_page_faqs', 'crm_city_page_reviews'] as $tbl) {
            Schema::create($tbl, fn ($t) => tap($t, fn ($x) => [
                $x->id(), $x->unsignedBigInteger('page_id'), $x->ulid(str_contains($tbl, 'faq') ? 'faq_id' : 'review_id'),
                $x->unsignedTinyInteger('sort_order')->default(0), $x->timestamps(),
            ]));
        }
        Schema::create('crm_city_page_faq_categories', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('page_id'), $x->unsignedBigInteger('taxonomy_id'),
            $x->unsignedTinyInteger('sort_order')->default(0), $x->timestamps(),
        ]));
    }

    private function admin(): User
    {
        $u = User::forceCreate([
            'first_name' => 'مدیر', 'email' => 'cityedit@example.test',
            'password' => bcrypt('x'), 'mobile' => '09120000010', 'mobile_verified_at' => now(),
        ]);
        \Spatie\Permission\Models\Permission::findOrCreate('manage-crm-cities', 'web');
        $u->givePermissionTo('manage-crm-cities');

        return $u;
    }

    public function test_update_persists_rich_content_and_clears_auto_generated(): void
    {
        $city = City::withoutEvents(fn () => City::create(['name' => 'مشهد', 'slug' => 'mashhad']));
        $page = CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_CITY, 'path' => '/mashhad',
            'title' => 'قدیمی', 'status' => CityPage::STATUS_DRAFT, 'auto_generated' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('crm.city-pages.update', $page), [
                'path' => '/mashhad',
                'title' => 'تعمیرات لوازم خانگی در مشهد',
                'h1' => 'تعمیرکار در مشهد',
                'eyebrow' => 'سرویس تخصصی',
                'subtitle' => 'اعزام تا ۳ ساعت',
                'caption' => 'خدمات در مشهد',
                'content' => '<p>متن <b>بدنه</b></p>',
                'cta_primary_label' => 'ثبت سفارش',
                'cta_primary_url' => '/order',
                'steps_image_desktop' => 'https://cdn/x.jpg',
                'sections_enabled' => ['hero' => '1', 'faq' => '0'],
                'hero_image' => ['desktop_left' => ['url' => 'https://cdn/h.jpg', 'alt' => 'مشهد']],
            ])
            ->assertRedirect(route('crm.city-pages.edit', $page->id));

        $page->refresh();
        $this->assertSame('تعمیرات لوازم خانگی در مشهد', $page->title);
        $this->assertSame('سرویس تخصصی', $page->eyebrow);
        $this->assertSame('اعزام تا ۳ ساعت', $page->subtitle);
        $this->assertStringContainsString('بدنه', (string) $page->content);
        $this->assertSame('ثبت سفارش', $page->cta_primary_label);
        $this->assertSame('https://cdn/x.jpg', $page->steps_image_desktop);
        $this->assertTrue((bool) ($page->sections_enabled['hero'] ?? false));
        $this->assertFalse((bool) ($page->sections_enabled['faq'] ?? true));
        $this->assertSame('https://cdn/h.jpg', $page->hero_image['desktop_left']['url'] ?? null);
        // ویرایشِ دستی → دیگر خودکار نیست.
        $this->assertFalse((bool) $page->auto_generated);
    }

    public function test_admin_can_manually_change_the_page_slug(): void
    {
        $city = City::withoutEvents(fn () => City::create(['name' => 'مشهد', 'slug' => 'mashhad']));
        $page = CityPage::create([
            'city_id' => $city->id, 'type' => CityPage::TYPE_CITY, 'path' => '/mashhad',
            'title' => 'عنوان', 'status' => CityPage::STATUS_DRAFT,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->put(route('crm.city-pages.update', $page), [
                'path' => '/mashhad-landing', 'title' => 'عنوان',
            ])->assertRedirect();

        $this->assertSame('/mashhad-landing', $page->fresh()->path);

        // مسیرِ فارسی/نامعتبر رد می‌شود.
        $this->actingAs($admin)
            ->put(route('crm.city-pages.update', $page), [
                'path' => '/مشهد', 'title' => 'عنوان',
            ])->assertSessionHasErrors('path');
    }
}
