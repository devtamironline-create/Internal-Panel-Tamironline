<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * تخصیصِ گروهیِ اپراتور به تکنسین‌ها (چت اپراتور↔تکنسین):
 *
 *   - «افزودن به همه» اپراتور را با یک درخواست به همهٔ تکنسین‌های فعال
 *     اضافه می‌کند؛ تخصیص‌های فعلیِ بقیهٔ اپراتورها دست نمی‌خورد.
 *   - idempotent: اجرای دوباره چیزی را دوبار اضافه نمی‌کند.
 *   - «حذف از همه» فقط همان اپراتور را از همه برمی‌دارد.
 *   - بدونِ permission «manage-technicians» بسته است.
 */
class TechChatBulkAssignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php',
            '--force' => true,
        ]);

        Schema::table('users', function ($t) {
            foreach (['first_name', 'last_name', 'mobile', 'is_active'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $column === 'is_active' ? $t->boolean($column)->default(true) : $t->string($column)->nullable();
                }
            }
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_operators', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('assigned_at')->nullable();
        });
    }

    private function admin(): User
    {
        $admin = User::forceCreate([
            'first_name' => 'مدیر', 'email' => 'chat-admin@example.test',
            'password' => bcrypt('secret'), 'mobile' => '09123456700',
            'mobile_verified_at' => now(),
        ]);
        \Spatie\Permission\Models\Permission::findOrCreate('manage-technicians', 'web');
        $admin->givePermissionTo('manage-technicians');

        return $admin;
    }

    private function operator(string $email): User
    {
        return User::forceCreate([
            'first_name' => 'اپراتور', 'email' => $email,
            'password' => bcrypt('secret'),
            'mobile' => '0935'.random_int(1000000, 9999999),
            'mobile_verified_at' => now(),
        ]);
    }

    private function tech(string $status = 'active'): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '0912'.random_int(1000000, 9999999), 'status' => $status,
        ]);
    }

    public function test_bulk_attach_adds_the_operator_to_every_active_technician_in_one_save(): void
    {
        $admin = $this->admin();
        $op = $this->operator('op1@example.test');
        $other = $this->operator('op2@example.test');

        $t1 = $this->tech();
        $t2 = $this->tech();
        $inactive = $this->tech(status: 'inactive');
        // t1 از قبل اپراتورِ دیگری دارد — نباید حذف شود.
        $t1->operators()->attach($other->id, ['assigned_at' => now()]);

        $this->actingAs($admin)
            ->post(route('crm.tech-chats.assignments.bulk'), [
                'operator_id' => $op->id, 'bulk_action' => 'attach',
            ])->assertRedirect();

        $this->assertTrue($t1->operators()->where('users.id', $op->id)->exists());
        $this->assertTrue($t2->operators()->where('users.id', $op->id)->exists());
        $this->assertTrue($t1->operators()->where('users.id', $other->id)->exists(), 'تخصیص قبلی نباید پاک شود.');
        $this->assertFalse($inactive->operators()->where('users.id', $op->id)->exists(), 'تکنسین غیرفعال نباید تخصیص بگیرد.');
    }

    public function test_bulk_attach_is_idempotent(): void
    {
        $admin = $this->admin();
        $op = $this->operator('op3@example.test');
        $t = $this->tech();

        foreach ([1, 2] as $i) {
            $this->actingAs($admin)->post(route('crm.tech-chats.assignments.bulk'), [
                'operator_id' => $op->id, 'bulk_action' => 'attach',
            ]);
        }

        $this->assertSame(1, $t->operators()->where('users.id', $op->id)->count());
    }

    public function test_bulk_detach_removes_only_that_operator_from_everyone(): void
    {
        $admin = $this->admin();
        $op = $this->operator('op4@example.test');
        $other = $this->operator('op5@example.test');
        $t1 = $this->tech();
        $t2 = $this->tech();
        $t1->operators()->attach([$op->id => ['assigned_at' => now()], $other->id => ['assigned_at' => now()]]);
        $t2->operators()->attach($op->id, ['assigned_at' => now()]);

        $this->actingAs($admin)
            ->post(route('crm.tech-chats.assignments.bulk'), [
                'operator_id' => $op->id, 'bulk_action' => 'detach',
            ])->assertRedirect();

        $this->assertFalse($t1->operators()->where('users.id', $op->id)->exists());
        $this->assertFalse($t2->operators()->where('users.id', $op->id)->exists());
        $this->assertTrue($t1->operators()->where('users.id', $other->id)->exists(), 'اپراتور دیگر نباید حذف شود.');
    }

    public function test_bulk_assign_is_closed_without_the_permission(): void
    {
        $stranger = $this->operator('stranger@example.test');
        $op = $this->operator('op6@example.test');
        $this->tech();

        $this->actingAs($stranger)
            ->post(route('crm.tech-chats.assignments.bulk'), [
                'operator_id' => $op->id, 'bulk_action' => 'attach',
            ])->assertForbidden();
    }
}
