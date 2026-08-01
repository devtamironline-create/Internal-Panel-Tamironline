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
