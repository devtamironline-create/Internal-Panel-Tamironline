<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * خلاصهٔ پوششِ داشبورد (۱۴۰۵/۰۶/۰۴): استان‌های تحتِ پوشش به‌صورت خودکار
 * از تگِ تکنسین‌ها می‌آیند — دیگر hardcode «تهران و کرج» نیست؛ با تگ‌خوردنِ
 * تکنسینِ مشهد، «خراسان رضوی» هم در داشبورد ظاهر می‌شود.
 */
class DashboardCoverageSummaryTest extends TestCase
{
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
        Schema::create('crm_settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(), $x->timestamps(),
        ]));
        // ساختِ شهر، نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function summary(): array
    {
        $m = new \ReflectionMethod(DashboardController::class, 'coverageSummary');
        $m->setAccessible(true);

        return $m->invoke(new DashboardController);
    }

    public function test_coverage_summary_includes_mashhad_from_technician_tags(): void
    {
        $tehranP = Province::create(['name' => 'تهران']);
        $khorasan = Province::create(['name' => 'خراسان رضوی']);
        $tehran = City::create(['province_id' => $tehranP->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $mashhad = City::create(['province_id' => $khorasan->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);

        $tTehran = Technician::forceCreate(['first_name' => 'ت۱', 'mobile' => '09120000001', 'status' => 'active']);
        $tTehran->cities()->sync([$tehran->id]);
        $tTehran->devices()->sync([$washer->id]);

        $tMashhad = Technician::forceCreate(['first_name' => 'ت۲', 'mobile' => '09120000002', 'status' => 'active']);
        $tMashhad->cities()->sync([$mashhad->id]);
        $tMashhad->devices()->sync([$washer->id]);

        $summary = $this->summary();

        $names = collect($summary['provinces'])->pluck('name');
        $this->assertTrue($names->contains('خراسان رضوی'));
        $this->assertTrue($names->contains('تهران'));
        $this->assertSame(2, $summary['province_count']);
        $this->assertSame(2, $summary['city_count']);

        $khorasanRow = collect($summary['provinces'])->firstWhere('name', 'خراسان رضوی');
        $this->assertContains('مشهد', $khorasanRow['cities']);
        $this->assertSame(1, $khorasanRow['techs']);
    }
}
