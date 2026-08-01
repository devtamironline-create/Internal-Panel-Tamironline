<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Models\SeoMetaAuditRow;
use Modules\Seo\Services\MetaAuditBuilder;

/**
 * ابزارِ بازبینی. مهم‌ترین ادعایش «فقط نمایش» است، پس همان اول آزموده می‌شود:
 * بعد از یک بازسازیِ کامل، ستون‌های محتوایی باید بیت‌به‌بیت دست‌نخورده بمانند.
 */
class MetaAuditToolTest extends ComboMetaUniquenessTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_meta_audit_rows', function ($t) {
            $t->id();
            $t->string('page_type', 20);
            $t->string('url', 700);
            $t->unsignedBigInteger('entity_id');
            $t->unsignedBigInteger('secondary_id')->nullable();
            $t->string('label', 255)->nullable();
            $t->text('live_title')->nullable();
            $t->unsignedSmallInteger('live_title_len')->default(0);
            $t->text('live_description')->nullable();
            $t->unsignedSmallInteger('live_description_len')->default(0);
            $t->text('catalog_title')->nullable();
            $t->text('catalog_description')->nullable();
            $t->boolean('channels_diverge')->default(false);
            $t->char('title_hash', 32)->nullable();
            $t->char('description_hash', 32)->nullable();
            $t->text('crawled_title')->nullable();
            $t->text('crawled_description')->nullable();
            $t->timestamp('crawled_at')->nullable();
            $t->json('flags')->nullable();
            $t->string('verdict', 20)->nullable();
            $t->boolean('matches_template')->default(false);
            $t->boolean('url_pattern_conflict')->default(false);
            $t->timestamp('generated_at')->nullable();
        });

        Schema::create('seo_audits', function ($t) {
            $t->id();
            $t->string('url', 700);
            $t->string('title', 512)->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamp('crawled_at')->nullable();
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
    }

    private function build(): array
    {
        return app(MetaAuditBuilder::class)->rebuild();
    }

    public function test_the_rebuild_touches_no_content_column(): void
    {
        $before = [
            'brands' => DB::table('crm_brands')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson(),
            'devices' => DB::table('crm_devices')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson(),
            'pages' => DB::table('crm_device_brand_pages')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson(),
        ];

        $this->build();

        $this->assertSame($before['brands'], DB::table('crm_brands')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson());
        $this->assertSame($before['devices'], DB::table('crm_devices')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson());
        $this->assertSame($before['pages'], DB::table('crm_device_brand_pages')->orderBy('id')->get(['id', 'meta_title', 'meta_description'])->toJson());
    }

    public function test_it_covers_all_three_page_types_with_the_right_urls(): void
    {
        $this->build();

        $combo = SeoMetaAuditRow::where('page_type', 'brand_device')->where('url', '/services/gaz/zanussi')->first();
        $this->assertNotNull($combo, 'صفحهٔ ترکیبی در snapshot نیست.');
        $this->assertSame('تعمیر اجاق گاز زانوسی | تعمیرات ۳ ساعته و ۶ ماه ضمانت', $combo->live_title);
        $this->assertSame(53, $combo->live_title_len);
        $this->assertTrue($combo->matches_template);

        $device = SeoMetaAuditRow::where('page_type', 'device')->where('url', '/services/gaz')->first();
        $this->assertNotNull($device, 'صفحهٔ دستگاه در snapshot نیست.');
        $this->assertSame('تعمیر اجاق گاز | تعمیرکار ۵ ستاره، اعزام تا ۳ ساعت', $device->live_title);

        $brand = SeoMetaAuditRow::where('page_type', 'brand')->where('url', '/brands/zanussi')->first();
        $this->assertNotNull($brand, 'صفحهٔ برند در snapshot نیست.');
        $this->assertSame('تعمیرات زانوسی در محل | اعزام ۳ ساعته، ضمانت ۱۸۰ روزه', $brand->live_title);
    }

    public function test_lengths_are_counted_in_characters_not_bytes(): void
    {
        $this->build();

        $row = SeoMetaAuditRow::where('url', '/services/gaz/zanussi')->first();

        // بایت‌شمار برای این رشتهٔ فارسی حدوداً دوبرابر است؛ اگر جایی strlen
        // مانده باشد این تست می‌شکند و همه‌چیز اشتباهاً «بلند» علامت می‌خورد.
        $this->assertSame(mb_strlen($row->live_title), $row->live_title_len);
        $this->assertGreaterThan($row->live_title_len, strlen($row->live_title));
    }

    public function test_duplicate_titles_are_grouped_not_just_labelled(): void
    {
        // هم‌عنوان کردن از راهِ پنلِ سئو (`seo_meta`) — همان مسیری که مقدارِ زنده
        // را عوض می‌کند.
        foreach (DeviceBrandPage::all() as $page) {
            DB::table('seo_meta')->insert([
                'seoable_type' => DeviceBrandPage::class,
                'seoable_id' => $page->id,
                'title' => 'یک عنوانِ کاملاً یکسان',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $stats = $this->build();

        $this->assertGreaterThanOrEqual(2, $stats['title_duplicate']);

        $rows = SeoMetaAuditRow::where('live_title', 'یک عنوانِ کاملاً یکسان')->get();
        $this->assertGreaterThanOrEqual(2, $rows->count());
        foreach ($rows as $row) {
            $this->assertContains('title_duplicate', $row->flags);
            $this->assertSame($rows->first()->title_hash, $row->title_hash, 'هش‌ها باید یکی باشند تا گروه‌بندی کار کند.');
        }
    }

    /**
     * یافته‌ای که همین تست رو کرد: `crm_device_brand_pages.meta_title` فقط
     * کانالِ کاتالوگ را عوض می‌کند و کانالِ سئو (`/v1/seo/meta`) آن را نمی‌بیند.
     * یعنی همان صفحه از دو مسیر دو عنوانِ متفاوت می‌گیرد — دقیقاً چیزی که
     * ستونِ `channels_diverge` برای دیدنش ساخته شد.
     */
    public function test_a_per_pair_override_shows_up_as_a_channel_divergence(): void
    {
        DeviceBrandPage::query()->update(['meta_title' => 'عنوانِ دستیِ فقط-کاتالوگ']);

        $this->build();

        $row = SeoMetaAuditRow::where('url', '/services/gaz/zanussi')->first();

        $this->assertTrue($row->channels_diverge, 'اختلافِ دو کانال تشخیص داده نشد.');
        $this->assertSame('عنوانِ دستیِ فقط-کاتالوگ', $row->catalog_title);
        $this->assertNotSame($row->catalog_title, $row->live_title);
        $this->assertContains('channels_diverge', $row->flags);
    }

    public function test_a_stale_broken_crawl_against_a_healthy_live_value_reads_as_fixed(): void
    {
        DB::table('seo_audits')->insert([
            'url' => 'https://tamironline.com/services/gaz/zanussi',
            'title' => 'نمایندگی زانوسی zanussi در ایران | مرکز تخصصی تعمیرات زانوسی – تعمیرآنلاین',
            'meta_description' => '',
            'crawled_at' => now()->subDays(3),
        ]);

        $this->build();

        $row = SeoMetaAuditRow::where('url', '/services/gaz/zanussi')->first();

        $this->assertSame('fixed', $row->verdict);
        $this->assertStringContainsString('نمایندگی', (string) $row->crawled_title);
        // «قبل» فقط گزارش می‌شود؛ نباید در مقدار زنده نشت کند.
        $this->assertStringNotContainsString('نمایندگی', (string) $row->live_title);
    }

    public function test_a_page_never_crawled_reads_as_awaiting(): void
    {
        $this->build();

        $this->assertSame('awaiting_crawl', SeoMetaAuditRow::where('url', '/services/gaz/zanussi')->value('verdict'));
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get('/admin/seo/meta-audit')->assertRedirect();
    }

    public function test_device_rows_carry_the_url_pattern_conflict_flag(): void
    {
        $this->build();

        $this->assertTrue((bool) SeoMetaAuditRow::where('page_type', 'device')->value('url_pattern_conflict'));
        $this->assertFalse((bool) SeoMetaAuditRow::where('page_type', 'brand')->value('url_pattern_conflict'));
    }

    public function test_a_second_rebuild_is_idempotent(): void
    {
        $first = $this->build();
        $second = $this->build();

        $this->assertSame($first['total'], $second['total']);
        $this->assertSame($first['total'], SeoMetaAuditRow::count(), 'بازسازی نباید ردیف تکراری بگذارد.');
    }

    public function test_devices_and_brands_come_from_active_rows_only(): void
    {
        Device::forceCreate(['name' => 'غیرفعال', 'slug' => 'inactive-device', 'is_active' => false]);
        Brand::forceCreate(['name' => 'برند غیرفعال', 'slug' => 'inactive-brand', 'is_active' => false]);

        $this->build();

        $this->assertNull(SeoMetaAuditRow::where('url', '/services/inactive-device')->first());
        $this->assertNull(SeoMetaAuditRow::where('url', '/brands/inactive-brand')->first());
    }
}
