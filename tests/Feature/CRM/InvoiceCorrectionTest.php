<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\InvoiceController;
use Modules\CRM\Http\Controllers\PaymentController;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\InvoiceService;
use Tests\TestCase;

/**
 * «اصلاح مبلغ فاکتور» + ریدایرکتِ لینک‌های باطل + لغو با برگشتِ کمیسیون
 * — قواعد قفل‌شدهٔ ۱۴۰۵/۰۵/۲۹:
 *
 *   ۱) اصلاح دقیقاً همان فاکتور را باطل می‌کند (نه فاکتورهای فعالِ دیگرِ
 *      سفارشِ بازگشتی)، کمیسیونش خودکار برمی‌گردد و فاکتورِ جدید با
 *      محاسبه‌گرِ استاندارد صادر می‌شود.
 *   ۲) فاکتورِ پرداخت‌شده/لغوشده اصلاح‌پذیر نیست؛ مبلغِ برابر هم رد می‌شود.
 *   ۳) لینکِ عمومی/پرداختِ فاکتورِ باطل بی‌سروصدا به فاکتورِ جایگزین
 *      ریدایرکت می‌شود — مشتری هیچ توضیحی نمی‌بیند.
 *   ۴) لغوِ فاکتور سهمِ شرکت را خودکار برمی‌گرداند؛ پرداخت‌شده قابلِ لغو نیست.
 */
class InvoiceCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->bigInteger('wallet_balance')->default(0);
            $t->integer('percent')->nullable();
            $t->integer('tech_per_of_all')->nullable();
            $t->string('type_of_calc_tech', 20)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_percent_changes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->integer('percent')->nullable();
            $t->integer('tech_per_of_all')->nullable();
            $t->timestamp('effective_from')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_tech_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->string('type', 30);
            $t->bigInteger('amount')->default(0);
            $t->bigInteger('balance_after')->nullable();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->string('invoice_code', 40)->nullable();
            $t->string('public_token', 64)->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->bigInteger('total_amount')->default(0);
            $t->bigInteger('tech_share')->default(0);
            $t->bigInteger('company_share')->default(0);
            $t->string('calc_type', 30)->nullable();
            $t->integer('commission_percent')->default(0);
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

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->tinyInteger('return_type')->nullable();
            $t->timestamp('return_reviewed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->boolean('save_as_draft')->default(false);
            $t->timestamp('completed_at')->nullable();
            $t->bigInteger('price_customer')->default(0);
            $t->bigInteger('cost_price')->default(0);
            $t->bigInteger('total_invoice')->default(0);
            $t->text('invoice_descripotion')->nullable();
            $t->json('wp_notes')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_order_status_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('from_status', 40)->nullable();
            $t->string('to_status', 40)->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->string('actor_name', 120)->nullable();
            $t->string('actor_role', 20)->nullable();
            $t->unsignedBigInteger('actor_technician_id')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('crm_customers', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('first_name')->nullable(), $x->string('last_name')->nullable()]));
        Schema::create('crm_order_items', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('order_id')->nullable()]));
        Schema::create('crm_proformas', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('order_id')->nullable(), $x->string('status', 20)->default('draft'), $x->unsignedBigInteger('invoice_id')->nullable()]));
        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        \Modules\CRM\Models\OrderStatusLog::flushActorCache();
    }

    // ───────────────────────── helpers

    private function technician(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین تست', 'mobile' => '09120000000',
            'status' => 'active', 'percent' => 30,
        ]);
    }

    private function completedOrder(Technician $tech, int $price, array $extra = []): Order
    {
        return Order::forceCreate(array_merge([
            'order_code' => 'IC-'.uniqid(),
            'technician_id' => $tech->id,
            'status' => 'completed',
            'completed_at' => now(),
            'price_customer' => $price,
            'total_invoice' => $price,
        ], $extra));
    }

    // ───────────────────────── ۱) اصلاح — مسیر اصلی

    public function test_correction_supersedes_only_this_invoice_and_recovers_the_commission(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 6_000_000);

        /** @var InvoiceService $service */
        $service = app(InvoiceService::class);
        $old = $service->generateForOrder($order, null, 'idempotent');
        $this->assertSame(-1_800_000, (int) $tech->refresh()->wallet_balance);

        $new = $service->correctInvoice($old, 8_000_000, 'قطعه در فاکتور محاسبه نشده بود', null);

        // فاکتور قدیمی باطل با اشاره‌گر به جدید
        $old->refresh();
        $this->assertNotNull($old->superseded_at);
        $this->assertSame($new->id, (int) $old->superseded_by_id);

        // فاکتور جدید با محاسبه‌گر استاندارد
        $this->assertSame(8_000_000, (int) $new->total_amount);
        $this->assertSame(2_400_000, (int) $new->company_share);
        $this->assertSame(5_600_000, (int) $new->tech_share);
        $this->assertNull($new->superseded_at);

        // کیف‌پول: −۱٫۸ + ۱٫۸ (برگشت) − ۲٫۴ = −۲٫۴
        $this->assertSame(-2_400_000, (int) $tech->refresh()->wallet_balance);
        $this->assertSame(1, WalletTransaction::where('invoice_id', $old->id)
            ->where('note', 'like', '%[reversal#'.$old->id.']%')->count());

        // مبلغ سفارش همگام شد
        $order->refresh();
        $this->assertSame(8_000_000, (int) $order->price_customer);
        $this->assertSame(8_000_000, (int) $order->total_invoice);

        // لاگ بدون تغییر وضعیت با دلیل
        $log = \Modules\CRM\Models\OrderStatusLog::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((string) $log->from_status, (string) $log->to_status);
        $this->assertStringContainsString('قطعه در فاکتور محاسبه نشده بود', (string) $log->note);
        $this->assertStringContainsString($old->invoice_code, (string) $log->note);
    }

    public function test_correcting_an_older_invoice_leaves_the_sibling_and_order_price_alone(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 2_000_000, ['return_type' => 1]);

        // دو فاکتور فعال (سفارش بازگشتیِ جمع‌شونده): اولی اشتباه، دومی درست.
        $first = Invoice::forceCreate([
            'invoice_code' => 'INV-T-1', 'public_token' => str_repeat('a', 40),
            'order_id' => $order->id, 'technician_id' => $tech->id,
            'total_amount' => 5_000_000, 'tech_share' => 3_500_000, 'company_share' => 1_500_000,
            'commission_percent' => 30, 'in_wallet' => true, 'status' => 'issued',
        ]);
        $second = Invoice::forceCreate([
            'invoice_code' => 'INV-T-2', 'public_token' => str_repeat('b', 40),
            'order_id' => $order->id, 'technician_id' => $tech->id,
            'total_amount' => 2_000_000, 'tech_share' => 1_400_000, 'company_share' => 600_000,
            'commission_percent' => 30, 'in_wallet' => true, 'status' => 'issued',
        ]);

        $new = app(InvoiceService::class)->correctInvoice($first, 4_000_000, 'مبلغ کار اول اشتباه ثبت شده بود');

        // فقط فاکتور هدف باطل شد؛ فاکتور کار دوم دست‌نخورده فعال است.
        $this->assertNotNull($first->refresh()->superseded_at);
        $this->assertNull($second->refresh()->superseded_at);
        $this->assertSame(2_000_000, (int) $second->total_amount);

        // چون فاکتور جدیدتری (کار دوم) وجود دارد، مبلغ سفارش دست نمی‌خورد.
        $this->assertSame(2_000_000, (int) $order->refresh()->price_customer);

        // برگشتِ کمیسیونِ فاکتورِ اشتباه ثبت شد.
        $this->assertSame(1, WalletTransaction::where('invoice_id', $first->id)
            ->where('note', 'like', '%[reversal#'.$first->id.']%')->count());
        $this->assertSame(4_000_000, (int) $new->total_amount);
    }

    // ───────────────────────── ۲) گاردها

    public function test_a_paid_invoice_cannot_be_corrected(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 1_000_000);
        $invoice = Invoice::forceCreate([
            'invoice_code' => 'INV-PAID', 'public_token' => str_repeat('c', 40),
            'order_id' => $order->id, 'technician_id' => $tech->id,
            'total_amount' => 1_000_000, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->correctInvoice($invoice, 2_000_000, 'تست روی پرداخت‌شده');
    }

    public function test_the_same_amount_is_rejected_as_a_no_op(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 1_000_000);
        $invoice = Invoice::forceCreate([
            'invoice_code' => 'INV-SAME', 'public_token' => str_repeat('d', 40),
            'order_id' => $order->id, 'technician_id' => $tech->id,
            'total_amount' => 1_000_000, 'status' => 'issued',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->correctInvoice($invoice, 1_000_000, 'بدون تغییر مبلغ');
    }

    // ───────────────────────── ۳) ریدایرکتِ لینک‌های باطل

    public function test_the_old_public_receipt_link_redirects_to_the_replacement(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 6_000_000);

        $service = app(InvoiceService::class);
        $old = $service->generateForOrder($order, null, 'idempotent');
        $new = $service->correctInvoice($old, 7_000_000, 'اصلاح برای تست ریدایرکت');

        $response = app(InvoiceController::class)->publicReceipt($old->refresh()->public_token);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString($new->public_token, $response->getTargetUrl());
    }

    public function test_the_old_payment_link_redirects_to_the_replacements_pay_page(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 6_000_000);

        $service = app(InvoiceService::class);
        $old = $service->generateForOrder($order, null, 'idempotent');
        $new = $service->correctInvoice($old, 7_000_000, 'اصلاح برای تست لینک پرداخت');

        $response = app(PaymentController::class)->pay($old->refresh()->public_token);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString($new->public_token, $response->getTargetUrl());
    }

    // ───────────────────────── ۴) لغو فاکتور

    public function test_cancelling_an_invoice_recovers_the_company_share_automatically(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 6_000_000);

        $invoice = app(InvoiceService::class)->generateForOrder($order, null, 'idempotent');
        $this->assertSame(-1_800_000, (int) $tech->refresh()->wallet_balance);

        app(InvoiceController::class)->cancel($invoice);

        $this->assertSame('cancelled', $invoice->refresh()->status);
        $this->assertSame(0, (int) $tech->refresh()->wallet_balance);
        $this->assertSame(1, WalletTransaction::where('invoice_id', $invoice->id)
            ->where('note', 'like', '%[reversal#'.$invoice->id.']%')->count());
    }

    public function test_a_paid_invoice_cannot_be_cancelled(): void
    {
        $tech = $this->technician();
        $order = $this->completedOrder($tech, 1_000_000);
        $invoice = Invoice::forceCreate([
            'invoice_code' => 'INV-PAID2', 'public_token' => str_repeat('e', 40),
            'order_id' => $order->id, 'technician_id' => $tech->id,
            'total_amount' => 1_000_000, 'company_share' => 300_000,
            'in_wallet' => true, 'status' => 'paid', 'paid_at' => now(),
        ]);

        app(InvoiceController::class)->cancel($invoice);

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(0, WalletTransaction::where('invoice_id', $invoice->id)->count());
    }
}
