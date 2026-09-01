<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\InvoiceService;
use Tests\TestCase;

/**
 * قراردادِ علامتِ مبالغ کیف‌پول — اپ علامت را از خودِ amount می‌گیرد:
 *
 *   ۱) صدور فاکتور نهایی، تراکنش کمیسیون با amount = «منهای سهم شرکت»
 *      می‌سازد و wallet_balance (همان balance در API) بلافاصله و در همان
 *      DB transaction کم می‌شود.
 *   ۲) هر کسر (جریمه/پرداختی/واریز به شرکت) علامت منفی و هر واریز
 *      (شارژ/پاداش/کمیسیونِ WP قدیمی) علامت مثبت دارد.
 */
class WalletSignContractTest extends TestCase
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
            $t->string('mobile')->nullable();
            $t->unsignedTinyInteger('percent')->default(0);
            $t->unsignedTinyInteger('tech_per_of_all')->default(0);
            $t->string('type_of_calc_tech', 20)->nullable();
            $t->string('status', 20)->default('active');
            $t->bigInteger('wallet_balance')->default(0);
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
            $t->string('status', 30)->default('completed');
            $t->timestamp('status_changed_at')->nullable();
            $t->bigInteger('price_customer')->nullable();
            $t->bigInteger('cost_price')->nullable();
            $t->bigInteger('total_invoice')->nullable();
            $t->bigInteger('final_price')->nullable();
            $t->boolean('save_as_draft')->default(false);
            $t->boolean('is_lead')->default(false);
            $t->timestamp('completed_at')->nullable();
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
            $t->text('description')->nullable();
            $t->text('items_snapshot')->nullable();
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

        Schema::create('crm_proformas', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamps();
        });
    }

    public function test_issuing_an_invoice_records_a_negative_commission_and_updates_the_balance(): void
    {
        $tech = Technician::forceCreate([
            'first_name' => 'تکنسین', 'status' => 'active',
            'percent' => 30, 'type_of_calc_tech' => '', 'wallet_balance' => 0,
        ]);
        $order = Order::forceCreate([
            'order_code' => 'ORD-SIGN-1', 'technician_id' => $tech->id,
            'status' => 'completed', 'price_customer' => 2_000_000,
            'cost_price' => 0, 'completed_at' => now(),
        ]);

        $invoice = app(InvoiceService::class)->generateForOrder($order->fresh(), null, true);

        $this->assertNotNull($invoice);
        $this->assertGreaterThan(0, (int) $invoice->company_share);

        $tx = WalletTransaction::where('invoice_id', $invoice->id)->firstOrFail();

        // کسر = amount منفی، دقیقاً به اندازهٔ سهم شرکت.
        $this->assertSame(WalletTxType::Commission->value, $tx->type?->value ?? $tx->type);
        $this->assertSame(-1 * (int) $invoice->company_share, (int) $tx->amount);

        // balance بلافاصله بعد از صدور به‌روز است — همان چیزی که API می‌دهد.
        $this->assertSame(-1 * (int) $invoice->company_share, (int) $tech->fresh()->wallet_balance);
        $this->assertSame((int) $tech->fresh()->wallet_balance, (int) $tx->balance_after);
    }

    /** قرارداد علامت هر نوع تراکنش — اپ علامت را از amount می‌گیرد. */
    public function test_deduction_types_are_negative_and_deposit_types_positive(): void
    {
        foreach ([WalletTxType::Penalty, WalletTxType::Payout, WalletTxType::Credit] as $type) {
            $this->assertSame(-1, $type->sign(), $type->value);
        }
        foreach ([WalletTxType::Reward, WalletTxType::WalletCharge] as $type) {
            $this->assertSame(+1, $type->sign(), $type->value);
        }
    }
}
