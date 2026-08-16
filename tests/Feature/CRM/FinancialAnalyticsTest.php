<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Accounting\FinancialReportController;
use Modules\CRM\Models\Expense;
use Modules\CRM\Models\WalletTransaction;
use Tests\TestCase;

/**
 * «گزارش حسابداری» — سه سری:
 *   شارژ کیف پول (سود ناخالص)، هزینه‌ها، و سود خالص = شارژ − هزینه
 *   (تعریفِ مصوب). «مبلغ کل فاکتورها» به درخواستِ مدیر حذف شد.
 * دانه‌بندی و بازه شمسی‌اند. کنترلر مستقیم صدا زده می‌شود.
 */
class FinancialAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_tech_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->string('type', 30);
            $t->bigInteger('amount')->default(0);
            $t->bigInteger('balance_after')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_expenses', function ($t) {
            $t->id();
            $t->timestamp('paid_at')->nullable();
            $t->bigInteger('amount')->default(0);
            $t->unsignedBigInteger('category_id')->nullable();
            $t->unsignedBigInteger('payment_account_id')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }

    /** @return array<string, mixed> */
    private function report(array $query = []): array
    {
        $request = Request::create('/admin/crm/costs/analytics', 'GET', $query);

        return app(FinancialReportController::class)->index($request)->getData();
    }

    public function test_the_three_series_are_summed_and_net_is_wallet_minus_expenses(): void
    {
        WalletTransaction::forceCreate(['technician_id' => 1, 'type' => 'wallet_charge', 'amount' => 700_000, 'created_at' => now()->subDays(2)]);
        WalletTransaction::forceCreate(['technician_id' => 1, 'type' => 'wallet_charge', 'amount' => 300_000, 'created_at' => now()->subDay()]);
        // کمیسیون شارژ نیست — نباید شمرده شود.
        WalletTransaction::forceCreate(['technician_id' => 1, 'type' => 'commission', 'amount' => -500_000, 'created_at' => now()->subDay()]);

        Expense::forceCreate(['paid_at' => now()->subDay(), 'amount' => 250_000]);

        $data = $this->report(['granularity' => 'day']);

        $this->assertArrayNotHasKey('invoices', $data['totals']);
        $this->assertSame(1_000_000, $data['totals']['wallet']);
        $this->assertSame(250_000, $data['totals']['expenses']);
        $this->assertSame(750_000, $data['totals']['net']);

        // در سطلِ دیروز: net = 300٬000 − 250٬000
        $yesterday = collect($data['buckets'])
            ->firstWhere('label', \Morilog\Jalali\Jalalian::fromCarbon(now()->subDay())->format('m/d'));
        $this->assertSame(50_000, $yesterday['net']);
    }

    public function test_month_buckets_follow_the_jalali_calendar(): void
    {
        // 2026-07-22 = ۱۴۰۵/۰۴/۳۱ (تیر)؛ 2026-07-23 = ۱۴۰۵/۰۵/۰۱ (مرداد).
        WalletTransaction::forceCreate(['technician_id' => 1, 'type' => 'wallet_charge', 'amount' => 100, 'created_at' => '2026-07-22 10:00:00']);
        WalletTransaction::forceCreate(['technician_id' => 1, 'type' => 'wallet_charge', 'amount' => 200, 'created_at' => '2026-07-23 10:00:00']);

        $data = $this->report(['granularity' => 'month', 'from' => '1405/04/25', 'to' => '1405/05/05']);

        $labels = array_column($data['buckets'], 'wallet', 'label');
        $this->assertSame(100, $labels['تیر 1405']);
        $this->assertSame(200, $labels['مرداد 1405']);
    }

    public function test_quarter_and_year_buckets_are_available(): void
    {
        Expense::forceCreate(['paid_at' => '2026-08-10 10:00:00', 'amount' => 500]); // مرداد → تابستان

        $quarter = $this->report(['granularity' => 'quarter', 'from' => '1405/05/01', 'to' => '1405/05/25']);
        $this->assertSame('تابستان 1405', $quarter['buckets'][0]['label']);
        $this->assertSame(500, $quarter['buckets'][0]['expenses']);

        $year = $this->report(['granularity' => 'year', 'from' => '1405/01/01', 'to' => '1405/05/25']);
        $this->assertSame('1405', $year['buckets'][0]['label']);
        $this->assertSame(500, $year['buckets'][0]['expenses']);
    }

    public function test_a_custom_jalali_range_limits_the_data(): void
    {
        Expense::forceCreate(['paid_at' => '2026-08-10 10:00:00', 'amount' => 100]);
        Expense::forceCreate(['paid_at' => '2026-06-01 10:00:00', 'amount' => 900]);

        $data = $this->report(['granularity' => 'day', 'from' => '1405/05/15', 'to' => '1405/05/25']);

        $this->assertSame(100, $data['totals']['expenses']);
    }
}
