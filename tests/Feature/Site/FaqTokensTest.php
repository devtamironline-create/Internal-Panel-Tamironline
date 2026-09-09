<?php

namespace Tests\Feature\Site;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Services\ServiceCoverage;
use Modules\Site\Support\FaqTokens;
use Tests\TestCase;

/**
 * توکن‌های داینامیکِ FAQ — به‌ویژه {cities}/{provinces}/{city_count} از روی
 * پوششِ واقعیِ خدمت. ServiceCoverage با یک fake در کانتینر جایگزین می‌شود.
 */
class FaqTokensTest extends TestCase
{
    private function fakeCoverage(): void
    {
        $fake = new class
        {
            public function forDevice($id)
            {
                return ['provinces' => [
                    ['name' => 'تهران', 'cities' => [
                        ['name' => 'تهران', 'site_visible' => true, 'brands' => 'all'],
                        ['name' => 'کرج', 'site_visible' => true, 'brands' => ['samsung']],
                    ]],
                ]];
            }

            public function siteTable()
            {
                return ['services' => [
                    ['provinces' => [
                        ['name' => 'تهران', 'cities' => [['name' => 'تهران', 'site_visible' => true]]],
                        ['name' => 'خراسان رضوی', 'cities' => [['name' => 'مشهد', 'site_visible' => true]]],
                    ]],
                ]];
            }
        };
        app()->instance(ServiceCoverage::class, $fake);
    }

    private function device(): Device
    {
        $d = new Device;
        $d->id = 1;
        $d->name = 'لباسشویی';
        $d->short_name = 'لباسشویی';
        $d->slug = 'washing-machine';
        $d->service_name = 'تعمیر لباسشویی';

        return $d;
    }

    public function test_resolves_city_and_device_tokens_with_device_context(): void
    {
        $this->fakeCoverage();

        $faq = ['items' => [
            ['id' => 1, 'question' => 'آیا {device} در شهر من تعمیر می‌شود؟', 'answer' => 'بله، در {cities} خدمت می‌دهیم ({city_count} شهر).'],
        ]];

        $out = FaqTokens::apply($faq, $this->device());

        $this->assertSame('آیا لباسشویی در شهر من تعمیر می‌شود؟', $out['items'][0]['question']);
        // بدونِ برند، فیلترِ برند اعمال نمی‌شود → هر دو شهر.
        $this->assertStringContainsString('تهران', $out['items'][0]['answer']);
        $this->assertStringContainsString('کرج', $out['items'][0]['answer']);
        $this->assertStringContainsString('۲ شهر', $out['items'][0]['answer']);
    }

    public function test_brand_filters_cities_in_combo_context(): void
    {
        $this->fakeCoverage();
        $brand = new Brand;
        $brand->id = 5;
        $brand->name = 'سامسونگ';
        $brand->slug = 'samsung';

        $faq = ['items' => [['id' => 1, 'question' => 'q', 'answer' => 'شهرها: {cities}']]];
        $out = FaqTokens::apply($faq, $this->device(), $brand);

        // کرج فقط برای samsung مجاز است؛ تهران all → هر دو برای این برند می‌مانند.
        $this->assertStringContainsString('کرج', $out['items'][0]['answer']);
    }

    public function test_resolves_global_cities_without_device(): void
    {
        $this->fakeCoverage();

        $faq = ['categories' => [[
            'id' => 1, 'name' => 'عمومی', 'items' => [
                ['id' => 9, 'question' => 'q', 'answer' => 'پوشش در {provinces}؛ شهرها: {cities}'],
            ],
        ]]];

        $out = FaqTokens::apply($faq, null);
        $ans = $out['categories'][0]['items'][0]['answer'];

        $this->assertStringContainsString('تهران', $ans);
        $this->assertStringContainsString('مشهد', $ans);
        $this->assertStringContainsString('خراسان رضوی', $ans);
    }

    public function test_unknown_tokens_and_token_free_text_are_untouched(): void
    {
        $this->fakeCoverage();

        $faq = ['items' => [
            ['id' => 1, 'question' => 'بدون توکن', 'answer' => 'قیمت {unknown_token} ثابت است'],
        ]];

        $out = FaqTokens::apply($faq, $this->device());

        $this->assertSame('بدون توکن', $out['items'][0]['question']);
        $this->assertSame('قیمت {unknown_token} ثابت است', $out['items'][0]['answer']);
    }
}
