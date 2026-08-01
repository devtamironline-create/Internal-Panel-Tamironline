<?php

namespace Tests\Feature\Seo;

use Illuminate\Http\Request;
use Modules\Seo\Http\Controllers\MetaAuditController;
use Modules\Seo\Services\MetaAuditBuilder;

/**
 * ویو با دادهٔ واقعیِ snapshot رندر می‌شود. خطاهای Blade — کلیدِ نبودهٔ آرایه،
 * فراخوانی روی null — فقط سرِ رندرِ واقعی خودشان را نشان می‌دهند و در تستِ
 * سرویس نامرئی‌اند.
 */
class MetaAuditViewTest extends MetaAuditToolTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // لایوتِ ادمین کاربرِ لاگین‌شده و جدول‌های مجوز می‌خواهد؛ با جایگزینِ
        // حداقلی، تست فقط همان ویویی را می‌سنجد که موضوعش است.
        view()->getFinder()->prependLocation(base_path('tests/stubs/views'));
    }

    private function render(array $query = []): string
    {
        app(MetaAuditBuilder::class)->rebuild();

        $request = Request::create('/admin/seo/meta-audit', 'GET', $query);
        $this->app['request'] = $request;

        return view('seo::meta-audit.index', app(MetaAuditController::class)->viewData($request))->render();
    }

    public function test_the_page_renders_with_real_snapshot_data(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('بازبینی تایتل و دیسکریپشن', $html);
        $this->assertStringContainsString('/services/gaz/zanussi', $html);
        $this->assertStringContainsString('تعمیر اجاق گاز زانوسی', $html);
        $this->assertStringContainsString('53/60', $html, 'شمارندهٔ طول نمایش داده نشد.');
        $this->assertStringContainsString('مطابق الگو', $html);
    }

    public function test_filtering_by_page_type_narrows_the_table(): void
    {
        $html = $this->render(['type' => 'brand']);

        $this->assertStringContainsString('/brands/zanussi', $html);
        $this->assertStringNotContainsString('/services/gaz/zanussi', $html);
    }

    public function test_searching_by_url_narrows_the_table(): void
    {
        $html = $this->render(['q' => 'range-hood']);

        $this->assertStringContainsString('/services/range-hood/zanussi', $html);
        $this->assertStringNotContainsString('/brands/zanussi', $html);
    }

    /**
     * اولین اجرای واقعی نشان داد اختلافِ دو کانال معمولاً در *دیسکریپشن* است نه
     * عنوان — و صفحه آن‌وقت دو عنوانِ کاملاً یکسان کنارِ هم چاپ می‌کرد. جعبهٔ
     * اختلاف باید فقط فیلدی را نشان دهد که واقعاً فرق دارد.
     */
    public function test_the_divergence_box_shows_the_field_that_actually_differs(): void
    {
        \Modules\CRM\Models\DeviceBrandPage::query()->update([
            'meta_description' => 'دیسکریپشنِ متفاوتِ کانالِ کاتالوگ.',
        ]);

        // فقط صفحاتِ ترکیبی: عنوانشان از هر دو کانال یکی است و تنها دیسکریپشن
        // فرق دارد. (صفحاتِ برند در همین فیکسچر متایِ آلودهٔ وردپرس دارند، پس
        // آن‌جا عنوان هم فرق می‌کند و تستِ این حالت را مبهم می‌کرد.)
        $html = $this->render(['type' => 'brand_device']);

        $this->assertStringContainsString('دیسکریپشن کانال کاتالوگ', $html);
        $this->assertStringContainsString('دیسکریپشنِ متفاوتِ کانالِ کاتالوگ.', $html);
        $this->assertStringNotContainsString('تایتل کانال کاتالوگ', $html);
    }

    /**
     * قرینه‌اش: صفحهٔ برندی که هنوز متایِ واردشده از وردپرس دارد، در کانالِ
     * کاتالوگ همان را سرو می‌کند در حالی که کانالِ سئو قالبِ تأییدشده را می‌دهد.
     * تا پیش از این، ابزار برای صفحاتِ مستقل اصلاً کانالِ کاتالوگ را نمی‌خواند و
     * این اختلاف نامرئی بود.
     */
    public function test_a_standalone_brand_page_reveals_its_catalog_channel_title(): void
    {
        $html = $this->render(['type' => 'brand']);

        $this->assertStringContainsString('تایتل کانال کاتالوگ', $html);
        $this->assertStringContainsString('نمایندگی زانوسی', $html);
    }

    public function test_the_page_offers_no_way_to_edit_content(): void
    {
        $html = $this->render();

        // تنها فرمِ POSTِ صفحه باید «بروزرسانی»ِ snapshot باشد. اگر روزی کسی
        // فرمِ ویرایش اضافه کند، این ادعای «فقط نمایش» همین‌جا می‌شکند.
        $this->assertSame(1, substr_count($html, 'method="POST"'), 'صفحه بیش از یک فرمِ POST دارد.');
        $this->assertStringContainsString('meta-audit/refresh', $html);
        $this->assertStringNotContainsString('name="meta_title"', $html);
        $this->assertStringNotContainsString('name="meta_description"', $html);
    }
}
