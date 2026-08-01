<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Schema;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\VariableRenderer;
use Modules\Seo\Support\MetaLength;
use Tests\TestCase;

/**
 * قالب‌های تأییدشده باید سه چیز را همزمان داشته باشند:
 *   ۱) متغیرِ لازم را (بدونِ %device% صفحاتِ یک برند دوباره هم‌عنوان می‌شوند)،
 *   ۲) در `seo_settings` نشسته باشند — چون تنظیمِ پنل بر config مقدم است و
 *      تغییرِ فایلِ config به‌تنهایی روی سایتِ زنده هیچ اثری ندارد،
 *   ۳) با نام‌های واقعی زیرِ بودجهٔ ۶۰/۱۵۵ بمانند.
 */
class MetaTemplateBudgetTest extends TestCase
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

        // خودِ مهاجرتِ فاز ۱ اجرا می‌شود تا مقدارهایی که روی پروداکشن می‌نشینند
        // آزموده شوند، نه یک کپیِ دستی از آن‌ها.
        (require base_path('Modules/Seo/Database/Migrations/2026_08_01_120000_update_meta_templates_to_approved_copy.php'))->up();
    }

    public function test_the_migration_writes_all_three_page_types(): void
    {
        foreach ([
            'tpl_device_title', 'tpl_device_description',
            'tpl_brand_title', 'tpl_brand_description',
            'tpl_brand_device_title', 'tpl_brand_device_description',
            'tpl_device_brand_page_title', 'tpl_device_brand_page_description',
        ] as $key) {
            $this->assertNotEmpty(SeoSetting::get($key), "قالبِ {$key} نوشته نشده.");
        }
    }

    public function test_the_combo_template_carries_both_variables(): void
    {
        // اگر روزی یکی از این دو از قالب حذف شود، همان اشکالِ SEMrush برمی‌گردد.
        foreach (['tpl_brand_device_title', 'tpl_brand_device_description'] as $key) {
            $value = (string) SeoSetting::get($key);

            $this->assertStringContainsString('%device%', $value, "{$key} نامِ دستگاه را ندارد.");
            $this->assertStringContainsString('%brand%', $value, "{$key} نامِ برند را ندارد.");
        }
    }

    public function test_stored_templates_match_the_config_defaults(): void
    {
        // دو منبع نباید از هم جدا بیفتند؛ اگر کسی یکی را عوض کند و دیگری را نه،
        // رفتار به «کدام‌یک زودتر خوانده می‌شود» وابسته می‌شود.
        $this->assertSame(
            config('seo.templates.brand_device.title'),
            SeoSetting::get('tpl_brand_device_title')
        );
        $this->assertSame(
            config('seo.templates.device.title'),
            SeoSetting::get('tpl_device_title')
        );
        $this->assertSame(
            config('seo.templates.brand.title'),
            SeoSetting::get('tpl_brand_title')
        );
    }

    /**
     * بلندترین نام‌های واقعیِ کاتالوگ — همان‌هایی که بودجه را می‌سنجند.
     */
    public function test_every_page_type_fits_the_budget_with_real_names(): void
    {
        $renderer = new VariableRenderer;
        $devices = ['اجاق گاز', 'هود', 'ماشین لباسشویی', 'ظرفشویی', 'یخچال و فریزر'];
        $brands = ['زانوسی', 'سامسونگ', 'الکترواستیل', 'ال جی'];

        $check = function (string $key, array $vars, int $max, string $label) use ($renderer) {
            $rendered = $renderer->render((string) SeoSetting::get($key), $vars);
            $final = $max === MetaLength::TITLE_MAX
                ? MetaLength::title($rendered)
                : MetaLength::description($rendered);

            $this->assertNotSame('', $final, "{$label} خالی رندر شد.");
            $this->assertLessThanOrEqual($max, mb_strlen($final), "{$label} از {$max} رد شد: {$final}");

            return $final;
        };

        foreach ($devices as $device) {
            $check('tpl_device_title', ['title' => $device], MetaLength::TITLE_MAX, "دستگاه/{$device}");
            $check('tpl_device_description', ['title' => $device], MetaLength::DESCRIPTION_MAX, "دستگاه/{$device}");

            foreach ($brands as $brand) {
                $vars = ['device' => $device, 'brand' => $brand];
                $title = $check('tpl_brand_device_title', $vars, MetaLength::TITLE_MAX, "ترکیبی/{$device} {$brand}");
                $check('tpl_brand_device_description', $vars, MetaLength::DESCRIPTION_MAX, "ترکیبی/{$device} {$brand}");

                // کوتاه‌سازی هرگز نباید نامِ دستگاه یا برند را قربانی کند.
                $this->assertStringContainsString($device, $title, "نامِ دستگاه از عنوان حذف شد: {$title}");
                $this->assertStringContainsString($brand, $title, "نامِ برند از عنوان حذف شد: {$title}");
            }
        }

        foreach ($brands as $brand) {
            $check('tpl_brand_title', ['title' => $brand], MetaLength::TITLE_MAX, "برند/{$brand}");
            $check('tpl_brand_description', ['title' => $brand], MetaLength::DESCRIPTION_MAX, "برند/{$brand}");
        }
    }

    public function test_two_devices_of_one_brand_render_different_titles(): void
    {
        $renderer = new VariableRenderer;
        $tpl = (string) SeoSetting::get('tpl_brand_device_title');

        $gaz = MetaLength::title($renderer->render($tpl, ['device' => 'اجاق گاز', 'brand' => 'زانوسی']));
        $hood = MetaLength::title($renderer->render($tpl, ['device' => 'هود', 'brand' => 'زانوسی']));

        $this->assertNotSame($gaz, $hood);
    }
}
