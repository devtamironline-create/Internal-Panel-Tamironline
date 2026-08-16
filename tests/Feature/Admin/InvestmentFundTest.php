<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\InvestmentController;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * صندوق سرمایه.
 *
 * سه ادعای قفل‌شده:
 *   ۱) مخفی‌بودن: بدون فلگ investment_access حتی سوپر-ادمین هم 404 می‌گیرد —
 *      نه 403 — و فلگ فقط با کامند investment:access روشن/خاموش می‌شود.
 *   ۲) ارزش‌گذاری: مقدار × قیمتِ روزِ نوسان، و سود/زیان نسبت به خرید.
 *   ۳) خرابیِ نوسان صفحه را نمی‌خواباند — ارزشِ روز «—» می‌شود ولی
 *      لیست و ثبت خرید سالم می‌مانند.
 */
class InvestmentFundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);

        Schema::table('users', function ($t) {
            foreach (['first_name', 'last_name', 'mobile'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $t->string($column)->nullable();
                }
            }
        });

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_11_120000_create_investment_fund.php',
            '--force' => true,
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_16_100000_add_source_and_snapshots_to_investment_fund.php',
            '--force' => true,
        ]);

        // میدل‌ویر can/سایدبار در مسیرهای admin به جدول‌های Spatie نیاز دارد.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php',
            '--force' => true,
        ]);

        config(['investment.navasan.api_key' => 'test-key']);
    }

    private function user(bool $access): User
    {
        static $seq = 0;
        $seq++;

        return User::forceCreate([
            'first_name' => 'کاربر',
            'email' => "user{$seq}@example.test",
            'mobile' => '0912000000'.$seq,
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
            'investment_access' => $access,
        ]);
    }

    // ───────────────────────── مخفی‌بودن

    public function test_the_section_is_a_404_for_anyone_without_the_flag(): void
    {
        $admin = $this->user(access: false);
        \Spatie\Permission\Models\Permission::findOrCreate('manage-permissions', 'web');
        $admin->givePermissionTo('manage-permissions');   // سوپر-ادمین هم نباید ببیند

        $this->actingAs($admin)->get('/admin/investment')->assertNotFound();
        $this->actingAs($admin)->post('/admin/investment', [])->assertNotFound();
    }

    public function test_the_command_is_the_only_switch(): void
    {
        $user = $this->user(access: false);

        Artisan::call('investment:access', ['action' => 'grant', 'user' => $user->mobile]);
        $this->assertTrue($user->fresh()->investment_access);

        Artisan::call('investment:access', ['action' => 'revoke', 'user' => $user->email]);
        $this->assertFalse($user->fresh()->investment_access);

        $this->assertSame(1, Artisan::call('investment:access', ['action' => 'grant', 'user' => 'missing@nowhere.test']));
    }

    /** قیمتِ خرید دیگر دستی نیست — از قیمتِ لحظه‌ایِ نوسان برداشته می‌شود. */
    public function test_a_flagged_user_stores_a_purchase_priced_by_navasan(): void
    {
        Http::fake(['*' => Http::response(['18ayar' => ['value' => '7500000']], 200)]);

        $this->actingAs($this->user(access: true))
            ->post('/admin/investment', [
                'asset' => 'gold_18k',
                'amount' => '100',
                'source' => 'tamir',
                'bought_at' => '1405/05/01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = InvestmentAsset::firstOrFail();
        $this->assertSame('gold_18k', $row->asset);
        $this->assertSame(7_500_000, (int) $row->buy_unit_price);
        $this->assertSame(750_000_000, $row->cost());
        $this->assertSame('tamir', $row->source);
        $this->assertSame('2026-07-23', $row->bought_at->toDateString());
    }

    public function test_source_is_required_for_a_purchase(): void
    {
        Http::fake(['*' => Http::response(['18ayar' => ['value' => '7500000']], 200)]);

        $this->actingAs($this->user(access: true))
            ->post('/admin/investment', ['asset' => 'gold_18k', 'amount' => '10'])
            ->assertSessionHasErrors('source');

        $this->assertSame(0, InvestmentAsset::count());
    }

    /** بدونِ قیمتِ روز، ثبتِ خرید متوقف می‌شود — ردیفِ بی‌مبلغ ممنوع. */
    public function test_a_purchase_is_rejected_when_navasan_is_down(): void
    {
        Http::fake(['*' => Http::response('upstream error', 502)]);

        $this->actingAs($this->user(access: true))
            ->post('/admin/investment', ['asset' => 'gold_18k', 'amount' => '10', 'source' => 'ganje'])
            ->assertSessionHasErrors('asset');

        $this->assertSame(0, InvestmentAsset::count());
    }

    // ───────────────────────── ارزش‌گذاری

    /** @return array<string, mixed> دادهٔ view ایندکس، بدون render شدن layout */
    private function indexData(array $query = []): array
    {
        $request = \Illuminate\Http\Request::create('/admin/investment', 'GET', $query);

        return app(InvestmentController::class)
            ->index($request, app(\App\Services\InvestmentPortfolio::class))
            ->getData();
    }

    /** نوسان قیمتِ سکه را به «هزار تومان» می‌دهد — ضریبِ config باید اعمال شود. */
    public function test_coin_prices_are_scaled_by_the_configured_multiplier(): void
    {
        Http::fake(['*' => Http::response(['sekkeh' => ['value' => '189500']], 200)]);

        InvestmentAsset::create(['asset' => 'sekkeh_emami', 'amount' => 1, 'buy_unit_price' => 150_000_000]);

        $coin = collect($this->indexData()['positions'])->firstWhere('asset', 'sekkeh_emami');

        $this->assertSame(189_500_000, $coin['unit_price']);
        $this->assertSame(189_500_000 - 150_000_000, $coin['profit']);
    }

    public function test_positions_are_valued_with_live_navasan_prices(): void
    {
        Http::fake(['*' => Http::response([
            '18ayar' => ['value' => '8000000'],
            'usdt' => ['value' => '61000'],
        ], 200)]);

        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 100, 'buy_unit_price' => 7_500_000]);
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 50, 'buy_unit_price' => 7_000_000]);
        InvestmentAsset::create(['asset' => 'usdt', 'amount' => 1000, 'buy_unit_price' => 60_000]);

        $data = $this->indexData();
        $gold = collect($data['positions'])->firstWhere('asset', 'gold_18k');

        $this->assertSame(150.0, $gold['amount']);
        $this->assertSame(1_100_000_000, $gold['cost']);
        $this->assertSame(150 * 8_000_000, $gold['value']);
        $this->assertSame(100_000_000, $gold['profit']);

        $this->assertSame(1_100_000_000 + 60_000_000, $data['totalCost']);
        $this->assertSame(1_200_000_000 + 61_000_000, $data['totalValue']);
    }

    public function test_a_navasan_outage_does_not_break_the_page(): void
    {
        Http::fake(['*' => Http::response('upstream error', 502)]);

        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 10, 'buy_unit_price' => 7_500_000]);

        $data = $this->indexData();
        $gold = collect($data['positions'])->firstWhere('asset', 'gold_18k');

        $this->assertNull($gold['value']);
        $this->assertSame(75_000_000, $data['totalCost']);
        $this->assertNull($data['totalValue']);
    }

    // ───────────────────────── نمودار برداشت از هر منبع

    public function test_withdrawals_are_grouped_by_jalali_month_and_source(): void
    {
        Http::fake(['*' => Http::response(['18ayar' => ['value' => '8000000']], 200)]);

        // ۱۴۰۵/۰۵ → مرداد؛ دو منبع + یک ردیف قدیمیِ بدونِ منبع.
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 10, 'buy_unit_price' => 1_000_000, 'bought_at' => '2026-07-23', 'source' => 'tamir']);
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 5, 'buy_unit_price' => 1_000_000, 'bought_at' => '2026-07-30', 'source' => 'ganje']);
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 2, 'buy_unit_price' => 1_000_000, 'bought_at' => '2026-07-24']);

        $data = $this->indexData(['year' => '1405']);

        $mordad = collect($data['withdrawMonths'])->firstWhere('month', 5);
        $this->assertSame(10_000_000, $mordad['tamir']);
        $this->assertSame(5_000_000, $mordad['ganje']);
        $this->assertSame(2_000_000, $mordad['unknown']);
        $this->assertSame(10_000_000, $data['sourceTotals']['tamir']);
        $this->assertSame(5_000_000, $data['sourceTotals']['ganje']);
        $this->assertContains(1405, $data['withdrawYears']);
    }

    // ───────────────────────── snapshot روزانهٔ ارزش

    public function test_the_snapshot_command_writes_one_row_per_day(): void
    {
        Http::fake(['*' => Http::response(['18ayar' => ['value' => '8000000']], 200)]);
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 10, 'buy_unit_price' => 7_000_000]);

        $this->assertSame(0, Artisan::call('investment:snapshot'));
        $this->assertSame(0, Artisan::call('investment:snapshot')); // idempotent

        $this->assertSame(1, \App\Models\InvestmentSnapshot::count());
        $snap = \App\Models\InvestmentSnapshot::firstOrFail();
        $this->assertSame(80_000_000, (int) $snap->total_value);
        $this->assertSame(70_000_000, (int) $snap->total_cost);
        $this->assertSame(80_000_000, (int) $snap->breakdown['gold_18k']['value']);
    }

    /** قطعیِ نوسان نباید صفرِ دروغین در تاریخچه بنشاند. */
    public function test_the_snapshot_command_skips_when_prices_are_unavailable(): void
    {
        Http::fake(['*' => Http::response('upstream error', 502)]);
        InvestmentAsset::create(['asset' => 'gold_18k', 'amount' => 10, 'buy_unit_price' => 7_000_000]);

        $this->assertSame(1, Artisan::call('investment:snapshot'));
        $this->assertSame(0, \App\Models\InvestmentSnapshot::count());
    }

    public function test_the_trend_chart_serves_day_month_and_year_views(): void
    {
        Http::fake(['*' => Http::response(['18ayar' => ['value' => '8000000']], 200)]);

        // سالِ قبل — تا با snapshotِ امروز (که بازدیدِ صفحه می‌سازد) هم‌ماه نشود.
        \App\Models\InvestmentSnapshot::create(['snap_date' => '2025-07-01', 'total_value' => 100, 'total_cost' => 90]);
        \App\Models\InvestmentSnapshot::create(['snap_date' => '2025-07-02', 'total_value' => 110, 'total_cost' => 90]);
        \App\Models\InvestmentSnapshot::create(['snap_date' => '2025-08-10', 'total_value' => 130, 'total_cost' => 90]);

        $day = $this->indexData(['view' => 'day']);
        // ردیفِ امروز هم توسطِ بازدیدِ صفحه ساخته می‌شود (سبد خالی → ارزش ۰).
        $this->assertGreaterThanOrEqual(4, count($day['trendPoints']));

        $month = $this->indexData(['view' => 'month']);
        $monthValues = collect($month['trendPoints'])->pluck('value');
        $this->assertContains(110, $monthValues); // آخرین snapshot ماهِ اول
        $this->assertContains(130, $monthValues); // ماهِ دوم

        $year = $this->indexData(['view' => 'year']);
        $yearValues = collect($year['trendPoints'])->pluck('value');
        $this->assertContains(130, $yearValues); // آخرین snapshot سال ۱۴۰۴
        $this->assertGreaterThanOrEqual(2, count($year['trendPoints']));
    }
}
