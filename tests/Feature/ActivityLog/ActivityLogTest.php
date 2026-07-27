<?php

namespace Tests\Feature\ActivityLog;

use App\Models\User;
use App\Support\ActivityLog\ActivityLog;
use App\Support\ActivityLog\ActivityLogReader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * گزارشِ فعالیت: ثبت در فایل، فیلتر، دسترسی و پاک‌سازیِ ۱۵ روزه.
 */
class ActivityLogTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDir = storage_path('logs/activity');
        File::deleteDirectory($this->logDir);

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'database/migrations/2026_01_04_100000_create_chat_tables.php',
            'database/migrations/2026_02_03_140854_create_settings_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // تست‌ها در کنسول اجرا می‌شوند؛ برای بررسیِ ثبتِ خودکارِ رویدادهای مدل
        // باید همان مسیرِ production فعال شود (در production این گیت روشن است
        // چون درخواست‌ها HTTP هستند).
        config(['activity-log.log_console' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logDir);
        parent::tearDown();
    }

    public function test_model_created_and_updated_and_deleted_are_written_to_file(): void
    {
        $user = $this->makeUser('09120000010');

        $this->assertNotEmpty($this->entries(), 'ایجادِ کاربر باید ثبت شود.');
        $this->assertSame('created', $this->entries()[0]['action']);

        $user->update(['first_name' => 'تغییر یافته']);
        $user->delete();

        $actions = array_column($this->entries(), 'action');
        $this->assertContains('created', $actions);
        $this->assertContains('updated', $actions);
        $this->assertContains('deleted', $actions);
    }

    public function test_log_file_is_json_lines_named_by_date(): void
    {
        $this->makeUser('09120000011');

        $file = $this->logDir.'/activity-'.now()->format('Y-m-d').'.log';
        $this->assertFileExists($file, 'فایل باید به‌نامِ تاریخِ روز ساخته شود.');

        $firstLine = trim(explode("\n", trim(file_get_contents($file)))[0]);
        $decoded = json_decode($firstLine, true);
        $this->assertIsArray($decoded, 'هر خط باید JSON معتبر باشد.');
        $this->assertSame('created', $decoded['context']['action']);
    }

    public function test_update_records_changed_fields_with_old_and_new(): void
    {
        $user = $this->makeUser('09120000012', ['first_name' => 'قبلی']);
        $user->update(['first_name' => 'بعدی']);

        $updated = collect($this->entries())->firstWhere('action', 'updated');

        $this->assertNotNull($updated);
        $this->assertArrayHasKey('first_name', $updated['changes']);
        $this->assertSame('قبلی', $updated['changes']['first_name']['old']);
        $this->assertSame('بعدی', $updated['changes']['first_name']['new']);
    }

    public function test_sensitive_fields_are_never_written(): void
    {
        $user = $this->makeUser('09120000013');
        $user->update(['password' => bcrypt('super-secret-value')]);

        $raw = file_get_contents($this->logDir.'/activity-'.now()->format('Y-m-d').'.log');

        $this->assertStringNotContainsString('super-secret-value', $raw);
        $updated = collect($this->entries())->firstWhere('action', 'updated');
        $this->assertSame('••••••', $updated['changes']['password']['new']);
    }

    public function test_timestamp_only_update_is_not_logged(): void
    {
        $user = $this->makeUser('09120000014');
        $before = count($this->entries());

        $user->touch(); // فقط updated_at

        $this->assertCount($before, $this->entries(), 'تغییرِ صرفِ timestamp نباید ثبت شود.');
    }

    public function test_excluded_models_are_not_logged(): void
    {
        config(['activity-log.excluded_models' => [User::class]]);

        $this->makeUser('09120000015');

        $this->assertSame([], $this->entries(), 'مدل مستثنا نباید ثبت شود.');
    }

    public function test_muted_block_is_not_logged(): void
    {
        ActivityLog::withoutLogging(function () {
            $this->makeUser('09120000016');
        });

        $this->assertSame([], $this->entries());
    }

    public function test_reader_filters_by_action_and_search(): void
    {
        $user = $this->makeUser('09120000017', ['first_name' => 'آرش']);
        $user->update(['first_name' => 'کاوه']);

        $reader = app(ActivityLogReader::class);

        $onlyUpdates = $reader->search(['action' => 'updated']);
        $this->assertSame(1, $onlyUpdates['scanned']);
        $this->assertSame('updated', $onlyUpdates['items'][0]['action']);

        $bySearch = $reader->search(['q' => 'کاوه']);
        $this->assertGreaterThanOrEqual(1, $bySearch['scanned']);

        $noMatch = $reader->search(['q' => 'رشتهٔ-کاملاً-نامرتبط-xyz']);
        $this->assertSame(0, $noMatch['scanned']);
    }

    public function test_reader_filters_by_date_range(): void
    {
        $this->makeUser('09120000018');

        $reader = app(ActivityLogReader::class);

        $today = $reader->search(['from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $this->assertGreaterThan(0, $today['scanned']);

        $past = $reader->search(['from' => now()->subDays(10)->toDateString(), 'to' => now()->subDays(5)->toDateString()]);
        $this->assertSame(0, $past['scanned'], 'بازهٔ گذشته نباید رویدادِ امروز را برگرداند.');
    }

    public function test_purge_command_deletes_files_older_than_retention(): void
    {
        File::ensureDirectoryExists($this->logDir);

        $old = $this->logDir.'/activity-'.now()->subDays(20)->format('Y-m-d').'.log';
        $edge = $this->logDir.'/activity-'.now()->subDays(16)->format('Y-m-d').'.log';
        $fresh = $this->logDir.'/activity-'.now()->subDays(3)->format('Y-m-d').'.log';
        foreach ([$old, $edge, $fresh] as $file) {
            File::put($file, '{"context":{"action":"created"}}'."\n");
        }

        Artisan::call('activity-log:purge');

        $this->assertFileDoesNotExist($old, 'فایل ۲۰ روزه باید حذف شود.');
        $this->assertFileDoesNotExist($edge, 'فایل ۱۶ روزه باید حذف شود.');
        $this->assertFileExists($fresh, 'فایل ۳ روزه باید بماند.');
    }

    public function test_only_super_admin_can_view_the_report(): void
    {
        Permission::firstOrCreate(['name' => 'manage-permissions', 'guard_name' => 'web']);

        $this->get('/admin/activity-log')->assertRedirect();

        $plain = $this->makeUser('09120000019');
        $this->actingAs($plain)->get('/admin/activity-log')->assertForbidden();

        $admin = $this->makeUser('09120000020');
        $admin->givePermissionTo('manage-permissions');
        $this->actingAs($admin)->get('/admin/activity-log')->assertOk()->assertSee('گزارش فعالیت');
    }

    public function test_report_page_shows_recorded_entries(): void
    {
        Permission::firstOrCreate(['name' => 'manage-permissions', 'guard_name' => 'web']);
        $admin = $this->makeUser('09120000021');
        $admin->givePermissionTo('manage-permissions');

        ActivityLog::record('created', 'رویداد آزمایشی', [
            'entity_label' => 'سفارش',
            'entity_id' => 4242,
            'entity_title' => 'ORD-TEST-4242',
        ]);

        $this->actingAs($admin)
            ->get('/admin/activity-log')
            ->assertOk()
            ->assertSee('ORD-TEST-4242');
    }

    private function makeUser(string $mobile, array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'first_name' => 'کاربر',
            'last_name' => 'آزمایشی',
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
    }

    /** @return list<array<string, mixed>> همهٔ ردیف‌های ثبت‌شدهٔ امروز */
    private function entries(): array
    {
        return iterator_to_array(
            app(ActivityLogReader::class)->readLines(now()->format('Y-m-d')),
            false
        );
    }
}
