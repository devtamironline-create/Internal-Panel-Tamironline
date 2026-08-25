<?php

namespace Tests\Feature\Site;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\Site\Support\CaptionTokens;
use Tests\TestCase;

/**
 * کپشنِ داینامیک (۱۴۰۵/۰۶/۰۳): توکن‌های {{device}}/{{brand}}/{{cities}}…
 * سمتِ پنل با پوششِ واقعی (تگِ تکنسین‌ها) resolve می‌شوند؛ در contextِ
 * برند، شهرها به شهرهای مجازِ همان برند محدودند؛ توکنِ خالی بدونِ
 * فاصلهٔ دوتایی حذف می‌شود.
 */
class CaptionTokensTest extends TestCase
{
    private Province $khorasan;

    private City $mashhad;

    private City $tehran;

    private Device $washer;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('province_id')->nullable(),
            $x->unsignedBigInteger('parent_city_id')->nullable(),
            $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_technicians', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('mobile')->nullable(),
            $x->string('status', 20)->default('active'), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_technician_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('city_id'),
        ]));
        Schema::create('crm_technician_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('device_id'),
            $x->integer('priority')->nullable(),
        ]));
        Schema::create('crm_technician_brands', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('brand_id'),
        ]));
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
        Schema::create('crm_settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(), $x->timestamps(),
        ]));

        $this->khorasan = Province::create(['name' => 'خراسان رضوی']);
        $tehranProv = Province::create(['name' => 'تهران']);
        $this->mashhad = City::create(['province_id' => $this->khorasan->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $this->tehran = City::create(['province_id' => $tehranProv->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $this->washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
    }

    private function tech(array $cityIds, array $deviceIds = [], array $brandIds = []): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => 'active',
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);
        $t->brands()->sync($brandIds);

        return $t;
    }

    public function test_tokens_resolve_with_real_coverage_and_empty_brand_is_cleaned(): void
    {
        $this->tech([$this->tehran->id], [$this->washer->id]);
        $this->tech([$this->mashhad->id], [$this->washer->id]);

        $out = CaptionTokens::resolve(
            'خدمات {{device}} {{brand}} در {{cities}}',
            $this->washer
        );

        // بدونِ برند: توکنِ برند حذف و فاصلهٔ دوتایی جمع می‌شود؛
        // «تهران» (مرکز استان — طبق ترتیبِ پوشش) و «مشهد» با «و».
        $this->assertSame('خدمات لباسشویی در تهران و مشهد', $out);
    }

    public function test_brand_context_limits_cities_to_that_brands_coverage(): void
    {
        $samsung = Brand::create(['name' => 'سامسونگ', 'slug' => 'samsung']);
        // تهران: تکنسینِ بدونِ تگِ برند (همهٔ برندها)؛ مشهد: فقط برندِ دیگر.
        $lg = Brand::create(['name' => 'ال‌جی', 'slug' => 'lg']);
        $this->tech([$this->tehran->id], [$this->washer->id]);
        $this->tech([$this->mashhad->id], [$this->washer->id], [$lg->id]);

        $out = CaptionTokens::resolve(
            'خدمات {{device}} {{brand}} در {{cities}} ({{city_count}} شهر)',
            $this->washer,
            $samsung
        );

        // مشهد برای سامسونگ مجاز نیست → فقط تهران.
        $this->assertSame('خدمات لباسشویی سامسونگ در تهران (۱ شهر)', $out);
    }

    public function test_text_without_tokens_and_null_pass_through(): void
    {
        $this->assertSame('متن ثابت', CaptionTokens::resolve('متن ثابت', $this->washer));
        $this->assertNull(CaptionTokens::resolve(null, $this->washer));
    }

    public function test_a_device_with_no_coverage_resolves_location_tokens_to_nothing(): void
    {
        // تکنسینِ تگ‌خورده برای دستگاهِ دیگر تا فیچر «با داده» بماند.
        $fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
        $this->tech([$this->tehran->id], [$fridge->id]);

        $out = CaptionTokens::resolve('خدمات {{device}} در {{cities}}', $this->washer);

        $this->assertSame('خدمات لباسشویی در', $out);
    }
}
