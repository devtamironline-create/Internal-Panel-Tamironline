<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Support\MetaLength;
use Tests\TestCase;

/**
 * «چرا دو صفحهٔ متفاوت عنوانِ یکسان دارند؟»
 *
 * ‏SEMrush گزارش داد `services/gaz/zanussi` و `services/range-hood/zanussi`
 * تایتل و دیسکریپشنِ کاملاً یکسان دارند. علت: زنجیرهٔ متایِ صفحهٔ ترکیبی به
 * `brand->meta_title` می‌رسید — مقداری که برای همهٔ دستگاه‌های آن برند یکی است
 * و اصلاً نامِ دستگاه در آن نیست.
 *
 * این تست همان دو صفحهٔ واقعی را روی endpointِ واقعی می‌سنجد؛ اگر کسی روزی آن
 * دو منبع را به زنجیره برگرداند، همین‌جا می‌شکند.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migrationهای CRM/Site
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class ComboMetaUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        // برندی که متایِ آلودهٔ واردشده از وردپرس را دارد: نامِ برند دو بار،
        // هیچ نامی از دستگاه، و بلندتر از ۶۰ کاراکتر.
        $this->brand = Brand::forceCreate([
            'name' => 'زانوسی',
            'slug' => 'zanussi',
            'is_active' => true,
            'meta_title' => 'نمایندگی زانوسی zanussi در ایران | مرکز تخصصی تعمیرات زانوسی – تعمیرآنلاین',
            'meta_description' => 'نمایندگی زانوسی در ایران؛ مرکز تخصصی تعمیرات زانوسی با گارانتی معتبر.',
        ]);

        $this->gaz = $this->makeDevice('اجاق گاز', 'gaz');
        $this->hood = $this->makeDevice('هود', 'range-hood');
    }

    private Brand $brand;

    private Device $gaz;

    private Device $hood;

    public function test_two_devices_of_the_same_brand_no_longer_share_a_title(): void
    {
        $gaz = $this->meta('gaz', 'zanussi');
        $hood = $this->meta('range-hood', 'zanussi');

        $this->assertNotSame($gaz['meta_title'], $hood['meta_title'], 'عنوانِ دو صفحه هنوز یکسان است.');
        $this->assertNotSame($gaz['meta_description'], $hood['meta_description'], 'توضیحاتِ دو صفحه هنوز یکسان است.');

        // و هر کدام باید نامِ دستگاهِ خودش را داشته باشد — همان چیزی که غایب بود.
        $this->assertStringContainsString('اجاق گاز', $gaz['meta_title']);
        $this->assertStringContainsString('هود', $hood['meta_title']);
        $this->assertStringContainsString('زانوسی', $gaz['meta_title']);
        $this->assertStringContainsString('زانوسی', $hood['meta_title']);
    }

    public function test_the_polluted_brand_value_never_reaches_the_combo_page(): void
    {
        foreach ([['gaz', 'zanussi'], ['range-hood', 'zanussi']] as [$device, $brand]) {
            $meta = $this->meta($device, $brand);

            $this->assertStringNotContainsString('نمایندگی', $meta['meta_title']);
            $this->assertStringNotContainsString('zanussi', $meta['meta_title']);
        }
    }

    public function test_titles_and_descriptions_respect_the_length_budget(): void
    {
        // بلندترین ترکیبِ واقعی — همانی که قالب را چند کاراکتر رد می‌کند.
        $long = $this->makeDevice('ماشین لباسشویی', 'washing-machine');
        $steel = Brand::forceCreate(['name' => 'الکترواستیل', 'slug' => 'electrosteel', 'is_active' => true]);
        DeviceBrandPage::forceCreate([
            'device_id' => $long->id, 'brand_id' => $steel->id, 'is_active' => true,
        ]);

        foreach ([['gaz', 'zanussi'], ['range-hood', 'zanussi'], ['washing-machine', 'electrosteel']] as [$d, $b]) {
            $meta = $this->meta($d, $b);

            $this->assertLessThanOrEqual(MetaLength::TITLE_MAX, mb_strlen($meta['meta_title']),
                "عنوانِ {$d}/{$b} از سقف رد شده: ".$meta['meta_title']);
            $this->assertLessThanOrEqual(MetaLength::DESCRIPTION_MAX, mb_strlen($meta['meta_description']),
                "توضیحاتِ {$d}/{$b} از سقف رد شده.");
        }
    }

    public function test_a_manual_per_pair_override_still_wins(): void
    {
        DeviceBrandPage::query()
            ->where('device_id', $this->gaz->id)
            ->where('brand_id', $this->brand->id)
            ->update([
                'meta_title' => 'عنوانِ دستیِ اپراتور برای اجاق گاز زانوسی',
                'meta_description' => 'توضیحاتِ دستیِ اپراتور.',
            ]);

        $meta = $this->meta('gaz', 'zanussi');

        $this->assertSame('عنوانِ دستیِ اپراتور برای اجاق گاز زانوسی', $meta['meta_title']);
        $this->assertSame('توضیحاتِ دستیِ اپراتور.', $meta['meta_description']);
    }

    /** @return array{meta_title: string, meta_description: string} */
    private function meta(string $device, string $brand): array
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyTrustedFrontend::class)
            ->getJson("/v1/catalog/devices/{$device}/{$brand}");

        $response->assertOk();

        return [
            'meta_title' => (string) $response->json('meta_title'),
            'meta_description' => (string) $response->json('meta_description'),
        ];
    }

    private function makeDevice(string $name, string $slug): Device
    {
        $device = Device::forceCreate(['name' => $name, 'slug' => $slug, 'is_active' => true]);

        DeviceBrandPage::forceCreate([
            'device_id' => $device->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        return $device;
    }

    private function createTables(): void
    {
        Schema::create('crm_brands', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('logo')->nullable();
            $t->text('description')->nullable();
            $t->string('meta_title', 200)->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('device_category_id')->nullable();
            $t->string('name');
            $t->string('short_name', 80)->nullable();
            $t->string('slug')->unique();
            $t->string('icon')->nullable();
            $t->string('thumbnail')->nullable();
            $t->string('tone')->nullable();
            $t->text('description')->nullable();
            $t->string('service_name', 160)->nullable();
            $t->string('meta_title', 200)->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_device_brand_pages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->unsignedBigInteger('brand_id');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('meta_title', 200)->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });

        // کانالِ کاتالوگ حالا `seo_meta` را هم می‌خواند تا با کانالِ سئو یکی باشد.
        Schema::create('seo_meta', function ($t) {
            $t->id();
            $t->string('seoable_type');
            $t->unsignedBigInteger('seoable_id');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('site_page_sections', function ($t) {
            $t->id();
            $t->string('page_slug');
            $t->string('section_key');
            $t->json('payload')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();
        });

        // سکشن‌های جانبیِ صفحهٔ ترکیبی (FAQ، نظرات، انجمن، مقالات) بی‌قید‌وشرط
        // اجرا می‌شوند، پس جدول‌هایشان باید وجود داشته باشند حتی وقتی خالی‌اند.
        // خالی‌ماندنشان عمدی است: این تست دربارهٔ متا است، نه محتوا.
        Schema::create('site_taxonomies', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->string('type')->default('faq');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('faqs', function ($t) {
            $t->id();
            $t->text('question')->nullable();
            $t->text('answer')->nullable();
            $t->boolean('is_published')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('site_faq_taxonomies', function ($t) {
            $t->id();
            $t->unsignedBigInteger('faq_id');
            $t->unsignedBigInteger('taxonomy_id');
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('crm_device_brand_page_faqs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('page_id');
            $t->unsignedBigInteger('faq_id');
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('crm_device_brand_page_faq_categories', function ($t) {
            $t->id();
            $t->unsignedBigInteger('page_id');
            $t->unsignedBigInteger('taxonomy_id');
            $t->unsignedInteger('sort_order')->default(0);
        });

        // همان الگو در سطحِ دستگاه و برند هم تکرار می‌شود (FaqSectionBuilder
        // هر سه مالک را می‌پیماید).
        foreach (['crm_device_faqs' => 'device_id', 'crm_brand_faqs' => 'brand_id'] as $table => $owner) {
            Schema::create($table, function ($t) use ($owner) {
                $t->id();
                $t->unsignedBigInteger($owner);
                $t->unsignedBigInteger('faq_id');
                $t->unsignedInteger('sort_order')->default(0);
            });
        }

        foreach (['crm_device_faq_categories' => 'device_id', 'crm_brand_faq_categories' => 'brand_id'] as $table => $owner) {
            Schema::create($table, function ($t) use ($owner) {
                $t->id();
                $t->unsignedBigInteger($owner);
                $t->unsignedBigInteger('taxonomy_id');
                $t->unsignedInteger('sort_order')->default(0);
            });
        }

        Schema::create('crm_device_brand_page_reviews', function ($t) {
            $t->id();
            $t->unsignedBigInteger('page_id');
            $t->unsignedBigInteger('review_id');
        });

        Schema::create('site_reviews', function ($t) {
            $t->id();
            $t->string('type')->nullable();
            $t->string('status')->nullable();
            $t->string('author_name')->nullable();
            $t->string('topic')->nullable();
            $t->unsignedTinyInteger('rating')->default(5);
            $t->string('audio_url')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->text('content')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->timestamps();
        });

        foreach (['site_review_devices' => 'device_id', 'site_review_brands' => 'brand_id'] as $table => $column) {
            Schema::create($table, function ($t) use ($column) {
                $t->id();
                $t->unsignedBigInteger('review_id');
                $t->unsignedBigInteger($column);
            });
        }

        Schema::create('site_review_taxonomies', function ($t) {
            $t->id();
            $t->unsignedBigInteger('review_id');
            $t->unsignedBigInteger('taxonomy_id');
            $t->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('site_forum_questions', function ($t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('site_blog_articles', function ($t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('slug')->nullable();
            $t->text('excerpt')->nullable();
            $t->string('cover_image')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        foreach (['site_blog_article_devices' => 'device_id', 'site_blog_article_brands' => 'brand_id'] as $table => $column) {
            Schema::create($table, function ($t) use ($column) {
                $t->id();
                $t->unsignedBigInteger('article_id');
                $t->unsignedBigInteger($column);
            });
        }

        // ناظرِ AppCacheBumpObserver با هر ذخیرهٔ برند/دستگاه نسخهٔ کش اپ را
        // بالا می‌برد و روی همین جدول می‌نویسد.
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('seo_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general');
            $t->timestamps();
        });
    }
}
