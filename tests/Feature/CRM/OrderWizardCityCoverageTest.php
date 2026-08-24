<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\CRM\Livewire\OrderWizard;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * پوششِ خدماتِ شهر در ویزاردِ ثبتِ سفارشِ ادمین (تصمیمِ ۱۴۰۵/۰۵/۲۹):
 *
 *   - «سفارش» فقط برای دستگاهی ثبت می‌شود که در شهرِ انتخاب‌شده حداقل یک
 *     تکنسینِ فعال با تگِ صریحِ همان شهر مهارتش (تگِ دستگاه) را دارد —
 *     مثالِ مشهد: فقط لباسشویی؛ اردبیلِ بدونِ تکنسین: هیچ.
 *   - سخت‌گیرانه‌تر از سیستمِ پیشنهاد: تکنسینِ بدونِ تگِ شهر پوششِ هیچ
 *     شهری حساب نمی‌شود (وگرنه شهرِ بدونِ تکنسین همه‌چیز را باز می‌کرد).
 *     تگِ دستگاهِ خالی برای تکنسینِ تگ‌خورده = همه‌کاره.
 *   - ایمنی: اگر هیچ تکنسینِ فعالی تگِ شهر نداشته باشد، محدودیت غیرفعال
 *     است (با بنرِ هشدار) تا ثبتِ سفارش سراسری قفل نشود.
 *   - ثبتِ «لید» هیچ محدودیتی ندارد.
 *   - تغییرِ شهر انتخابِ دستگاهِ خارج از پوشش را پاک می‌کند.
 */
class OrderWizardCityCoverageTest extends TestCase
{
    private Province $province;

    private City $mashhad;

    private City $tehran;

    private Brand $brand;

    private Device $washer;

    private Device $fridge;

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

        foreach (['crm_brands', 'crm_devices'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('is_featured')->default(false);
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_cities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('city_id');
        });

