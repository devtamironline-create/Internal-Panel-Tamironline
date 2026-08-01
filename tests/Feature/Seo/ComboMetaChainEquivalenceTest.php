<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * قفلِ رفتارِ زنجیرهٔ چهارسطحیِ متایِ صفحهٔ ترکیبی.
 *
 * این تست **پیش از** بیرون‌کشیدنِ زنجیره از کنترلر به `ComboMetaChain` نوشته و
 * سبز شد. اگر بعد از استخراج هم سبز بماند، یعنی جای منطق عوض شده و رفتار نه —
 * که کلِ ادعای «refactor بدونِ تغییرِ رفتار» است.
 *
 * هر سطح جدا آزموده می‌شود، با رشتهٔ دقیق و نه فقط «شامل بودن»: زنجیره‌ای که
 * سطحِ اشتباهی را برنده کند هم می‌تواند رشته‌ای «شبیه» درست بدهد.
 *
 * جدول‌ها از ComboMetaUniquenessTest به ارث می‌رسند (setUp همان‌جاست).
 */
class ComboMetaChainEquivalenceTest extends ComboMetaUniquenessTest
{
    public function test_level_1_per_pair_override_wins_over_everything(): void
    {
        $this->publishComboTemplate('عنوانِ الگویِ ترکیبی {device} {brand}', 'توضیحاتِ الگو.');
        $this->setPerPair('gaz', 'عنوانِ دستیِ جفت', 'توضیحاتِ دستیِ جفت.');

        $meta = $this->chain('gaz', 'zanussi');

        $this->assertSame('عنوانِ دستیِ جفت', $meta['meta_title']);
        $this->assertSame('توضیحاتِ دستیِ جفت.', $meta['meta_description']);
    }

    public function test_level_3_page_section_template_wins_when_no_override(): void
    {
        $this->publishComboTemplate('عنوانِ الگویِ ترکیبی {device} {brand}', 'توضیحاتِ الگو {device}.');

        $meta = $this->chain('gaz', 'zanussi');

        $this->assertSame('عنوانِ الگویِ ترکیبی اجاق گاز زانوسی', $meta['meta_title']);
        $this->assertSame('توضیحاتِ الگو اجاق گاز.', $meta['meta_description']);
    }

    public function test_level_4_seo_template_is_the_floor(): void
    {
        // هیچ سطحی پر نیست — قالبِ تأییدشدهٔ ماژول سئو باید جواب بدهد.
        $meta = $this->chain('gaz', 'zanussi');

        $this->assertSame('تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت', $meta['meta_title']);
        $this->assertSame(
            'تعمیر تخصصی اجاق گاز زانوسی در محل با تعمیرکار ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات',
            $meta['meta_description']
        );
    }

    public function test_an_empty_string_at_a_higher_level_falls_through(): void
    {
        // رشتهٔ خالی باید مثلِ null رفتار کند (CatalogMerger::pick)، وگرنه صفحه
        // با متایِ خالی سرو می‌شود.
        $this->setPerPair('gaz', '', '');

        $meta = $this->chain('gaz', 'zanussi');

        $this->assertSame('تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت', $meta['meta_title']);
    }

    public function test_the_length_cap_still_applies_to_a_manual_override(): void
    {
        $long = 'تعمیر '.str_repeat('ماشین لباسشویی الکترواستیل ', 4);
        $this->setPerPair('gaz', $long, null);

        $meta = $this->chain('gaz', 'zanussi');

        $this->assertLessThanOrEqual(60, mb_strlen($meta['meta_title']));
        $this->assertStringStartsWith('تعمیر ماشین لباسشویی', $meta['meta_title']);
    }

    /** @return array{meta_title: string, meta_description: string} */
    private function chain(string $device, string $brand): array
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyTrustedFrontend::class)
            ->getJson("/v1/catalog/devices/{$device}/{$brand}");

        $response->assertOk();

        return [
            'meta_title' => (string) $response->json('meta_title'),
            'meta_description' => (string) $response->json('meta_description'),
        ];
    }

    private function setPerPair(string $deviceSlug, ?string $title, ?string $description): void
    {
        $deviceId = Device::query()->where('slug', $deviceSlug)->value('id');
        $brandId = Brand::query()->where('slug', 'zanussi')->value('id');

        DeviceBrandPage::query()
            ->where('device_id', $deviceId)
            ->where('brand_id', $brandId)
            ->update(['meta_title' => $title, 'meta_description' => $description]);
    }

    private function publishComboTemplate(string $title, string $description): void
    {
        DB::table('site_page_sections')->insert([
            'page_slug' => 'device_brand',
            'section_key' => 'seo',
            'payload' => json_encode(['meta_title' => $title, 'meta_description' => $description], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
