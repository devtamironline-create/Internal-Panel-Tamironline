<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\CRM\Models\Expense;
use Modules\CRM\Models\OwnerWithdrawal;
use Modules\CRM\Models\PaymentAccount;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * برداشت سرمایه (مالک/شرکا) — نوعِ دومِ خروج وجه.
 *
 * معیارهای پذیرش: ثبت/ویرایش/حذفِ نرم، جدایی کامل از crm_expenses (هزینه/سود
 * دست‌نخورده)، و گیتِ مجوزِ واحد.
 */
class OwnerWithdrawalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'Modules/CRM/Database/Migrations/2026_06_11_120000_create_crm_expenses_tables.php',
            'Modules/CRM/Database/Migrations/2026_09_15_100000_create_crm_owner_withdrawals_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        Permission::firstOrCreate(['name' => 'manage-owner-withdrawals', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_user_without_permission_cannot_access_withdrawals(): void
    {
        $this->actingAs($this->user('09120000401'))
            ->get(route('crm.withdrawals.index'))
            ->assertForbidden();
    }

    public function test_store_creates_a_withdrawal_linked_to_an_account(): void
    {
        $manager = $this->manager('09120000402');
        $account = $this->account();

        $this->actingAs($manager)
            ->post(route('crm.withdrawals.store'), [
                'paid_date' => Jalalian::now()->format('Y/m/d'),
                'paid_time' => '10:30',
                'amount' => '200,000,000',
                'payment_account_id' => $account->id,
                'payee' => 'علی اسماعیلی',
                'description' => 'برداشت سود شهریور',
            ])
            ->assertRedirect(route('crm.withdrawals.index'));

        $w = OwnerWithdrawal::first();
        $this->assertNotNull($w);
        $this->assertSame(200_000_000, $w->amount);
        $this->assertSame($account->id, $w->payment_account_id);
        $this->assertSame($manager->id, $w->created_by);
    }

    public function test_a_withdrawal_is_never_recorded_as_an_expense(): void
    {
        $manager = $this->manager('09120000403');
        $account = $this->account();

        $this->actingAs($manager)->post(route('crm.withdrawals.store'), [
            'paid_date' => Jalalian::now()->format('Y/m/d'),
            'paid_time' => '10:30',
            'amount' => '200,000,000',
            'payment_account_id' => $account->id,
        ])->assertRedirect();

        // معیارِ پذیرشِ ۴/۵/۱۱: هیچ ردیفِ هزینه‌ای ساخته نمی‌شود و جمعِ هزینه صفر می‌ماند.
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, (int) Expense::sum('amount'));
        $this->assertSame(1, OwnerWithdrawal::count());
    }

    public function test_index_totals_and_monthly_kpi_are_summed(): void
    {
        $account = $this->account();
        OwnerWithdrawal::create($this->row($account, 200_000_000));
        OwnerWithdrawal::create($this->row($account, 170_000_000));

        // دادهٔ ویو را مستقیم می‌سنجیم تا نیازی به رندرِ کاملِ layout (و جدولِ
        // settings و ...) نباشد.
        $request = \Illuminate\Http\Request::create(route('crm.withdrawals.index'), 'GET');
        $data = app(\Modules\CRM\Http\Controllers\Accounting\OwnerWithdrawalController::class)
            ->index($request)->getData();

        $this->assertSame(370_000_000, $data['totalAmount']);
        $this->assertSame(370_000_000, $data['monthTotal']);
        $this->assertSame(2, $data['totalCount']);
    }

    public function test_delete_is_soft_and_reversible(): void
    {
        $manager = $this->manager('09120000405');
        $account = $this->account();
        $w = OwnerWithdrawal::create($this->row($account, 200_000_000));

        $this->actingAs($manager)
            ->delete(route('crm.withdrawals.destroy', $w))
            ->assertRedirect(route('crm.withdrawals.index'));

        $this->assertSame(0, OwnerWithdrawal::count());
        $this->assertSame(1, OwnerWithdrawal::withTrashed()->count());
    }

    public function test_edit_updates_the_amount(): void
    {
        $manager = $this->manager('09120000406');
        $account = $this->account();
        $w = OwnerWithdrawal::create($this->row($account, 200_000_000));

        $this->actingAs($manager)->put(route('crm.withdrawals.update', $w), [
            'paid_date' => Jalalian::now()->format('Y/m/d'),
            'paid_time' => '09:00',
            'amount' => '150,000,000',
            'payment_account_id' => $account->id,
        ])->assertRedirect(route('crm.withdrawals.index'));

        $this->assertSame(150_000_000, $w->fresh()->amount);
    }

    // ── helpers ──────────────────────────────────────────────────

    private function user(string $mobile): User
    {
        return User::forceCreate([
            'first_name' => 'کاربر', 'last_name' => 'تست',
            'mobile' => $mobile, 'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
    }

    private function manager(string $mobile): User
    {
        $u = $this->user($mobile);
        $u->givePermissionTo('manage-owner-withdrawals');

        return $u;
    }

    private function account(): PaymentAccount
    {
        return PaymentAccount::create(['title' => 'بانک شرکت', 'type' => 'company_bank', 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function row(PaymentAccount $account, int $amount): array
    {
        return [
            'withdrawn_at' => now(),
            'amount' => $amount,
            'payment_account_id' => $account->id,
        ];
    }
}
