<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Staff\Models\StaffContract;
use Modules\Staff\Support\ContractSettings;
use Tests\TestCase;

/**
 * صدور و حذفِ قراردادِ پرسنل.
 *
 * سه ادعا این‌جا قفل می‌شوند:
 *   ۱) تاریخِ شمسیِ فرم درست به میلادیِ دیتابیس تبدیل می‌شود،
 *   ۲) بندهای ثابتِ قرارداد از فرم قابل تغییر نیستند،
 *   ۳) حذف بدونِ کدِ پیامکی انجام نمی‌شود و فایل‌ها را هم می‌برد.
 */
class StaffContractAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);

        // مهاجرتِ پایهٔ users بعضی از این ستون‌ها را دارد و بعضی را نه؛
        // فقط نبوده‌ها اضافه می‌شوند.
        Schema::table('users', function ($t) {
            foreach ([
                'first_name' => fn () => $t->string('first_name')->nullable(),
                'last_name' => fn () => $t->string('last_name')->nullable(),
                'mobile' => fn () => $t->string('mobile')->nullable(),
                'national_code' => fn () => $t->string('national_code')->nullable(),
                'address' => fn () => $t->text('address')->nullable(),
                'is_staff' => fn () => $t->boolean('is_staff')->default(false),
            ] as $column => $add) {
                if (! Schema::hasColumn('users', $column)) {
                    $add();
                }
            }
        });

        // جدول‌های Spatie لازم‌اند: میدل‌ویرِ `can:` و خودِ Gate از آن‌ها
        // می‌خوانند، حتی وقتی نتیجه از قبل معلوم است.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php',
            '--force' => true,
        ]);

        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_28_100000_create_staff_contracts_table.php',
            '--force' => true,
        ]);

        Storage::fake('public');

        // پیامک واقعی فرستاده نمی‌شود، ولی مسیرِ کد باید همان مسیرِ واقعی
        // بماند: بدونِ کلید و بدونِ پاسخِ موفق، `OTPService` کد را از کش پاک
        // می‌کند و تست چیزی برای تأیید ندارد.
        config(['sms.kavenegar.api_key' => 'test-key', 'sms.proxy.enabled' => false]);
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['return' => ['status' => 200]], 200),
        ]);
    }

    private ?User $adminUser = null;

    private ?User $staffUser = null;

    /** یک‌بار ساخته می‌شود؛ فراخوانیِ دوباره همان کاربر را برمی‌گرداند. */
    private function admin(): User
    {
        if ($this->adminUser) {
            return $this->adminUser;
        }

        $admin = User::forceCreate([
            'first_name' => 'مدیر',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'mobile' => '09123456789',
            'mobile_verified_at' => now(),   // مسیرهای ادمین پشتِ verified.mobile هستند
            'is_staff' => true,
        ]);

        // موضوعِ این تست نقش‌ها نیست؛ هر دو مجوزِ لازم مستقیم داده می‌شوند.
        foreach (['manage-staff-contracts', 'manage-permissions'] as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'web');
        }
        $admin->givePermissionTo(['manage-staff-contracts', 'manage-permissions']);

        return $this->adminUser = $admin;
    }

    private function staffMember(): User
    {
        return $this->staffUser ??= User::forceCreate([
            'first_name' => 'کارمند',
            'last_name' => 'نمونه',
            'email' => 'staff@example.test',
            'password' => bcrypt('secret'),
            'mobile' => '09121112233',
            'mobile_verified_at' => now(),
            'is_staff' => true,
        ]);
    }

    private function issue(array $overrides = [])
    {
        $staff = $this->staffMember();

        return $this->actingAs($this->admin())->post(route('admin.staff-contracts.store'), array_merge([
            'user_ids' => [$staff->id],
            'contract_date' => '1405/05/11',
            'start_date' => '1405/05/11',
            'service_description' => 'نظارت و کنترل کیفیت',
            'monthly_wage' => 22000000,
            'promissory_amount' => 100000000,
        ], $overrides));
    }

    public function test_a_jalali_date_is_stored_as_gregorian(): void
    {
        $this->issue()->assertRedirect();

        $contract = StaffContract::firstOrFail();

        $this->assertSame('2026-08-02', $contract->contract_date->toDateString());
        $this->assertSame('2026-08-02', $contract->start_date->toDateString());
        $this->assertNull($contract->end_date);
    }

    public function test_an_invalid_date_is_rejected_instead_of_stored_as_null(): void
    {
        $this->issue(['start_date' => 'دیروز'])->assertSessionHasErrors('start_date');

        $this->assertSame(0, StaffContract::count());
    }

    public function test_an_end_date_before_the_start_is_rejected(): void
    {
        $this->issue(['end_date' => '1405/05/01'])->assertSessionHasErrors('end_date');

        $this->assertSame(0, StaffContract::count());
    }

    /**
     * قلبِ درخواست: بندهای ثابت نباید از فرم قابلِ تعیین باشند. اگر کسی
     * دستی مقدار بفرستد، باید نادیده گرفته شود — نه اینکه در سند بنشیند.
     */
    public function test_fixed_clauses_ignore_anything_posted_from_the_form(): void
    {
        $this->issue([
            'non_solicit_months' => 1,
            'payment_grace_days' => 99,
            'confidentiality_years' => 1,
            'holiday_hourly_rate' => 1,
        ])->assertRedirect();

        $contract = StaffContract::firstOrFail();

        $this->assertSame(24, $contract->non_solicit_months);
        $this->assertSame(3, $contract->payment_grace_days);
        $this->assertSame(5, $contract->confidentiality_years);
        $this->assertNull($contract->holiday_hourly_rate);
    }

    public function test_fixed_clauses_follow_the_organisation_setting(): void
    {
        \App\Models\Setting::set('contract_non_solicit_months', '36');
        \App\Models\Setting::set('contract_holiday_hourly_rate', '150000');

        $this->issue()->assertRedirect();

        $contract = StaffContract::firstOrFail();

        $this->assertSame(36, $contract->non_solicit_months);
        $this->assertSame(150000, $contract->holiday_hourly_rate);
        $this->assertSame('36', ContractSettings::get('contract_non_solicit_months'));
    }

    public function test_the_variable_fields_are_still_per_person(): void
    {
        $this->issue()->assertRedirect();

        $contract = StaffContract::firstOrFail();

        $this->assertSame(22000000, $contract->monthly_wage);
        $this->assertSame(100000000, $contract->promissory_amount);
    }

    // ─── حذف ─────────────────────────────────────────────────────

    private function contractWithFiles(): StaffContract
    {
        $this->issue();
        $contract = StaffContract::firstOrFail();

        Storage::disk('public')->put('contracts/national-card.jpg', 'x');
        Storage::disk('public')->put('contracts/signature.png', 'x');
        $contract->forceFill([
            'doc_national_card_front' => 'contracts/national-card.jpg',
            'signature_path' => 'contracts/signature.png',
        ])->save();

        return $contract;
    }

    public function test_deleting_without_a_code_is_refused(): void
    {
        $contract = $this->contractWithFiles();

        $this->actingAs($this->admin())
            ->delete(route('admin.staff-contracts.destroy', $contract), ['confirmation_code' => '000000'])
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, StaffContract::count());
    }

    public function test_a_code_issued_for_one_contract_cannot_delete_another(): void
    {
        $first = $this->contractWithFiles();
        $this->issue();
        $second = StaffContract::where('id', '!=', $first->id)->firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.staff-contracts.delete-code', $first));

        // کدِ درست، ولی برای قراردادِ دیگری صادر شده.
        $code = Cache::get('otp_09123456789')['code'] ?? null;
        $this->assertNotNull($code, 'کد تأیید ساخته نشد.');

        $this->actingAs($admin)
            ->delete(route('admin.staff-contracts.destroy', $second), ['confirmation_code' => $code])
            ->assertSessionHasErrors('delete');

        $this->assertSame(2, StaffContract::count());
    }

    public function test_a_valid_code_deletes_the_contract_and_its_files(): void
    {
        $contract = $this->contractWithFiles();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.staff-contracts.delete-code', $contract));
        $code = Cache::get('otp_09123456789')['code'] ?? null;

        $this->actingAs($admin)
            ->delete(route('admin.staff-contracts.destroy', $contract), ['confirmation_code' => $code])
            ->assertRedirect(route('admin.staff-contracts.index'));

        $this->assertSame(0, StaffContract::count());
        Storage::disk('public')->assertMissing('contracts/national-card.jpg');
        Storage::disk('public')->assertMissing('contracts/signature.png');
    }

    public function test_the_same_code_cannot_be_replayed(): void
    {
        $contract = $this->contractWithFiles();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.staff-contracts.delete-code', $contract));
        $code = Cache::get('otp_09123456789')['code'] ?? null;

        $this->actingAs($admin)->delete(route('admin.staff-contracts.destroy', $contract), ['confirmation_code' => $code]);

        $this->issue();
        $another = StaffContract::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.staff-contracts.destroy', $another), ['confirmation_code' => $code])
            ->assertSessionHasErrors('delete');

        $this->assertSame(1, StaffContract::count());
    }
}