        Schema::create('crm_technician_devices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('device_id');
            $t->integer('priority')->nullable();
        });

        Schema::create('crm_technician_districts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('district_id');
        });

        // ساخت شهر، نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_lead_reasons', function ($t) {
            $t->id();
            $t->string('title')->nullable();
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

        $this->province = Province::create(['name' => 'خراسان رضوی']);
        $this->mashhad = City::create(['province_id' => $this->province->id, 'name' => 'مشهد', 'slug' => 'mashhad']);
        $this->tehran = City::create(['province_id' => $this->province->id, 'name' => 'شهر دوم', 'slug' => 'city2']);
        $this->brand = Brand::create(['name' => 'اسنوا', 'slug' => 'snowa']);
        $this->washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $this->fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);
    }

    /** تکنسین فعال با تگ شهر و دستگاه مشخص. */
    private function technician(array $cityIds, array $deviceIds, string $status = 'active'): Technician
    {
        $t = Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => $status,
        ]);
        $t->cities()->sync($cityIds);
        $t->devices()->sync($deviceIds);

        return $t;
    }

    private function wizard(int $cityId)
    {
        return Livewire::test(OrderWizard::class)
            ->set('provinceId', $this->province->id)
            ->set('cityId', $cityId)
            ->set('brandId', $this->brand->id);
    }

    public function test_an_uncovered_device_cannot_be_ordered_in_that_city(): void
    {
        // مشهد فقط تکنسین لباسشویی دارد — یخچال قابل سفارش نیست.
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasErrors(['deviceId']);
    }

    public function test_a_covered_device_passes(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->washer->id)
            ->call('next')
            ->assertHasNoErrors(['deviceId']);
    }

    public function test_leads_are_exempt_from_coverage(): void
    {
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $reason = \Modules\CRM\Models\LeadReason::forceCreate(['title' => 'خارج از پوشش', 'is_active' => true]);

        $this->wizard($this->mashhad->id)
            ->set('isOrderable', false)
            ->set('leadReasonId', $reason->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasNoErrors(['deviceId']);
    }

    public function test_a_generalist_technician_opens_all_devices(): void
    {
        // تگِ دستگاهِ خالی = همه‌کاره — هیچ محدودیتی روی لیست نمی‌ماند.
        $this->technician([$this->mashhad->id], []);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasNoErrors(['deviceId']);
    }

    public function test_a_technician_without_city_tags_does_not_open_other_cities(): void
    {
        // گزارشِ اردبیل: تکنسینِ بدونِ تگِ شهر نباید شهرِ بدونِ تکنسین را باز کند.
        // (یک تکنسینِ تگ‌خورده در شهرِ دیگر هست تا فیچر «با داده» حساب شود.)
        $this->technician([$this->tehran->id], [$this->washer->id]);
        $this->technician([], [$this->fridge->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasErrors(['deviceId']);
    }

    public function test_the_restriction_is_disabled_when_no_technician_has_city_tags(): void
    {
        // فیچر بدونِ داده: هیچ تکنسینی تگِ شهر ندارد → قفلِ سراسری ممنوع.
        $this->technician([], [$this->washer->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasNoErrors(['deviceId']);
    }

    public function test_an_inactive_technician_does_not_count_as_coverage(): void
    {
        $this->technician([$this->mashhad->id], [$this->fridge->id], status: 'inactive');
        // یک تکنسینِ فعالِ تگ‌خورده در شهرِ دیگر، تا فیچر «با داده» بماند و
        // fallback ایمنی (بدونِ هیچ تگی) فعال نشود.
        $this->technician([$this->tehran->id], [$this->washer->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->fridge->id)
            ->call('next')
            ->assertHasErrors(['deviceId']);
    }

    /**
     * پوششِ سطحِ منطقه (۱۴۰۵/۰۶/۰۲): منطقه‌ای که برای دستگاهِ انتخابی
     * تکنسینِ فعال ندارد قابلِ ثبتِ «سفارش» نیست — لید آزاد است.
     */
    public function test_an_uncovered_district_blocks_the_order_but_a_covered_one_passes(): void
    {
        $district5 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۵', 'slug' => 'r5']);
        $district9 = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۹', 'slug' => 'r9']);

        // تکنسینِ لباسشویی فقط منطقهٔ ۵ مشهد را تگ کرده.
        $t = $this->technician([$this->mashhad->id], [$this->washer->id]);
        $t->regions()->sync([$district5->id]);

        $base = fn () => $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->washer->id)
            ->set('currentStep', 2)
            ->set('showNewCustomerForm', true)
            ->set('newName', 'مشتری تست')
            ->set('newMobile', '09123456789')
            ->set('introduction', 'گوگل')
            ->set('address', 'خیابان تست، پلاک ۱');

        // منطقهٔ بدونِ تکنسین → خطا روی regionId.
        $base()->set('regionId', $district9->id)
            ->call('next')
            ->assertHasErrors(['regionId']);

        // منطقهٔ تحتِ پوشش → عبور.
        $base()->set('regionId', $district5->id)
            ->call('next')
            ->assertHasNoErrors(['regionId']);
    }

    public function test_a_whole_city_technician_covers_every_district(): void
    {
        $district = City::create(['province_id' => $this->province->id, 'parent_city_id' => $this->mashhad->id, 'name' => 'منطقه ۳', 'slug' => 'r3']);

        // بدونِ تگِ منطقه = کلِ شهر (همان معنای سیستمِ تخصیص).
        $this->technician([$this->mashhad->id], [$this->washer->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->washer->id)
            ->set('currentStep', 2)
            ->set('showNewCustomerForm', true)
            ->set('newName', 'مشتری تست')
            ->set('newMobile', '09123456780')
            ->set('introduction', 'گوگل')
            ->set('address', 'خیابان تست، پلاک ۲')
            ->set('regionId', $district->id)
            ->call('next')
            ->assertHasNoErrors(['regionId']);
    }

    public function test_changing_the_city_clears_an_uncovered_selection(): void
    {
        // لباسشویی فقط در مشهد پوشش دارد؛ یخچال فقط در شهر دوم.
        $this->technician([$this->mashhad->id], [$this->washer->id]);
        $this->technician([$this->tehran->id], [$this->fridge->id]);

        $this->wizard($this->mashhad->id)
            ->set('deviceId', $this->washer->id)
            ->set('cityId', $this->tehran->id)
            ->assertSet('deviceId', null);
    }
}
