<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Livewire\OrderWizard;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerAddress;
use Modules\CRM\Models\Province;
use Tests\TestCase;

/**
 * ذخیرهٔ آدرسِ ویزارد در دفترچهٔ آدرسِ مشتری (۱۴۰۵/۰۶/۰۲):
 *
 *   - آدرسِ جدید → رکوردِ ماندگارِ crm_customer_addresses با استان/شهر/
 *     منطقه و مختصاتِ نقطهٔ نقشه؛ اگر مشتری آدرسِ پیش‌فرض ندارد، همین
 *     پیش‌فرض می‌شود و id برای لینکِ order.address_id برمی‌گردد.
 *   - همان متنِ آدرس دوباره → رکوردِ تکراری ساخته نمی‌شود؛ به‌روزرسانی.
 *   - آدرسِ خالی → هیچ.
 */
class OrderWizardCustomerAddressSaveTest extends TestCase
{
    private Province $province;

    private City $tehran;

    private City $d5;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', function ($t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_cities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('parent_city_id')->nullable();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('phone')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_customer_addresses', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->string('label')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->unsignedBigInteger('district_id')->nullable();
            $t->text('full_address')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('postal_code')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_transient')->default(false);
            $t->timestamps();
        });

        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        $this->province = Province::create(['name' => 'تهران']);
        $this->tehran = City::create(['province_id' => $this->province->id, 'name' => 'تهران', 'slug' => 'tehran']);
        $this->d5 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->tehran->id, 'name' => 'منطقه ۵', 'slug' => 'r5']);
        $this->customer = Customer::forceCreate(['first_name' => 'مشتری', 'mobile' => '09121234567', 'phone' => '02177001122']);
    }

    /** دسترسی به متدِ protected از طریق subclass تستی. */
    private function harness(): object
    {
        return new class extends OrderWizard
        {
            public function callSave(Customer $c): ?int
            {
                return $this->saveCustomerAddress($c);
            }
        };
    }

    public function test_a_new_address_is_saved_with_coordinates_and_becomes_default(): void
    {
        $w = $this->harness();
        $w->provinceId = $this->province->id;
        $w->cityId = $this->tehran->id;
        $w->regionId = $this->d5->id;
        $w->address = 'ستارخان، خیابان کوثر دوم، پلاک ۱۰';
        $w->pickedLat = 35.7219;
        $w->pickedLng = 51.3347;

        $id = $w->callSave($this->customer);

        $this->assertNotNull($id);
        $addr = CustomerAddress::findOrFail($id);
        $this->assertSame($this->customer->id, $addr->customer_id);
        $this->assertSame($this->d5->id, $addr->district_id);
        $this->assertSame(35.7219, $addr->latitude);
        $this->assertSame(51.3347, $addr->longitude);
        $this->assertTrue($addr->is_default);
        $this->assertFalse($addr->is_transient);
        $this->assertSame('02177001122', $addr->phone);
    }

    public function test_the_same_address_text_updates_instead_of_duplicating(): void
    {
        $existing = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'خانه',
            'province_id' => $this->province->id,
            'city_id' => $this->tehran->id,
            'full_address' => 'ستارخان، پلاک ۱۰',
            'is_default' => true,
        ]);

        $w = $this->harness();
        $w->provinceId = $this->province->id;
        $w->cityId = $this->tehran->id;
        $w->regionId = $this->d5->id;
        $w->address = 'ستارخان، پلاک ۱۰';
        $w->pickedLat = 35.72;
        $w->pickedLng = 51.33;

        $id = $w->callSave($this->customer);

        $this->assertSame($existing->id, $id);
        $this->assertSame(1, CustomerAddress::count());
        $existing->refresh();
        $this->assertSame($this->d5->id, $existing->district_id);
        $this->assertSame(35.72, $existing->latitude);
    }

    public function test_without_picked_coordinates_existing_coordinates_are_kept(): void
    {
        $existing = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'province_id' => $this->province->id,
            'city_id' => $this->tehran->id,
            'full_address' => 'ستارخان، پلاک ۱۰',
            'latitude' => 35.5,
            'longitude' => 51.5,
        ]);

        $w = $this->harness();
        $w->provinceId = $this->province->id;
        $w->cityId = $this->tehran->id;
        $w->address = 'ستارخان، پلاک ۱۰';

        $w->callSave($this->customer);

        $existing->refresh();
        $this->assertSame(35.5, $existing->latitude);
    }

    public function test_a_second_address_does_not_steal_the_default_flag(): void
    {
        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => 'آدرس قبلی',
            'is_default' => true,
        ]);

        $w = $this->harness();
        $w->provinceId = $this->province->id;
        $w->cityId = $this->tehran->id;
        $w->address = 'آدرس جدید از پنل';

        $id = $w->callSave($this->customer);

        $this->assertFalse(CustomerAddress::findOrFail($id)->is_default);
    }

    public function test_an_empty_address_saves_nothing(): void
    {
        $w = $this->harness();
        $w->cityId = $this->tehran->id;
        $w->address = '   ';

        $this->assertNull($w->callSave($this->customer));
        $this->assertSame(0, CustomerAddress::count());
    }
}
