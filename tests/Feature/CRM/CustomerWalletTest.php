<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\CustomerWalletTxType;
use Modules\CRM\Models\Customer;
use Modules\CRM\Services\CustomerWalletService;
use Tests\TestCase;

/**
 * کیف‌پولِ مشتری — یکپارچگیِ موجودی: credit/debit، balance_after، گاردِ منفی.
 */
class CustomerWalletTest extends TestCase
{
    private CustomerWalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('mobile')->unique();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->bigInteger('wallet_balance')->default(0);
            $t->unsignedBigInteger('referred_by')->nullable();
            $t->timestamp('referral_rewarded_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_customer_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->string('type', 30);
            $t->bigInteger('amount');
            $t->bigInteger('balance_after');
            $t->text('note')->nullable();
            $t->json('meta')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        $this->wallet = app(CustomerWalletService::class);
    }

    private function customer(int $balance = 0): Customer
    {
        return Customer::create([
            'mobile' => '0912'.random_int(1000000, 9999999),
            'is_active' => true,
            'wallet_balance' => $balance,
        ]);
    }

    public function test_credit_increases_balance_and_records_balance_after(): void
    {
        $c = $this->customer(0);

        $tx = $this->wallet->credit($c, CustomerWalletTxType::Topup, 500000);

        $this->assertSame(500000, (int) $c->fresh()->wallet_balance);
        $this->assertSame(500000, (int) $tx->amount);
        $this->assertSame(500000, (int) $tx->balance_after);
    }

    public function test_debit_decreases_balance(): void
    {
        $c = $this->customer(500000);

        $this->wallet->debit($c, CustomerWalletTxType::Payment, 200000);

        $this->assertSame(300000, (int) $c->fresh()->wallet_balance);
    }

    public function test_referral_reward_then_full_withdrawal_is_allowed(): void
    {
        // معیارِ پذیرش: کلِ موجودی (شاملِ پاداش) قابلِ برداشت است.
        $c = $this->customer(0);
        $this->wallet->credit($c, CustomerWalletTxType::ReferralReward, 200000);
        $this->wallet->debit($c, CustomerWalletTxType::Withdrawal, 200000);

        $this->assertSame(0, (int) $c->fresh()->wallet_balance);
    }

    public function test_debit_beyond_balance_is_rejected_and_balance_unchanged(): void
    {
        $c = $this->customer(100000);

        try {
            $this->wallet->debit($c, CustomerWalletTxType::Withdrawal, 150000);
            $this->fail('باید به‌خاطرِ کسریِ موجودی رد می‌شد.');
        } catch (ValidationException $e) {
            // انتظار می‌رود.
        }

        $this->assertSame(100000, (int) $c->fresh()->wallet_balance);
        $this->assertSame(0, $c->walletTransactions()->count(), 'تراکنشِ ناموفق نباید ثبت شود.');
    }

    public function test_referral_code_is_last_six_digits_of_mobile(): void
    {
        $c = Customer::create(['mobile' => '09121234567', 'is_active' => true, 'wallet_balance' => 0]);

        $this->assertSame('234567', $c->referralCode());
    }

    public function test_find_by_referral_code_returns_single_active_match(): void
    {
        $ref = Customer::create(['mobile' => '09121234567', 'is_active' => true]);

        // کدِ ۶رقمی و کلِ موبایل هر دو باید به همین معرف برسند.
        $this->assertSame($ref->id, Customer::findByReferralCode('234567')?->id);
        $this->assertSame($ref->id, Customer::findByReferralCode('09121234567')?->id);

        // خودمعرفی حذف می‌شود.
        $this->assertNull(Customer::findByReferralCode('234567', '09121234567'));
    }

    public function test_find_by_referral_code_is_null_on_ambiguous_match(): void
    {
        Customer::create(['mobile' => '09121234567', 'is_active' => true]);
        Customer::create(['mobile' => '09351234567', 'is_active' => true]); // همان ۶ رقمِ آخر

        $this->assertNull(Customer::findByReferralCode('234567'));
    }

    public function test_reject_refund_restores_reserved_amount(): void
    {
        // شبیه‌سازیِ رزرو (برداشت) و سپس بازگشت در ردِ ادمین.
        $c = $this->customer(300000);
        $this->wallet->debit($c, CustomerWalletTxType::Withdrawal, 300000);
        $this->assertSame(0, (int) $c->fresh()->wallet_balance);

        $this->wallet->credit($c, CustomerWalletTxType::WithdrawalRefund, 300000);
        $this->assertSame(300000, (int) $c->fresh()->wallet_balance);
    }
}
