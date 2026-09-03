<?php

namespace Tests\Feature\CRM;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\CustomerController;
use Modules\CRM\Models\Customer;
use Tests\TestCase;

/**
 * سرچِ مشتری باید با نام، موبایل (فارسی یا انگلیسی) و شمارهٔ اشتراک کار کند.
 * باگِ رفع‌شده: موبایلِ ۱۱رقمیِ انگلیسی اشتباهاً «شمارهٔ اشتراک» تلقی می‌شد و
 * نتیجه نمی‌داد.
 */
class CustomerSearchNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('mobile')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Customer::create(['first_name' => 'علی', 'last_name' => 'رضایی', 'mobile' => '09123456789']);
        Customer::create(['first_name' => 'مریم', 'last_name' => 'کریمی', 'mobile' => '09350001122']);
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function search(string $term)
    {
        $controller = new CustomerController;
        $m = new \ReflectionMethod($controller, 'applyCustomerSearch');
        $m->setAccessible(true);

        $q = Customer::query();
        $m->invoke($controller, $q, $term);

        return $q->get();
    }

    public function test_english_full_phone_number_finds_the_customer(): void
    {
        // این دقیقاً همان چیزی است که قبلاً کار نمی‌کرد.
        $results = $this->search('09123456789');
        $this->assertCount(1, $results);
        $this->assertSame('علی', $results->first()->first_name);
    }

    public function test_persian_digit_phone_finds_the_customer(): void
    {
        $results = $this->search('۰۹۱۲۳۴۵۶۷۸۹');
        $this->assertCount(1, $results);
        $this->assertSame('09123456789', $results->first()->mobile);
    }

    public function test_partial_phone_finds_the_customer(): void
    {
        $this->assertCount(1, $this->search('91234'));
    }

    public function test_name_search_works(): void
    {
        $this->assertSame('مریم', $this->search('مریم')->first()->first_name);
        $this->assertSame('رضایی', $this->search('رضایی')->first()->last_name);
    }

    public function test_subscription_number_still_resolves(): void
    {
        $c = Customer::first();
        $subscription = (int) $c->id + Customer::SUBSCRIPTION_OFFSET;

        $results = $this->search((string) $subscription);
        $this->assertTrue($results->contains('id', $c->id));
    }
}
