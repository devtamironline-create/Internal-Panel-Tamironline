<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\ServicePriceController;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceServicePrice;
use Tests\TestCase;

/**
 * ثبتِ تعرفهٔ خدمات از پنل.
 *
 * ادعای قفل‌شده: validate() فقط کلیدهای حاضر در درخواست را برمی‌گرداند و
 * فرمِ «افزودن» فیلدِ compare_at_price را ندارد (قیمت هم با تیکِ «رایگان»
 * disabled می‌شود). کنترلر نباید روی کلیدِ غایب ۵۰۰ بدهد — هر بار ثبتِ
 * تعرفه در پروداکشن با «Undefined array key» می‌ترکید.
 *
 * کنترلر مستقیم صدا زده می‌شود؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class ServicePriceStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ثبتِ تعرفه، نسخهٔ کشِ اپ (AppCacheVersion) را در settings بالا می‌برد.
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_device_service_prices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->string('title', 300);
            $t->unsignedBigInteger('price')->nullable();
            $t->unsignedBigInteger('compare_at_price')->nullable();
            $t->boolean('is_free')->default(false);
            $t->string('note', 200)->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    /** همان چیزی که فرمِ واقعی می‌فرستد، نه یک درخواستِ ایدئال. */
    private function store(array $payload): void
    {
        $device = Device::forceCreate(['name' => 'لباسشویی', 'slug' => 'washing-machine']);

        $request = Request::create('/admin/crm/devices/'.$device->id.'/service-prices', 'POST', $payload);

        app(ServicePriceController::class)->store($request, $device);
    }

    /** فرمِ «افزودن» فیلدِ compare_at_price ندارد — نبودش نباید خطا بدهد. */
    public function test_storing_without_the_compare_at_price_field_works(): void
    {
        $this->store(['title' => 'باز و بست کامل', 'price' => '250000']);

        $price = DeviceServicePrice::firstOrFail();
        $this->assertSame(250000, $price->price);
        $this->assertNull($price->compare_at_price);
    }

    /** با تیکِ «رایگان»، input قیمت disabled و اصلاً ارسال نمی‌شود. */
    public function test_storing_a_free_service_without_the_price_field_works(): void
    {
        $this->store(['title' => 'مشاوره', 'is_free' => '1']);

        $price = DeviceServicePrice::firstOrFail();
        $this->assertTrue($price->is_free);
        $this->assertNull($price->price);
    }
}
