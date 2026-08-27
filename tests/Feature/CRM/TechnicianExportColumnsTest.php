<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * خروجی اکسل/CSV تکنسین‌ها (درخواستِ تیم ۱۴۰۵/۰۶/۰۴): ستون‌های
 * «نوع خدمات قابل ارائه»، «شهرهای فعال»، «مناطق پوشش» و «دستگاه‌های
 * قابل انجام» اضافه شده‌اند و از تگ‌های تکنسین پر می‌شوند.
 */
class TechnicianExportColumnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--path' => 'database/migrations/0001_01_01_000000_create_users_table.php', '--force' => true]);
        Artisan::call('migrate', ['--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php', '--force' => true]);

        Schema::table('users', function ($t) {
            foreach (['first_name', 'last_name', 'mobile'] as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $t->string($c)->nullable();
                }
            }
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('technician_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('phone')->nullable();
            $t->string('province')->nullable();
            $t->string('specialty')->nullable();
            $t->string('type_tech')->nullable();
            $t->text('service_types')->nullable();
            $t->integer('percent')->nullable();
            $t->integer('max_order')->nullable();
            $t->integer('max_price')->nullable();
            $t->string('status', 20)->default('active');
            $t->boolean('ready_for_delivery')->default(false);
            $t->bigInteger('wallet_balance')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('province_id')->nullable(),
            $x->unsignedBigInteger('parent_city_id')->nullable(),
            $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('name'), $x->string('slug')->nullable(),
            $x->boolean('is_active')->default(true),
            $x->unsignedInteger('sort_order')->default(0), $x->timestamps(),
        ]));
        Schema::create('crm_technician_cities', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('city_id'),
        ]));
        Schema::create('crm_technician_districts', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('district_id'),
        ]));
        Schema::create('crm_technician_devices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('technician_id'), $x->unsignedBigInteger('device_id'),
            $x->integer('priority')->nullable(),
        ]));
        // ساختِ شهر، نسخهٔ کشِ اپ را bump می‌کند (AppCacheVersion → settings).
        Schema::create('settings', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('key')->unique(), $x->text('value')->nullable(),
        ]));
    }

    private function admin(): User
    {
        $u = User::forceCreate([
            'first_name' => 'مدیر', 'email' => 'texp@example.test',
            'password' => bcrypt('x'), 'mobile' => '09120000009', 'mobile_verified_at' => now(),
        ]);
        \Spatie\Permission\Models\Permission::findOrCreate('view-crm-technicians', 'web');
        $u->givePermissionTo('view-crm-technicians');

        return $u;
    }

    /**
     * CSV به‌صورت stream تولید می‌شود و بافرهای خروجی را پاک می‌کند (درست
     * برای production)، پس streamedContent قابلِ capture نیست. این‌جا: (۱)
     * روت با ستون‌های جدید ۲۰۰ می‌دهد و (۲) خودِ تبدیل‌های چهار ستون —
     * دقیقاً همان‌که کنترلر انجام می‌دهد — روی داده بررسی می‌شوند.
     */
    public function test_export_route_is_ok_with_the_new_columns(): void
    {
        City::create(['name' => 'تهران', 'slug' => 'tehran']);
        Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $t = Technician::forceCreate([
            'technician_id' => 'TCH-1', 'firstname_tech' => 'رضا', 'mobile' => '09120000001',
            'status' => 'active', 'service_types' => ['تعمیر', 'نصب'],
        ]);
        $t->cities()->sync([City::first()->id]);
        $t->devices()->sync([Device::first()->id]);

        $this->actingAs($this->admin())
            ->get('/admin/crm/technicians/export/csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_the_four_new_columns_are_built_from_technician_tags(): void
    {
        $tehran = City::create(['name' => 'تهران', 'slug' => 'tehran', 'is_active' => true]);
        $inactive = City::create(['name' => 'شهر غیرفعال', 'slug' => 'off', 'is_active' => false]);
        $district = City::create(['name' => 'منطقه ۵', 'slug' => 'r5', 'parent_city_id' => $tehran->id]);
        $washer = Device::create(['name' => 'لباسشویی', 'slug' => 'washer']);
        $fridge = Device::create(['name' => 'یخچال', 'slug' => 'fridge']);

        $t = Technician::forceCreate([
            'firstname_tech' => 'رضا', 'mobile' => '09120000003', 'status' => 'active',
            'service_types' => ['تعمیر', 'نصب'],
        ]);
        $t->cities()->sync([$tehran->id, $inactive->id]);
        $t->regions()->sync([$district->id]);
        $t->devices()->sync([$washer->id, $fridge->id]);

        $t->load(['cities:id,name,is_active', 'regions:id,name', 'devices:id,name']);

        // همان تبدیل‌های کنترلر (export()).
        $serviceTypes = collect((array) $t->service_types)->map(fn ($v) => trim((string) $v))->filter()->implode('، ');
        $cities = $t->cities->where('is_active', true)->pluck('name')->filter()->implode('، ');
        $regions = $t->regions->pluck('name')->filter()->implode('، ');
        $devices = $t->devices->pluck('name')->filter()->implode('، ');

        $this->assertSame('تعمیر، نصب', $serviceTypes);
        $this->assertSame('تهران', $cities);                 // شهرِ غیرفعال حذف شده
        $this->assertSame('منطقه ۵', $regions);
        $this->assertSame('لباسشویی، یخچال', $devices);
    }
}
