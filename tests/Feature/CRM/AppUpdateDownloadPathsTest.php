<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * راه‌های جایگزینِ دانلود اپ اندروید — برای WebViewهایی که دانلود مستقیم،
 * target=_blank و intent:// را می‌بلعند:
 *
 *   ۱) آدرس کوتاه panel.tamironline.com/app → redirect به فایل APK
 *      (قابل تایپ دستی در مرورگر)،
 *   ۲) پیامکِ لینک به موبایل خودِ تکنسینِ لاگین‌شده (لینک پیامک همیشه
 *      در مرورگر سیستم باز می‌شود).
 */
class AppUpdateDownloadPathsTest extends TestCase
{
    private const APK = 'https://panel.tamironline.com/karbalad-ver4.apk';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('mobile')->nullable();
            $t->string('status', 30)->default('active');
            $t->string('remember_token', 100)->nullable();
            $t->timestamps();
        });

        foreach (['settings', 'crm_settings'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('key')->unique();
                $t->text('value')->nullable();
            });
        }
    }

    public function test_the_short_url_redirects_to_the_apk(): void
    {
        $this->get('/app')->assertRedirect(self::APK);
    }

    public function test_a_logged_in_technician_can_sms_the_link_to_their_own_mobile(): void
    {
        config(['sms.kavenegar.api_key' => 'test-key', 'sms.kavenegar.sender' => '10001', 'sms.proxy.enabled' => false]);
        Http::fake(['*' => Http::response(['return' => ['status' => 200], 'entries' => []], 200)]);

        $tech = Technician::forceCreate(['mobile' => '09191216486', 'status' => 'active']);

        $this->actingAs($tech, 'tech')
            ->post('/tech/app-update-sms')
            ->assertRedirect()
            ->assertSessionHas('app_update_sms_sent');

        Http::assertSent(function ($request) {
            return str_contains($request['receptor'] ?? '', '09191216486')
                && str_contains($request['message'] ?? '', self::APK);
        });
    }

    public function test_a_technician_without_a_mobile_gets_a_clear_error(): void
    {
        $tech = Technician::forceCreate(['mobile' => '', 'status' => 'active']);

        $this->actingAs($tech, 'tech')
            ->post('/tech/app-update-sms')
            ->assertRedirect()
            ->assertSessionHas('app_update_sms_error');
    }

    public function test_guests_cannot_use_the_sms_endpoint(): void
    {
        $this->post('/tech/app-update-sms')->assertRedirect();
        $this->assertGuest('tech');
    }
}
