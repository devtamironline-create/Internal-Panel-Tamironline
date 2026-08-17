<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\CRM\Livewire\OrderWizard;
use Tests\TestCase;

/**
 * ولیدیشنِ موبایل در فرمِ ثبتِ سفارش.
 *
 * تستِ واحدِ `MobileNumber` قاعده را می‌سنجد؛ این‌جا سنجیده می‌شود که قاعده
 * واقعاً به فرم **وصل** است. آن دو یکی نیستند: قاعده می‌تواند بی‌نقص باشد و
 * کسی یادش برود در `validateStep2Customer` استفاده‌اش کند.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migrationهای CRM روی sqlite
 * اجرا نمی‌شود.
 */
class OrderWizardMobileValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_provinces', function ($t) {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('crm_cities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('parent_city_id')->nullable();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // ویزارد در هر رندر همهٔ مرحله‌ها را می‌سازد، پس جدول‌های مرحله‌های
        // دیگر هم باید وجود داشته باشند — حتی خالی.
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
            $t->timestamp('mobile_verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function wizard()
    {
        return Livewire::test(OrderWizard::class)
            ->set('showNewCustomerForm', true)
            ->set('newName', 'میثم خاتمی')
            ->set('introduction', 'گوگل')
            // آدرس/منطقه لازم نشود؛ موضوع این تست فقط موبایل است.
            ->set('isOrderable', false)
            // مرحلهٔ ۲ همان «مشتری و آدرس» است؛ اعتبارسنجیِ موبایل آن‌جا اجرا می‌شود.
            ->set('currentStep', 2);
    }

    /**
     * دقیقاً همان چیزی که گزارش شد: فیلد هر رشته‌ای را قبول می‌کرد.
     */
    public function test_a_non_numeric_value_is_rejected(): void
    {
        $this->wizard()
            ->set('newMobile', 'سلام')
            ->call('next')
            ->assertHasErrors(['newMobile']);
    }

    /**
     * این فرم لید هم ثبت می‌کند و مشتری ممکن است با تلفنِ ثابت تماس گرفته
     * باشد — بر خلافِ گذشته، شمارهٔ ثابتِ کامل (با کدِ شهر) باید پذیرفته شود.
     */
    public function test_a_landline_number_is_accepted(): void
    {
        $this->wizard()
            ->set('newMobile', '02188776655')
            ->call('next')
            ->assertHasNoErrors(['newMobile']);
    }

    public function test_an_incomplete_landline_number_is_rejected(): void
    {
        // بدونِ کدِ شهر (۸ رقم) → ناقص است و باید رد شود.
        $this->wizard()
            ->set('newMobile', '88776655')
            ->call('next')
            ->assertHasErrors(['newMobile']);
    }

    public function test_a_short_number_is_rejected(): void
    {
        $this->wizard()
            ->set('newMobile', '0912345')
            ->call('next')
            ->assertHasErrors(['newMobile']);
    }

    public function test_an_empty_value_is_still_required(): void
    {
        $this->wizard()
            ->set('newMobile', '')
            ->call('next')
            ->assertHasErrors(['newMobile']);
    }

    public function test_a_valid_number_passes(): void
    {
        $this->wizard()
            ->set('newMobile', '09123456789')
            ->call('next')
            ->assertHasNoErrors(['newMobile']);
    }

    /**
     * مهم‌تر از رد کردنِ غلط‌ها: اپراتوری که رقمِ فارسی تایپ می‌کند نباید گیر
     * کند. فیلد باید خودش تمیز شود، نه اینکه پیامِ خطا بگیرد.
     */
    public function test_persian_digits_are_cleaned_up_instead_of_rejected(): void
    {
        $this->wizard()
            ->set('newMobile', '۰۹۱۲۳۴۵۶۷۸۹')
            ->assertSet('newMobile', '09123456789')
            ->call('next')
            ->assertHasNoErrors(['newMobile']);
    }

    public function test_a_pasted_international_number_is_cleaned_up(): void
    {
        $this->wizard()
            ->set('newMobile', '+98 912 345 6789')
            ->assertSet('newMobile', '09123456789')
            ->call('next')
            ->assertHasNoErrors(['newMobile']);
    }
}
