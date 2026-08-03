<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\PushEventSetting;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Support\TechPushPolicy;
use Tests\TestCase;

/**
 * پنلِ مدیریتِ پوش — رویدادها، تنظیمات و ارسالِ دستی.
 *
 * ادعای مرکزی: فرمِ پنل «واقعاً» روی ارسال اثر می‌گذارد. یک صفحهٔ تنظیماتی
 * که ذخیره می‌کند ولی هیچ‌جا خوانده نمی‌شود، بدتر از نبودنش است.
 */
class PushManagementTest extends TestCase
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
            foreach (['first_name', 'mobile'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $t->string($column)->nullable();
                }
            }
        });

        // لایوت ادمین روی هر صفحه `Setting::get('site_name')` را می‌خواند.
        Schema::create('settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
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

        foreach ([
            'Modules/CRM/Database/Migrations/2026_07_29_130000_create_crm_technician_push_tokens_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_100000_add_najva_columns_to_crm_technician_push_tokens_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_110000_create_crm_push_logs_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_120000_add_fingerprint_to_crm_push_logs_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_130000_create_crm_push_event_settings_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        // لایوتِ واقعیِ ادمین ده‌ها جدولِ نامرتبط (حضور کاربران، سایدبار،
        // شمارنده‌ها) را می‌خواند. موضوعِ این تست ویوهای پوش است، پس همان
        // stubِ حداقلیِ پروژه جایگزین می‌شود.
        view()->getFinder()->prependLocation(base_path('tests/stubs/views'));

        PushEventSetting::flushCache();

        config([
            'services.najva.api_key' => 'test-key',
            'services.najva.website_id' => '45290',
            'services.tech_app.url' => 'https://tg.tamironline.com',
        ]);
    }

    private ?User $adminUser = null;

    private function admin(): User
    {
        if ($this->adminUser) {
            return $this->adminUser;
        }

        $admin = User::forceCreate([
            'first_name' => 'مدیر',
            'email' => 'push-admin@example.test',
            'password' => bcrypt('secret'),
            'mobile' => '09123456789',
            'mobile_verified_at' => now(),   // مسیرهای ادمین پشتِ verified.mobile هستند
        ]);

        \Spatie\Permission\Models\Permission::findOrCreate('manage-crm-push', 'web');
        $admin->givePermissionTo('manage-crm-push');

        return $this->adminUser = $admin;
    }

    // ───────────────────────── دسترسی

    public function test_the_panel_is_closed_without_the_permission(): void
    {
        $stranger = User::forceCreate([
            'first_name' => 'بی‌دسترسی',
            'email' => 'nobody@example.test',
            'password' => bcrypt('secret'),
            'mobile' => '09120000000',
            'mobile_verified_at' => now(),
        ]);

        $this->actingAs($stranger)->get(route('crm.push.index'))->assertForbidden();
    }

    public function test_each_page_renders(): void
    {
        foreach ([
            'crm.push.index', 'crm.push.events', 'crm.push.campaign.create',
            'crm.push.logs', 'crm.push.subscribers', 'crm.push.settings',
        ] as $route) {
            $this->actingAs($this->admin())->get(route($route))->assertOk();
        }
    }

    // ───────────────────────── رویدادها

    public function test_a_saved_template_actually_changes_what_is_sent(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.events.update', PushEvent::OrderAssigned->value), [
                'title' => 'کار تازه',
                'body' => 'سفارش {order_code} برای شماست',
                'ttl' => 48,
            ])
            ->assertRedirect();

        PushEventSetting::flushCache();

        $this->assertSame('کار تازه', PushEventSetting::titleTemplate(PushEvent::OrderAssigned));
        $this->assertSame(48, PushEventSetting::ttl(PushEvent::OrderAssigned));

        // و در عمل، نه فقط در دیتابیس.
        TechnicianPushToken::create([
            'technician_id' => 7, 'token' => 'tok-a', 'provider' => 'najva', 'platform' => 'web',
        ]);
        Http::fake(['*/v1/send/token/' => Http::response([
            'Message' => 'ok',
            'Entries' => ['request_id' => 'r1', 'tokens' => [['token' => 'tok-a', 'status' => 'Sent', 'cost' => 1]]],
        ], 200)]);

        (new \Modules\CRM\Jobs\SendTechnicianPush(
            7, PushEvent::OrderAssigned, ['order_code' => 'OR-5'], orderId: 5, fingerprint: 'assign:1'
        ))->handle(app(\Modules\CRM\Services\Najva\NajvaPushService::class));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $fields = collect($request->data())->keyBy('name');

            $this->assertSame('کار تازه', $fields['message.title']['contents']);
            $this->assertSame('سفارش OR-5 برای شماست', $fields['message.body']['contents']);
            $this->assertSame('48', $fields['ttl']['contents']);

            return true;
        });
    }

    public function test_an_unknown_variable_is_refused_before_it_reaches_a_phone(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.events.update', PushEvent::Announcement->value), [
                // اطلاعیه فقط {title} دارد.
                'body' => 'سلام {customer_name}',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, PushEventSetting::count());
    }

    public function test_a_half_filled_quiet_window_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.events.update', PushEvent::Announcement->value), ['quiet_start' => 9])
            ->assertSessionHas('error');

        $this->assertSame(0, PushEventSetting::count());
    }

    public function test_disabling_an_event_stops_it_being_sent(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.events.toggle', PushEvent::Announcement->value))
            ->assertRedirect();

        PushEventSetting::flushCache();

        $this->assertFalse(PushEventSetting::enabled(PushEvent::Announcement));
        $this->assertFalse(TechPushPolicy::allows(PushEvent::Announcement, 7));
    }

    public function test_a_per_event_window_overrides_the_global_one(): void
    {
        TechPushPolicy::saveWindow(0, 0);   // سراسری: بی‌محدودیت

        $this->actingAs($this->admin())
            ->post(route('crm.push.events.update', PushEvent::OrderReturned->value), [
                'quiet_start' => 9, 'quiet_end' => 18,
            ])->assertRedirect();

        PushEventSetting::flushCache();

        // برگشتی به‌طور پیش‌فرض بی‌پنجره است؛ تعیینِ صریح باید بچربد.
        $this->assertFalse(TechPushPolicy::withinWindow(
            PushEvent::OrderReturned, \Carbon\CarbonImmutable::parse('2026-08-03 03:00')
        ));
        $this->assertTrue(TechPushPolicy::withinWindow(
            PushEvent::OrderReturned, \Carbon\CarbonImmutable::parse('2026-08-03 12:00')
        ));
    }

    // ───────────────────────── تنظیمات

    public function test_a_uuid_in_the_website_id_field_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.settings.update'), [
                'najva_website_id' => '2bad1f60-e2dd-41dd-8658-e3ca13223250',
                'tech_push_quiet_start' => 8,
                'tech_push_quiet_end' => 22,
            ])
            ->assertSessionHas('error');

        $this->assertSame('', CrmSetting::get('najva_website_id', ''));
    }

    public function test_the_numeric_website_id_is_accepted_and_used(): void
    {
        $this->actingAs($this->admin())
            ->post(route('crm.push.settings.update'), [
                'najva_api_key' => 'panel-key',
                'najva_website_id' => '45290',
                'tech_push_quiet_start' => 7,
                'tech_push_quiet_end' => 21,
            ])
            ->assertSessionHas('success');

        $this->assertSame('45290', CrmSetting::get('najva_website_id'));
        $this->assertSame(['start' => 7, 'end' => 21], TechPushPolicy::window());

        // تنظیماتِ پنل باید بر env بچربد.
        Http::fake(['*/v1/send/token/' => Http::response([
            'Message' => 'ok', 'Entries' => ['request_id' => 'r', 'tokens' => []],
        ], 200)]);

        (new \Modules\CRM\Services\Najva\NajvaPushService)
            ->sendToTokens(['tok'], 'ت', 'ب', 'https://x.test/a');

        Http::assertSent(fn (\Illuminate\Http\Client\Request $r) => ($r->header('apiKey')[0] ?? null) === 'panel-key');
    }

    public function test_the_connection_test_reports_the_numeric_id(): void
    {
        Http::fake(['*/v1/sender' => Http::response([
            'Entries' => ['websites' => [['address' => 'https://tg.tamironline.com', 'id' => '45290']]],
        ], 200)]);

        $this->actingAs($this->admin())
            ->post(route('crm.push.settings.test'))
            ->assertSessionHas('success');
    }

    public function test_the_connection_test_explains_a_whitelist_problem(): void
    {
        Http::fake(['*/v1/sender' => Http::response([], 416)]);

        $response = $this->actingAs($this->admin())->post(route('crm.push.settings.test'));

        $this->assertStringContainsString('وایت‌لیست', session('error'));
        $response->assertRedirect();
    }

    // ───────────────────────── ارسال دستی

    public function test_a_campaign_with_no_reachable_browser_is_refused(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->post(route('crm.push.campaign.store'), [
                'audience' => 'all',
                'title' => 'سلام',
                'body' => 'متن',
                'click_url' => 'https://tg.tamironline.com/orders',
                'ttl' => 24,
            ])
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_a_campaign_reaches_every_active_browser(): void
    {
        TechnicianPushToken::create(['technician_id' => 1, 'token' => 'tok-1', 'provider' => 'najva', 'platform' => 'web']);
        TechnicianPushToken::create(['technician_id' => 2, 'token' => 'tok-2', 'provider' => 'najva', 'platform' => 'web']);
        TechnicianPushToken::create(['technician_id' => 3, 'token' => 'tok-dead', 'provider' => 'najva', 'platform' => 'web', 'revoked_at' => now()]);

        Http::fake(['*/v1/send/token/' => Http::response([
            'Message' => 'ok',
            'Entries' => ['request_id' => 'r1', 'tokens' => [
                ['token' => 'tok-1', 'status' => 'Sent', 'cost' => 25],
                ['token' => 'tok-2', 'status' => 'Sent', 'cost' => 25],
            ]],
        ], 200)]);

        $this->actingAs($this->admin())
            ->post(route('crm.push.campaign.store'), [
                'audience' => 'all',
                'title' => 'اطلاعیهٔ فوری',
                'body' => 'فردا جلسه داریم',
                'click_url' => 'https://tg.tamironline.com/announcements',
                'ttl' => 24,
            ])
            ->assertSessionHas('success');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $tokens = array_column(array_filter($request->data(), fn ($f) => $f['name'] === 'tokens[]'), 'contents');

            // باطل‌شده نباید در فهرست باشد.
            $this->assertEqualsCanonicalizing(['tok-1', 'tok-2'], $tokens);

            return true;
        });

        // ارسالِ دستی رویداد ندارد — در گزارش با «ارسال دستی» شناخته می‌شود.
        $this->assertSame(2, PushLog::whereNull('event_key')->count());
    }

    public function test_a_campaign_never_ignores_a_whitelist_error(): void
    {
        TechnicianPushToken::create(['technician_id' => 1, 'token' => 'tok-1', 'provider' => 'najva', 'platform' => 'web']);
        Http::fake(['*/v1/send/token/' => Http::response([], 416)]);

        $this->actingAs($this->admin())
            ->post(route('crm.push.campaign.store'), [
                'audience' => 'all',
                'title' => 'سلام', 'body' => 'متن',
                'click_url' => 'https://tg.tamironline.com/orders', 'ttl' => 24,
            ])
            ->assertSessionHas('error');
    }

    public function test_a_past_schedule_is_refused(): void
    {
        TechnicianPushToken::create(['technician_id' => 1, 'token' => 'tok-1', 'provider' => 'najva', 'platform' => 'web']);
        Http::fake();

        $this->actingAs($this->admin())
            ->post(route('crm.push.campaign.store'), [
                'audience' => 'all',
                'title' => 'سلام', 'body' => 'متن',
                'click_url' => 'https://tg.tamironline.com/orders', 'ttl' => 24,
                'scheduled_at' => '1400/01/01', 'scheduled_time' => '09:00',
            ])
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    // ───────────────────────── گزارش

    public function test_the_log_filter_narrows_the_result(): void
    {
        PushLog::create(['token' => 'a', 'technician_id' => 1, 'event_key' => 'order_assigned_tech', 'title' => 'الف', 'body' => 'ب', 'status' => 'Sent', 'cost' => 25]);
        PushLog::create(['token' => 'b', 'technician_id' => 2, 'event_key' => null, 'title' => 'دستی', 'body' => 'ب', 'status' => 'failed', 'error_code' => 416]);

        $this->actingAs($this->admin())
            ->get(route('crm.push.logs', ['status' => 'failed']))
            ->assertOk()
            // «الف» عنوانِ ردیفِ موفق است و نباید در نتیجهٔ فیلترِ «ناموفق»
            // بیاید. روی نامِ رویداد نمی‌سنجیم چون در گزینه‌های dropdown
            // هم هست، فارغ از فیلتر.
            ->assertSee('دستی')
            ->assertDontSee('الف');
    }

    public function test_the_export_streams_the_filtered_rows(): void
    {
        PushLog::create(['token' => 'a', 'technician_id' => 1, 'event_key' => null, 'title' => 'الف', 'body' => 'ب', 'status' => 'Sent', 'cost' => 25]);

        $this->actingAs($this->admin())
            ->get(route('crm.push.logs.export', ['format' => 'csv']))
            ->assertOk()
            ->assertDownload();
    }
}
