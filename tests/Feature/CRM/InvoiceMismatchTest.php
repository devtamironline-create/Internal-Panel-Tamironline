<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\InvoiceMismatch;
use Tests\TestCase;

/**
 * «مبلغِ سفارش با فاکتورِ صادرشده نمی‌خواند».
 *
 * کارتِ «فاکتور سفارش» زنده محاسبه می‌شود و فاکتور یک snapshot است. تا
 * وقتی چیزی عوض نشود این دو یکی‌اند؛ چند مسیرِ قانونی می‌توانند جدایشان
 * کنند و پنل باید هرکدام را با علتِ درست اعلام کند.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class InvoiceMismatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->unsignedTinyInteger('percent')->default(0);
            $t->unsignedTinyInteger('tech_per_of_all')->default(0);
            $t->string('type_of_calc_tech', 20)->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamp('last_assigned_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_percent_changes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedTinyInteger('percent')->nullable();
            $t->unsignedTinyInteger('tech_per_of_all')->nullable();
            $t->timestamp('effective_from')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->bigInteger('price_customer')->nullable();
            $t->bigInteger('cost_price')->nullable();
            $t->bigInteger('total_invoice')->nullable();
            $t->bigInteger('final_price')->nullable();
            $t->boolean('save_as_draft')->default(false);
            $t->boolean('is_legacy_closed')->default(false);
            $t->bigInteger('legacy_tech_share')->nullable();
            $t->bigInteger('legacy_company_share')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->string('invoice_code')->nullable();
            $t->string('public_token')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->bigInteger('total_amount')->default(0);
            $t->bigInteger('tech_share')->default(0);
            $t->bigInteger('company_share')->default(0);
            $t->string('calc_type', 30)->nullable();
            $t->unsignedTinyInteger('commission_percent')->default(0);
            $t->boolean('in_wallet')->default(false);
            $t->string('status', 20)->default('issued');
            $t->string('collection_method', 10)->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->unsignedBigInteger('superseded_by_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    // ───────────────────────── helpers

    private function technician(int $percent = 30): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'ایوب', 'firstname_tech' => 'ایوب خزایی',
            'mobile' => '09120000111', 'status' => 'active',
            'percent' => $percent, 'tech_per_of_all' => 0,
        ]);
    }

    private function order(Technician $tech, int $priceCustomer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_code' => 'ORD-1',
            'technician_id' => $tech->id,
            'status' => 'completed',
            'price_customer' => $priceCustomer,
            'cost_price' => 0,
            'total_invoice' => $priceCustomer,
            'completed_at' => now()->subDays(3),
            'is_lead' => false,
        ], $attributes));
    }

    /** فاکتورِ snapshot با همان قاعدهٔ CommissionCalculator. */
    private function invoice(Order $order, int $total, int $percent = 30, array $attributes = []): Invoice
    {
        $company = intdiv($total * $percent, 100);

        return Invoice::create(array_merge([
            'invoice_code' => 'INV-1',
            'order_id' => $order->id,
            'technician_id' => $order->technician_id,
            'total_amount' => $total,
            'tech_share' => $total - $company,
            'company_share' => $company,
            'commission_percent' => $percent,
            'calc_type' => 'percent_of_total',
            'status' => 'issued',
            'issued_at' => now()->subDays(4),
        ], $attributes));
    }

    // ───────────────────────── بدون مغایرت

    public function test_matching_amounts_report_nothing(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 17_000_000);
        $this->invoice($order, 17_000_000);

        $this->assertNull(InvoiceMismatch::check($order->fresh()));
        $this->assertFalse(InvoiceMismatch::exists($order->fresh()));
    }

    public function test_an_order_without_an_invoice_is_not_a_mismatch(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 17_000_000);

        $this->assertNull(InvoiceMismatch::check($order->fresh()));
    }

    // ───────────────────────── مبلغ

    public function test_an_amount_changed_after_issuing_is_detected(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 17_000_000);
        $this->invoice($order, 17_000_000);

        // مبلغ بعداً به ۱۸ میلیون رفت — دقیقاً حالتِ گزارش‌شده.
        $order->update(['price_customer' => 18_000_000, 'total_invoice' => 18_000_000]);

        $result = InvoiceMismatch::check($order->fresh());

        $this->assertNotNull($result);
        $this->assertSame(18_000_000, $result['order_total']);
        $this->assertSame(17_000_000, $result['invoice_total']);
        $this->assertSame(1_000_000, $result['difference']);
        $this->assertSame('edited_after_issue', $result['reason']);
        $this->assertSame(12_600_000, $result['order_tech']);
        $this->assertSame(11_900_000, $result['invoice_tech']);
    }

    public function test_a_draft_completion_is_named_as_the_cause(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 17_000_000);
        $this->invoice($order, 17_000_000);

        $order->update([
            'price_customer' => 18_000_000,
            'total_invoice' => 18_000_000,
            'save_as_draft' => true,
        ]);

        $result = InvoiceMismatch::check($order->fresh());

        $this->assertSame('draft', $result['reason']);
        $this->assertStringContainsString('پیش‌نویس', $result['reason_label']);
    }

    // ───────────────────────── درصد

    public function test_the_card_takes_its_percent_from_the_issued_invoice(): void
    {
        $tech = $this->technician(30);
        $order = $this->order($tech, 17_000_000);
        $this->invoice($order, 17_000_000, 30);

        // درصد *امروز* عوض شد — تاریخِ مالیِ گذشته نباید تکان بخورد.
        $tech->update(['percent' => 40]);
        DB::table('crm_technician_percent_changes')->insert([
            'technician_id' => $tech->id,
            'percent' => 40,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fresh = $order->fresh();
        $fin = $fresh->financialSummary();

        // کارت باید همان ۳۰٪ فاکتور را نشان دهد، نه ۴۰٪ امروز — وگرنه
        // تغییرِ درصد بدونِ هیچ اتفاقِ واقعی «مغایرت» می‌ساخت.
        $this->assertSame(30, $fin['percent']);
        $this->assertSame(11_900_000, $fin['tech_share']);
        $this->assertNull(InvoiceMismatch::check($fresh));
    }

    // ───────────────────────── فاکتورِ باطل‌شده

    public function test_a_superseded_invoice_is_ignored_when_an_active_one_exists(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 18_000_000);

        $this->invoice($order, 17_000_000, 30, [
            'invoice_code' => 'INV-OLD',
            'superseded_at' => now()->subDay(),
        ]);
        $this->invoice($order, 18_000_000, 30, ['invoice_code' => 'INV-NEW']);

        $this->assertNull(InvoiceMismatch::check($order->fresh()));
    }

    public function test_the_active_invoice_code_is_reported(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 18_000_000);
        $this->invoice($order, 17_000_000, 30, ['invoice_code' => 'INV-2607-00632']);

        $result = InvoiceMismatch::check($order->fresh());

        $this->assertSame('INV-2607-00632', $result['invoice_code']);
    }

    // ───────────────────────── legacy

    public function test_legacy_orders_are_labelled_as_such(): void
    {
        $tech = $this->technician();
        $order = $this->order($tech, 18_000_000, [
            'is_legacy_closed' => true,
            'legacy_tech_share' => 11_900_000,
            'legacy_company_share' => 5_100_000,
        ]);
        $this->invoice($order, 17_000_000);

        $result = InvoiceMismatch::check($order->fresh());

        $this->assertSame('legacy', $result['reason']);
    }
}
