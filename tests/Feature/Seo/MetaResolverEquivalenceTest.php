<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Services\MetaResolver;
use Modules\Seo\Support\BrandDeviceCombo;
use Tests\TestCase;

/**
 * `titleAndDescription()` مسیرِ سبکِ ابزارِ بازبینی است و `resolve()` مسیرِ زندهٔ
 * سایت. اگر این دو از هم جدا بیفتند، ابزار عددی را گزارش می‌کند که هیچ‌کس
 * نمی‌بیند — و هیچ‌کس هم متوجه نمی‌شود، چون هر دو «معقول» به نظر می‌رسند.
 *
 * پس هم‌ارزیِ آن دو در همین‌جا قفل می‌شود، روی هر سه نوعِ صفحه و روی حالتِ
 * override دستی.
 */
class MetaResolverEquivalenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general');
            $t->timestamps();
        });

        Schema::create('seo_meta', function ($t) {
            $t->id();
            $t->string('seoable_type');
            $t->unsignedBigInteger('seoable_id');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('crm_brands', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });
    }

    private function resolver(): MetaResolver
    {
        return app(MetaResolver::class);
    }

    /** @return array{0: string, 1: string} */
    private function bothPaths(string $type, $model): array
    {
        $light = $this->resolver()->titleAndDescription($type, $model);
        $full = $this->resolver()->resolve($type, $model);

        $this->assertSame($full['title'], $light['title'], "عنوانِ دو مسیر برای «{$type}» یکی نیست.");
        $this->assertSame($full['description'], $light['description'], "توضیحاتِ دو مسیر برای «{$type}» یکی نیست.");

        return [$light['title'], $light['description']];
    }

    public function test_device_page_matches_on_both_paths(): void
    {
        $device = Device::forceCreate(['name' => 'ماشین لباسشویی', 'slug' => 'washing-machine']);

        [$title] = $this->bothPaths('device', $device);

        $this->assertSame('تعمیر ماشین لباسشویی | تعمیرکار ۵ ستاره، اعزام تا ۳ ساعت', $title);
    }

    public function test_brand_page_matches_on_both_paths(): void
    {
        $brand = Brand::forceCreate(['name' => 'الکترواستیل', 'slug' => 'electrosteel']);

        [$title] = $this->bothPaths('brand', $brand);

        $this->assertSame('تعمیرات الکترواستیل در محل | اعزام ۳ ساعته، ضمانت ۱۸۰ روزه', $title);
    }

    public function test_combo_page_matches_on_both_paths(): void
    {
        $combo = $this->combo('اجاق گاز', 'gaz', 'زانوسی', 'zanussi');

        [$title, $description] = $this->bothPaths('brand_device', $combo);

        $this->assertSame('تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت', $title);
        $this->assertSame(
            'تعمیر تخصصی اجاق گاز زانوسی در محل با تعمیرکار ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات',
            $description
        );
    }

    public function test_a_manual_seo_meta_override_is_honoured_on_both_paths(): void
    {
        $device = Device::forceCreate(['name' => 'یخچال', 'slug' => 'fridge']);
        SeoMeta::forceCreate([
            'seoable_type' => Device::class,
            'seoable_id' => $device->id,
            'title' => 'عنوانِ دستیِ ادمین',
            'description' => 'توضیحاتِ دستیِ ادمین.',
        ]);

        [$title, $description] = $this->bothPaths('device', $device->fresh());

        $this->assertSame('عنوانِ دستیِ ادمین', $title);
        $this->assertSame('توضیحاتِ دستیِ ادمین.', $description);
    }

    public function test_the_length_cap_is_applied_identically_on_both_paths(): void
    {
        // بلندترین ترکیبِ واقعیِ کاتالوگ — تنها موردی که از ۶۰ رد می‌شود.
        $combo = $this->combo('ماشین لباسشویی', 'washing-machine', 'الکترواستیل', 'electrosteel');

        [$title] = $this->bothPaths('brand_device', $combo);

        $this->assertLessThanOrEqual(60, mb_strlen($title));
        $this->assertStringContainsString('ماشین لباسشویی', $title);
        $this->assertStringContainsString('الکترواستیل', $title);
    }

    private function combo(string $deviceName, string $deviceSlug, string $brandName, string $brandSlug): BrandDeviceCombo
    {
        $device = Device::forceCreate(['name' => $deviceName, 'slug' => $deviceSlug]);
        $brand = Brand::forceCreate(['name' => $brandName, 'slug' => $brandSlug]);

        $combo = new BrandDeviceCombo([
            'slug' => $deviceSlug.'/'.$brandSlug,
            'device' => $deviceSlug,
            'brand' => $brandSlug,
            'device_name' => $device->name,
            'brand_name' => $brand->name,
            'name' => trim($device->name.' '.$brand->name),
            'is_active' => true,
        ]);
        $combo->deviceModel = $device;
        $combo->brandModel = $brand;
        $combo->setRelation('seoMeta', null);

        return $combo;
    }
}
