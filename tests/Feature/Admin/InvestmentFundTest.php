<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\InvestmentController;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Services\NavasanService;
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

    public function test_a_flagged_user_can_store_a_purchase(): void
    {
        $this->actingAs($this->user(access: true))
            ->post('/admin/investment', [
                'asset' => 'gold_18k',
                'amount' => '100',
                'buy_unit_price' => '7500000',
                'bought_at' => '1405/05/01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = InvestmentAsset::firstOrFail();
        $this->assertSame('gold_18k', $row->asset);
        $this->assertSame(750_000_000, $row->cost());
        $this->assertSame('2026-07-23', $row->bought_at->toDateString());
    }

    // ───────────────────────── ارزش‌گذاری

    /** @return array<string, mixed> دادهٔ view ایندکس، بدون render شدن layout */
    private function indexData(): array
    {
        return app(InvestmentController::class)->index(app(NavasanService::class))->getData();
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
}
