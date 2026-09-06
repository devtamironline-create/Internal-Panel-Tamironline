<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Tests\TestCase;

/**
 * crm:publish-combo — انتشارِ صفحهٔ ترکیبیِ دستگاه×برند:
 *   • رکورد را می‌سازد/فعال می‌کند،
 *   • محتوای پیش‌فرض فقط وقتی خالی است ست می‌شود (کارِ ادمین بازنویسی نمی‌شود).
 */
class PublishComboTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ذخیرهٔ DeviceBrandPage کشِ کاتالوگِ اپ را bump می‌کند (settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_device_brand_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('device_id'), $x->unsignedBigInteger('brand_id'),
            $x->string('title')->nullable(), $x->text('description')->nullable(),
            $x->string('meta_title')->nullable(), $x->text('meta_description')->nullable(),
            $x->boolean('is_active')->default(false), $x->timestamps(),
        ]));
    }

    public function test_publishes_and_seeds_default_content(): void
    {
        $d = Device::create(['name' => 'جاروبرقی', 'slug' => 'vacuum-cleaner']);
        $b = Brand::create(['name' => 'فیلیپس', 'slug' => 'philips']);

        Artisan::call('crm:publish-combo', ['device' => 'vacuum-cleaner', 'brand' => 'philips']);

        $page = DeviceBrandPage::where('device_id', $d->id)->where('brand_id', $b->id)->first();
        $this->assertNotNull($page);
        $this->assertTrue((bool) $page->is_active);
        $this->assertStringContainsString('جاروبرقی', $page->title);
        $this->assertStringContainsString('فیلیپس', $page->title);
        $this->assertNotEmpty($page->meta_title);
        $this->assertNotEmpty($page->description);
    }

    public function test_does_not_overwrite_existing_content(): void
    {
        $d = Device::create(['name' => 'پکیج دیواری', 'slug' => 'wall-mounted-boiler']);
        $b = Brand::create(['name' => 'لورچ', 'slug' => 'lorch']);
        DeviceBrandPage::create([
            'device_id' => $d->id, 'brand_id' => $b->id,
            'title' => 'عنوانِ دستیِ ادمین', 'is_active' => false,
        ]);

        Artisan::call('crm:publish-combo', ['device' => 'wall-mounted-boiler', 'brand' => 'lorch']);

        $page = DeviceBrandPage::where('device_id', $d->id)->where('brand_id', $b->id)->first();
        $this->assertTrue((bool) $page->is_active);            // فعال شد
        $this->assertSame('عنوانِ دستیِ ادمین', $page->title); // ولی عنوان دست‌نخورد
    }

    public function test_fails_on_unknown_slug(): void
    {
        $code = Artisan::call('crm:publish-combo', ['device' => 'nope', 'brand' => 'philips']);
        $this->assertSame(1, $code); // FAILURE
    }
}
