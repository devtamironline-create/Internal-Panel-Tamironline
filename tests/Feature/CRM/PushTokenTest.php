<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Http\Controllers\Api\V1\Technician\PushTokenController;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TechnicianPushToken;
use Tests\TestCase;

/**
 * ثبت و باطل‌کردنِ توکنِ پوشِ نجوا.
 *
 * ادعای اصلی: payloadِ واقعیِ اپ باید پذیرفته شود. تا پیش از این تغییر،
 * `provider=najva` و `platform=web` در فهرستِ مجاز نبودند و اپ برای هر
 * ثبت ۴۲۲ می‌گرفت — یعنی هیچ توکنی ذخیره نمی‌شد.
 *
 * کنترلر مستقیم صدا زده می‌شود نه از راهِ route: زنجیرهٔ auth اپ
 * (Sanctum + EnsureTechnician + TechRollingToken) موضوعِ این تست نیست.
 */
class PushTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        // خودِ مهاجرت‌ها اجرا می‌شوند، نه schemaِ دست‌ساز — تا اگر ستونی
        // در مهاجرت جا افتاده باشد، همین‌جا معلوم شود.
        foreach ([
            'Modules/CRM/Database/Migrations/2026_07_29_130000_create_crm_technician_push_tokens_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_100000_add_najva_columns_to_crm_technician_push_tokens_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        TechnicianPushToken::flushSchemaCache();
    }

    /**
     * schemaِ پیش از نجوا — شبیه‌سازیِ «کد رفته، migrate نخورده».
     *
     * جدول دستی ساخته می‌شود نه با اجرای دوبارهٔ مهاجرت: لاراول آن مهاجرت
     * را «اجراشده» ثبت کرده و دوباره اجرایش نمی‌کند.
     */
    private function rollBackToPreNajvaSchema(): void
    {
        Schema::dropIfExists('crm_technician_push_tokens');

        Schema::create('crm_technician_push_tokens', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id')->index();
            $t->string('token', 255)->unique();
            $t->string('provider', 20)->default('expo');
            $t->string('platform', 10)->nullable();
            $t->string('device_name', 120)->nullable();
            $t->string('app_version', 20)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        TechnicianPushToken::flushSchemaCache();
    }

    // ───────────────────────── helpers

    private function technician(string $name = 'علی'): Technician
    {
        return Technician::forceCreate([
            'first_name' => $name,
            'firstname_tech' => $name,
            'mobile' => '0912'.random_int(1000000, 9999999),
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function store(Technician $tech, array $payload): array
    {
        $request = Request::create('/v1/technician/me/push-token', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        $response = app(PushTokenController::class)->store($request);

        return json_decode($response->getContent(), true);
    }

    /** @return array<string, mixed> */
    private function destroy(Technician $tech, string $token): array
    {
        $request = Request::create('/v1/technician/me/push-token', 'DELETE', ['token' => $token]);
        $request->setUserResolver(fn () => $tech);

        $response = app(PushTokenController::class)->destroy($request);

        return json_decode($response->getContent(), true);
    }

    /** payloadِ دقیقی که اپ می‌فرستد — بخش ۲ سند نجوا. */
    private function najvaPayload(string $token = '9615c2ea-9d2d-4343-b880-4e79cb115384'): array
    {
        return [
            'token' => $token,
            'provider' => 'najva',
            'najva_script_id' => '2bad1f60-e2dd-41dd-8658-e3ca13223250',
            'platform' => 'web',
        ];
    }

    // ───────────────────────── پذیرشِ payloadِ اپ

    public function test_the_apps_najva_payload_is_accepted_and_stored(): void
    {
        $tech = $this->technician();

        $body = $this->store($tech, $this->najvaPayload());

        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['registered']);

        $row = TechnicianPushToken::firstOrFail();
        $this->assertSame('9615c2ea-9d2d-4343-b880-4e79cb115384', $row->token);
        $this->assertSame('najva', $row->provider);
        $this->assertSame('web', $row->platform);
        $this->assertSame('2bad1f60-e2dd-41dd-8658-e3ca13223250', $row->najva_script_id);
        $this->assertSame($tech->id, $row->technician_id);
        $this->assertNotNull($row->last_seen_at);
        $this->assertNull($row->revoked_at);
        $this->assertSame(0, $row->failed_count);
    }

    /** اپِ نیتیو حذف نشده — مسیرِ قدیمی نباید بشکند. */
    public function test_a_native_app_token_still_registers(): void
    {
        $this->store($this->technician(), [
            'token' => 'ExponentPushToken[abc]',
            'provider' => 'expo',
            'platform' => 'android',
            'device_name' => 'Galaxy A52',
            'app_version' => '1.4.0',
        ]);

        $row = TechnicianPushToken::firstOrFail();
        $this->assertSame('expo', $row->provider);
        $this->assertSame('android', $row->platform);
        $this->assertSame('Galaxy A52', $row->device_name);
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->store($this->technician(), ['token' => 'x', 'provider' => 'onesignal']);
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->store($this->technician(), ['token' => 'x', 'platform' => 'desktop']);
    }

    // ───────────────────────── مالکیتِ توکن

    public function test_a_shared_browser_moves_to_the_new_owner_instead_of_duplicating(): void
    {
        $first = $this->technician('اولی');
        $second = $this->technician('دومی');

        $this->store($first, $this->najvaPayload());
        $this->store($second, $this->najvaPayload());

        $this->assertSame(1, TechnicianPushToken::count());
        $this->assertSame($second->id, TechnicianPushToken::firstOrFail()->technician_id);
    }

    // ───────────────────────── خروج از حساب

    public function test_logging_out_revokes_the_row_instead_of_deleting_it(): void
    {
        $tech = $this->technician();
        $this->store($tech, $this->najvaPayload());

        $body = $this->destroy($tech, '9615c2ea-9d2d-4343-b880-4e79cb115384');

        $this->assertSame(1, $body['data']['deleted']);
        // ردیف می‌ماند — گزارشِ «چه کسی اعلان را خاموش کرد» به آن نیاز دارد.
        $this->assertSame(1, TechnicianPushToken::count());
        $this->assertNotNull(TechnicianPushToken::firstOrFail()->revoked_at);
        $this->assertSame(0, TechnicianPushToken::query()->active()->count());
    }

    public function test_registering_again_revives_a_revoked_token(): void
    {
        $tech = $this->technician();
        $this->store($tech, $this->najvaPayload());
        $this->destroy($tech, '9615c2ea-9d2d-4343-b880-4e79cb115384');

        // شمارندهٔ خطا را دستی بالا می‌بریم تا صفرشدنش هم سنجیده شود.
        TechnicianPushToken::query()->update(['failed_count' => 4]);

        $this->store($tech, $this->najvaPayload());

        $row = TechnicianPushToken::firstOrFail();
        $this->assertNull($row->revoked_at);
        $this->assertSame(0, $row->failed_count);
        $this->assertSame(1, TechnicianPushToken::query()->active()->count());
    }

    public function test_one_technician_cannot_revoke_anothers_token(): void
    {
        $owner = $this->technician('مالک');
        $stranger = $this->technician('غریبه');
        $this->store($owner, $this->najvaPayload());

        $body = $this->destroy($stranger, '9615c2ea-9d2d-4343-b880-4e79cb115384');

        $this->assertSame(0, $body['data']['deleted']);
        $this->assertNull(TechnicianPushToken::firstOrFail()->revoked_at);
    }

    // ───────────────────────── پرچمِ push_enabled

    public function test_push_stays_disabled_until_the_najva_key_is_configured(): void
    {
        config(['services.najva.api_key' => null]);

        $body = $this->store($this->technician(), $this->najvaPayload());

        $this->assertFalse($body['data']['push_enabled']);
    }

    public function test_push_reports_enabled_once_the_key_exists(): void
    {
        config(['services.najva.api_key' => 'test-key']);

        $body = $this->store($this->technician(), $this->najvaPayload());

        $this->assertTrue($body['data']['push_enabled']);
    }

    // ───────────────────────── دیپلوی بدون مهاجرت

    /**
     * قلبِ این بخش: کد و مهاجرت با هم دیپلوی نمی‌شوند.
     *
     * وقتی `git pull` انجام شده ولی `artisan migrate` نه، این اندپوینت
     * پیش‌تر با خطای SQL می‌خوابید — و چون اپِ تکنسین در هر بار اجرا
     * صدایش می‌زند، عملاً کلِ اپ از کار می‌افتاد.
     */
    public function test_the_app_still_registers_before_the_migration_has_run(): void
    {
        $this->rollBackToPreNajvaSchema();
        $tech = $this->technician();

        $body = $this->store($tech, $this->najvaPayload());

        $this->assertTrue($body['data']['registered']);

        $row = TechnicianPushToken::firstOrFail();
        $this->assertSame('najva', $row->provider);
        $this->assertSame('web', $row->platform);
        $this->assertSame($tech->id, $row->technician_id);
    }

    public function test_logging_out_still_works_before_the_migration_has_run(): void
    {
        $this->rollBackToPreNajvaSchema();
        $tech = $this->technician();
        $this->store($tech, $this->najvaPayload());

        $body = $this->destroy($tech, '9615c2ea-9d2d-4343-b880-4e79cb115384');

        // بدونِ ستونِ حذفِ نرم، به رفتارِ قبلی برمی‌گردیم.
        $this->assertSame(1, $body['data']['deleted']);
        $this->assertSame(0, TechnicianPushToken::count());
    }

    public function test_the_active_scope_does_not_reference_a_missing_column(): void
    {
        $this->rollBackToPreNajvaSchema();
        $this->store($this->technician(), $this->najvaPayload());

        $this->assertSame(1, TechnicianPushToken::query()->active()->count());
    }
}
