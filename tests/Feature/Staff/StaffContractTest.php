<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Modules\Staff\Models\StaffContract;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * قرارداد کارمندان: صدور گروهی، تکمیل توسط کارمند (مدارک/امضا/ویدیو)،
 * بررسی و تأیید ادمین، تولید PDF و کنترل دسترسی.
 */
class StaffContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'database/migrations/2026_01_04_100000_create_chat_tables.php',
            'database/migrations/2026_02_03_140854_create_settings_table.php',
            'database/migrations/2026_07_28_100000_create_staff_contracts_table.php',
            'database/migrations/2026_08_27_000001_add_version_to_staff_contracts.php',
            'database/migrations/2026_08_27_000002_add_v2_salary_to_staff_contracts.php',
            'database/migrations/2026_09_06_120000_add_v2_marriage_allowance_to_staff_contracts.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        Storage::fake('public');
        Permission::firstOrCreate(['name' => 'manage-staff-contracts', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_issues_one_contract_per_checked_employee(): void
    {
        $admin = $this->admin();
        $a = $this->staff('09120000201', 'مهسا');
        $b = $this->staff('09120000202', 'رضا');
        $c = $this->staff('09120000203', 'نادیده');

        $this->actingAs($admin)
            ->post('/admin/staff-contracts', [
                'user_ids' => [$a->id, $b->id],   // فقط دو نفر تیک خورده‌اند
                // فرم تاریخ را شمسی می‌گیرد و کنترلر پیش از ذخیره تبدیل می‌کند.
                'contract_date' => \App\Support\JalaliDate::toJalali(now()->toDateString()),
                'start_date' => \App\Support\JalaliDate::toJalali(now()->toDateString()),
                'end_date' => \App\Support\JalaliDate::toJalali(now()->addYear()->toDateString()),
                'service_description' => 'نظارت و کنترل کیفیت',
                'monthly_wage' => 22000000,
                'promissory_amount' => 100000000,
            ])
            ->assertRedirect('/admin/staff-contracts');

        $this->assertSame(2, StaffContract::count());
        $this->assertTrue(StaffContract::where('user_id', $a->id)->exists());
        $this->assertTrue(StaffContract::where('user_id', $b->id)->exists());
        $this->assertFalse(StaffContract::where('user_id', $c->id)->exists(), 'کارمند تیک‌نخورده نباید قرارداد بگیرد.');

        // شمارهٔ قرارداد یکتا و مشخصات snapshot شده
        $numbers = StaffContract::pluck('contract_number');
        $this->assertCount(2, array_unique($numbers->all()));
        $this->assertSame('مهسا آزمایشی', StaffContract::where('user_id', $a->id)->value('party_name'));
        $this->assertSame('awaiting_staff', StaffContract::first()->status);
    }

    public function test_staff_uploads_documents_signs_and_records_video_then_submits(): void
    {
        $staff = $this->staff('09120000204', 'کارمند');
        $contract = $this->contractFor($staff);

        // ۱) بارگذاری هر ۶ مدرک
        foreach (array_keys(StaffContract::DOCUMENTS) as $field) {
            $this->actingAs($staff)
                ->post("/my-contracts/{$contract->id}/documents/{$field}", [
                    'file' => UploadedFile::fake()->image($field.'.jpg'),
                ])->assertRedirect();
        }
        $contract->refresh();
        $this->assertTrue($contract->documentsComplete());

        // هنوز امضا و ویدیو ندارد → ارسال باید رد شود
        $this->actingAs($staff)->post("/my-contracts/{$contract->id}/submit")
            ->assertSessionHasErrors('submit');
        $this->assertSame('awaiting_staff', $contract->fresh()->status);

        // ۲) امضای الکترونیک
        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/signature", [
                'signature' => $this->pngDataUri(),
                'accept_terms' => '1',
            ])->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertTrue($contract->hasSignature());
        $this->assertNotNull($contract->signed_at);
        Storage::disk('public')->assertExists($contract->signature_path);

        // ۳) ویدیوی احراز (ضبط‌شده در مرورگر → آپلود)
        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/video", [
                'video' => UploadedFile::fake()->create('verification.webm', 500, 'video/webm'),
            ])->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertTrue($contract->hasVideo());
        $this->assertSame(100, $contract->completionPercent());

        // ۴) ارسال نهایی
        $this->actingAs($staff)->post("/my-contracts/{$contract->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('submitted', $contract->fresh()->status);
    }

    public function test_upload_rejects_files_over_twenty_megabytes(): void
    {
        $staff = $this->staff('09120000205', 'حجم');
        $contract = $this->contractFor($staff);

        // ۲۵ مگابایت — بیش از سقف
        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/documents/doc_national_card_front", [
                'file' => UploadedFile::fake()->create('big.jpg', 25 * 1024, 'image/jpeg'),
            ])->assertSessionHasErrors('file');

        // ۱۹ مگابایت — مجاز
        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/documents/doc_national_card_front", [
                'file' => UploadedFile::fake()->create('ok.jpg', 19 * 1024, 'image/jpeg'),
            ])->assertSessionHasNoErrors();

        $this->assertNotNull($contract->fresh()->doc_national_card_front);
    }

    public function test_staff_cannot_access_another_persons_contract(): void
    {
        $owner = $this->staff('09120000206', 'صاحب');
        $other = $this->staff('09120000207', 'دیگری');
        $contract = $this->contractFor($owner);

        $this->actingAs($other)->get("/my-contracts/{$contract->id}")->assertForbidden();
        $this->actingAs($other)
            ->post("/my-contracts/{$contract->id}/documents/doc_national_card_front", [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])->assertForbidden();
    }

    public function test_submitted_contract_is_locked_for_staff_edits(): void
    {
        $staff = $this->staff('09120000208', 'قفل');
        $contract = $this->contractFor($staff, ['status' => 'submitted']);

        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/documents/doc_national_card_front", [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])->assertForbidden();
    }

    public function test_admin_approval_generates_downloadable_pdf(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('09120000209', 'تأیید');
        $contract = $this->completedContract($staff);

        $this->actingAs($admin)
            ->post("/admin/staff-contracts/{$contract->id}/approve")
            ->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('approved', $contract->status);
        $this->assertSame($admin->id, $contract->approved_by);
        $this->assertNotNull($contract->final_pdf_path, 'PDF نهایی باید ساخته شود.');
        Storage::disk('public')->assertExists($contract->final_pdf_path);

        // فایل واقعاً یک PDF است
        $binary = Storage::disk('public')->get($contract->final_pdf_path);
        $this->assertStringStartsWith('%PDF', $binary);

        // هر دو طرف می‌توانند دانلود کنند
        $this->actingAs($staff)->get("/my-contracts/{$contract->id}/download")->assertOk();
        $this->actingAs($admin)->get("/admin/staff-contracts/{$contract->id}/download")->assertOk();
    }

    public function test_admin_can_reject_and_staff_can_fix_and_resubmit(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('09120000210', 'اصلاح');
        $contract = $this->completedContract($staff);

        $this->actingAs($admin)
            ->post("/admin/staff-contracts/{$contract->id}/reject", ['reject_reason' => 'تصویر کارت ملی ناخواناست'])
            ->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('rejected', $contract->status);
        $this->assertSame('تصویر کارت ملی ناخواناست', $contract->reject_reason);
        $this->assertTrue($contract->editableByStaff(), 'قرارداد ردشده باید دوباره قابل ویرایش باشد.');

        // کارمند مدرک را اصلاح و دوباره ارسال می‌کند
        $this->actingAs($staff)
            ->post("/my-contracts/{$contract->id}/documents/doc_national_card_front", [
                'file' => UploadedFile::fake()->image('new.jpg'),
            ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post("/my-contracts/{$contract->id}/submit")->assertSessionHasNoErrors();

        $this->assertSame('submitted', $contract->fresh()->status);
        $this->assertNull($contract->fresh()->reject_reason);
    }

    public function test_incomplete_contract_cannot_be_approved(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('09120000211', 'ناقص');
        // وضعیت submitted ولی بدون مدارک/امضا/ویدیو
        $contract = $this->contractFor($staff, ['status' => 'submitted']);

        $this->actingAs($admin)
            ->post("/admin/staff-contracts/{$contract->id}/approve")
            ->assertSessionHasErrors('status');

        $this->assertSame('submitted', $contract->fresh()->status);
    }

    public function test_contract_pages_require_permission(): void
    {
        $plain = $this->staff('09120000212', 'بی‌دسترسی');

        $this->get('/admin/staff-contracts')->assertRedirect();
        $this->actingAs($plain)->get('/admin/staff-contracts')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/staff-contracts')->assertOk()->assertSee('قرارداد کارمندان');
    }

    public function test_contract_text_contains_filled_terms(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('09120000213', 'متن');
        $contract = $this->contractFor($staff, [
            'service_description' => 'نظارت و بررسی و ارتباط با مشتریان',
            'monthly_wage' => 22000000,
            'promissory_amount' => 100000000,
            'non_solicit_months' => 24,
        ]);

        $this->actingAs($admin)
            ->get("/admin/staff-contracts/{$contract->id}")
            ->assertOk()
            ->assertSee('نظارت و بررسی و ارتباط با مشتریان')
            ->assertSee('22,000,000 تومان')
            ->assertSee('100,000,000 تومان')
            ->assertSee('ماده ۱۴ ـ سفته تضمینی')
            ->assertSee('ماده ۲۱ ـ نسخه‌ها')
            ->assertSee($contract->contract_number);
    }

    // ── کمکی‌ها ───────────────────────────────────────────────────────

    private function admin(): User
    {
        $user = $this->staff('09121110301', 'مدیر');
        $user->givePermissionTo('manage-staff-contracts');

        return $user;
    }

    private function staff(string $mobile, string $firstName): User
    {
        return User::forceCreate([
            'first_name' => $firstName,
            'last_name' => 'آزمایشی',
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
            'is_staff' => true,
        ]);
    }

    private function contractFor(User $user, array $overrides = []): StaffContract
    {
        return StaffContract::create($overrides + [
            'contract_number' => StaffContract::nextNumber(),
            'user_id' => $user->id,
            'party_name' => $user->full_name,
            'party_national_code' => '0012345678',
            // این کمکی مستقیم روی مدل می‌نویسد و از فرم رد نمی‌شود، پس مقدار
            // همان میلادیِ ستون است — تبدیلِ شمسی فقط در کنترلر اتفاق می‌افتد.
            'contract_date' => now()->toDateString(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'service_description' => 'خدمات آزمایشی',
            'status' => 'awaiting_staff',
        ]);
    }

    /** قراردادی که کارمند کامل کرده و برای بررسی ارسال شده است. */
    private function completedContract(User $user): StaffContract
    {
        $contract = $this->contractFor($user);

        $data = ['status' => 'submitted', 'signed_at' => now(), 'video_recorded_at' => now()];
        foreach (array_keys(StaffContract::DOCUMENTS) as $field) {
            $path = 'staff-contracts/'.$contract->id.'/'.$field.'.jpg';
            Storage::disk('public')->put($path, 'fake');
            $data[$field] = $path;
        }

        $signature = 'staff-contracts/'.$contract->id.'/signature.png';
        Storage::disk('public')->put($signature, base64_decode($this->pngBase64()));
        $data['signature_path'] = $signature;

        $video = 'staff-contracts/'.$contract->id.'/verification.webm';
        Storage::disk('public')->put($video, 'fake-video');
        $data['video_path'] = $video;

        $contract->forceFill($data)->save();

        return $contract->fresh();
    }

    private function pngDataUri(): string
    {
        return 'data:image/png;base64,'.$this->pngBase64();
    }

    /** یک PNG کوچک و معتبر (۱×۱). */
    private function pngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }
}
