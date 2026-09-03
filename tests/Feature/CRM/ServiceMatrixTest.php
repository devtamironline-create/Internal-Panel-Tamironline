<?php

namespace Tests\Feature\CRM;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\ServiceMatrixController;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\ServiceType;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\ServiceTypeOptions;
use Tests\TestCase;

/**
 * صفحهٔ مدیریتِ نوع خدمت: ذخیرهٔ نوع‌های دستگاه/تکنسین، از جمله «تیک‌نخورده‌ها»
 * که باید پاک شوند (چک‌باکسِ خالی در فرم ارسال نمی‌شود).
 */
class ServiceMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceTypeOptions::flush();

        Schema::create('crm_service_types', fn (Blueprint $t) => tap($t, fn ($x) => [
            $x->id(), $x->string('slug'), $x->string('name'), $x->integer('sort_order')->default(0),
            $x->boolean('is_active')->default(true), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn (Blueprint $t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->json('order_types')->nullable(), $x->boolean('is_active_app')->default(true),
            $x->boolean('is_featured')->default(false), $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_technicians', fn (Blueprint $t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('mobile')->nullable(),
            $x->json('service_types')->nullable(), $x->string('status', 20)->default('active'),
            $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('settings', fn (Blueprint $t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));

        foreach ([['repair', 1], ['service', 2], ['install', 3]] as [$slug, $sort]) {
            ServiceType::create(['slug' => $slug, 'name' => $slug, 'sort_order' => $sort, 'is_active' => true]);
        }
    }

    protected function tearDown(): void
    {
        ServiceTypeOptions::flush();
        parent::tearDown();
    }

    public function test_update_devices_sets_and_clears(): void
    {
        $d1 = Device::create(['name' => 'یخچال', 'order_types' => ['repair', 'service']]);
        $d2 = Device::create(['name' => 'لباسشویی', 'order_types' => ['install']]);

        // فقط d1 در ماتریس است؛ d2 (تیک‌نخورده) باید پاک شود.
        $req = Request::create('/', 'PUT', ['devices' => [$d1->id => ['service', 'bogus']]]);
        (new ServiceMatrixController)->updateDevices($req);

        $this->assertSame(['service'], $d1->refresh()->order_types); // فقط slugِ معتبر
        $this->assertSame([], $d2->refresh()->order_types);          // پاک شد
    }

    public function test_update_technicians_sets_and_clears(): void
    {
        $t1 = Technician::forceCreate(['first_name' => 'الف', 'mobile' => '09120000001', 'service_types' => ['repair']]);
        $t2 = Technician::forceCreate(['first_name' => 'ب', 'mobile' => '09120000002', 'service_types' => ['install']]);

        $req = Request::create('/', 'PUT', ['technicians' => [$t1->id => ['repair', 'install']]]);
        (new ServiceMatrixController)->updateTechnicians($req);

        $this->assertSame(['repair', 'install'], $t1->refresh()->service_types);
        $this->assertSame([], $t2->refresh()->service_types);
    }
}
