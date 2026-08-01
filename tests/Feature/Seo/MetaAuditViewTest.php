<?php

namespace Tests\Feature\Seo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * صفحه باید فقط فیلدی را نشان دهد که واقعاً فرق دارد — اولین اجرای واقعی
     * دو عنوانِ کاملاً یکسان کنارِ هم چاپ می‌کرد چون اختلاف در دیسکریپشن بود.
     *
     * تنها اختلافِ واقعیِ باقی‌مانده: مقداری که در «مدیریت محتوای صفحه» نوشته
     * شده. آن ردیف را فقط کانالِ کاتالوگ می‌خواند و کانالِ سئو از وجودش خبر
     * ندارد — پس همان صفحه از دو مسیر دو دیسکریپشن می‌دهد.
     */
    public function test_the_divergence_box_shows_the_field_that_actually_differs(): void
    {
        DB::table('site_page_sections')->insert([
            'page_slug' => 'device_brand',
            'section_key' => 'seo',
            'payload' => json_encode(['meta_description' => 'دیسکریپشنِ فقط-کاتالوگ.'], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->render(['type' => 'brand_device']);

        $this->assertStringContainsString('دیسکریپشن کانال کاتالوگ', $html);
        $this->assertStringContainsString('دیسکریپشنِ فقط-کاتالوگ.', $html);
        // عنوان‌ها یکی‌اند، پس جعبهٔ عنوان نباید بیاید.
        $this->assertStringNotContainsString('تایتل کانال کاتالوگ', $html);
    }

    /**
     * صفحهٔ برندی که متای دستی دارد، دیگر «اختلافِ دو کانال» نمی‌گیرد — هر دو
     * کانال همان نوشتهٔ ادمین را می‌دهند. پیش از این، ابزار این‌ها را «نیاز به
     * بررسی» علامت می‌زد در حالی که هیچ مشکلی نبود.
     */
    public function test_a_standalone_brand_page_no_longer_diverges(): void
    {
        $html = $this->render(['type' => 'brand']);

        $this->assertStringContainsString('نمایندگی زانوسی', $html);
        $this->assertStringNotContainsString('تایتل کانال کاتالوگ', $html);
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
