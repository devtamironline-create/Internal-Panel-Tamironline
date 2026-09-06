<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Console\Commands\LegacyRedirectMap;
use Modules\Seo\Models\SeoNotFound;
use Modules\Seo\Models\SeoRedirect;
use Tests\TestCase;

/**
 * تفکیکِ اسلاگِ قدیمیِ {brand}-{device} به device/brand — با دقتِ اسلاگ‌های
 * چندکلمه‌ای (side-by-side, wall-mounted-boiler, refrigerator-freezer).
 */
class LegacyRedirectMapTest extends TestCase
{
    private function split(string $core, array $devices, array $brands): array
    {
        $m = new \ReflectionMethod(LegacyRedirectMap::class, 'split');
        $m->setAccessible(true);

        return $m->invoke(new LegacyRedirectMap, $core, array_flip($devices), array_flip($brands));
    }

    public function test_resolves_multiword_device_slugs(): void
    {
        $devices = ['microwave', 'refrigerator-freezer', 'side-by-side', 'wall-mounted-boiler'];
        $brands = ['samsung', 'lg', 'bosch', 'general-electric'];

        $this->assertSame(['microwave', 'samsung'], $this->split('samsung-microwave', $devices, $brands));
        $this->assertSame(['refrigerator-freezer', 'lg'], $this->split('lg-refrigerator-freezer', $devices, $brands));
        $this->assertSame(['wall-mounted-boiler', 'bosch'], $this->split('bosch-wall-mounted-boiler', $devices, $brands));
        $this->assertSame(['side-by-side', 'samsung'], $this->split('samsung-side-by-side', $devices, $brands));
    }

    public function test_multiword_brand_is_not_mis_split(): void
    {
        $devices = ['refrigerator-freezer'];
        $brands = ['general-electric'];
        // general-electric-refrigerator-freezer → brand=general-electric, device=refrigerator-freezer
        $this->assertSame(['refrigerator-freezer', 'general-electric'],
            $this->split('general-electric-refrigerator-freezer', $devices, $brands));
    }

    public function test_unknown_combo_is_unresolved(): void
    {
        $this->assertSame([null, null], $this->split('acme-teleporter', ['microwave'], ['samsung']));
    }

    public function test_write_upserts_published_rows_without_duplicates(): void
    {
        $this->createTables();

        $d = Device::create(['name' => 'جاروبرقی', 'slug' => 'vacuum-cleaner', 'is_active' => true]);
        $b = Brand::create(['name' => 'فیلیپس', 'slug' => 'philips', 'is_active' => true]);
        DeviceBrandPage::create(['device_id' => $d->id, 'brand_id' => $b->id, 'is_active' => true]);

        // اسلاگِ قدیمیِ *-repair در لاگِ ۴۰۴.
        SeoNotFound::create(['uri' => '/services/philips-vacuum-cleaner-repair', 'hits' => 12]);

        $out = storage_path('app/test-legacy-redirects.csv');
        Artisan::call('seo:legacy-redirect-map', ['--out' => $out, '--write' => true]);

        $rec = SeoRedirect::where('source', '/services/philips-vacuum-cleaner-repair')->first();
        $this->assertNotNull($rec);
        $this->assertSame('/services/vacuum-cleaner/philips', $rec->target);
        $this->assertSame(301, $rec->status_code);
        $this->assertSame('exact', $rec->match_type);
        $this->assertTrue((bool) $rec->is_active);

        // اجرای دوباره → بدونِ رکوردِ تکراری.
        Artisan::call('seo:legacy-redirect-map', ['--out' => $out, '--write' => true]);
        $this->assertSame(1, SeoRedirect::where('source', '/services/philips-vacuum-cleaner-repair')->count());

        @unlink($out);
    }

    private function createTables(): void
    {
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_device_brand_pages', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('device_id'), $x->unsignedBigInteger('brand_id'),
            $x->string('title')->nullable(), $x->text('description')->nullable(),
            $x->string('meta_title')->nullable(), $x->text('meta_description')->nullable(),
            $x->boolean('is_active')->default(false), $x->timestamps(),
        ]));
        Schema::create('seo_not_found_logs', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->text('uri')->nullable(), $x->string('uri_hash')->nullable(),
            $x->integer('hits')->default(0), $x->timestamp('ignored_at')->nullable(), $x->timestamps(),
        ]));
        Schema::create('seo_redirects', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('source'), $x->string('target'),
            $x->integer('status_code')->default(301), $x->string('match_type')->default('exact'),
            $x->boolean('is_active')->default(true), $x->integer('hits')->default(0),
            $x->timestamp('last_hit_at')->nullable(), $x->timestamps(),
        ]));
        Schema::create('site_blog_articles', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('slug')->nullable(), $x->string('title')->nullable(),
            $x->longText('content')->nullable(), $x->timestamps(),
        ]));
    }
}
