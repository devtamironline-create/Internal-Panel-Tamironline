<?php

namespace Tests\Feature\Seo;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

/**
 * پیش‌شرطِ پاک‌سازیِ فاز ۲.
 *
 * پاک‌سازی، `crm_devices.meta_title` و `crm_brands.meta_title` را خالی می‌کند.
 * ردیفِ محتواییِ `site_page_sections` برای این دو نوع صفحه هم خالی است، پس
 * بدونِ کفِ قالب، صفحه *بی‌عنوان* سرو می‌شد — یعنی پاک‌سازی به‌جای اصلاح،
 * تخریب می‌کرد. این تست همان کف را قفل می‌کند.
 */
class StandalonePageMetaFloorTest extends ComboMetaUniquenessTest
{
    public function test_a_device_page_falls_back_to_the_approved_template(): void
    {
        Device::query()->where('slug', 'gaz')->update(['meta_title' => null, 'meta_description' => null]);

        $body = $this->withoutMiddleware(\App\Http\Middleware\VerifyTrustedFrontend::class)
            ->getJson('/v1/catalog/devices/gaz')->assertOk()->json();

        $this->assertSame('تعمیر اجاق گاز | تعمیرکار ۵ ستاره، اعزام تا ۳ ساعت', $body['meta_title']);
        $this->assertNotEmpty($body['meta_description']);
    }

    public function test_a_brand_page_falls_back_to_the_approved_template(): void
    {
        Brand::query()->where('slug', 'zanussi')->update(['meta_title' => null, 'meta_description' => null]);

        $body = $this->withoutMiddleware(\App\Http\Middleware\VerifyTrustedFrontend::class)
            ->getJson('/v1/catalog/brands/zanussi')->assertOk()->json();

        $this->assertSame('تعمیرات زانوسی در محل | اعزام ۳ ساعته، ضمانت ۱۸۰ روزه', $body['meta_title']);
        $this->assertNotEmpty($body['meta_description']);
    }

    public function test_an_existing_value_still_wins_over_the_template(): void
    {
        Device::query()->where('slug', 'gaz')->update(['meta_title' => 'عنوانِ دستیِ دستگاه']);

        $body = $this->withoutMiddleware(\App\Http\Middleware\VerifyTrustedFrontend::class)
            ->getJson('/v1/catalog/devices/gaz')->assertOk()->json();

        $this->assertSame('عنوانِ دستیِ دستگاه', $body['meta_title']);
    }
}
