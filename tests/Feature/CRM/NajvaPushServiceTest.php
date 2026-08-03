<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Services\Najva\NajvaPushService;
use Tests\TestCase;

/**
 * سرویسِ ارسالِ نجوا — بدونِ تماسِ واقعی.
 *
 * ادعاهای قفل‌شده:
 *   ۱) درخواست همان شکلی است که نجوا می‌خواهد (multipart، هدرِ apiKey،
 *      `tokens[]` تکراری)،
 *   ۲) بیش از هزار توکن تکه می‌شود،
 *   ۳) `InvalidToken` مشترک را باطل می‌کند،
 *   ۴) خطای پیکربندی (۴۱۶/۴۱۸) تکرار نمی‌شود و throw هم نمی‌کند،
 *   ۵) هر توکن یک ردیفِ لاگِ جدا می‌گیرد.
 */
class NajvaPushServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        foreach ([
            'Modules/CRM/Database/Migrations/2026_07_29_130000_create_crm_technician_push_tokens_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_100000_add_najva_columns_to_crm_technician_push_tokens_table.php',
            'Modules/CRM/Database/Migrations/2026_08_03_110000_create_crm_push_logs_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        config([
            'services.najva.api_key' => 'test-key',
            'services.najva.website_id' => '45290',
            'services.najva.base_url' => 'https://push.najva.com',
        ]);
    }

    // ───────────────────────── helpers

    private function service(): NajvaPushService
    {
        return new NajvaPushService;
    }

    private function subscriber(string $token, int $technicianId = 7): TechnicianPushToken
    {
        return TechnicianPushToken::create([
            'technician_id' => $technicianId,
            'token' => $token,
            'provider' => 'najva',
            'platform' => 'web',
            'last_seen_at' => now()->subDay(),
        ]);
    }

    /** پاسخِ موفقِ نجوا برای فهرستی از (توکن، وضعیت، هزینه). */
    private function fakeAccepted(array $rows, string $requestId = 'req-1'): void
    {
        Http::fake([
            '*/v1/send/token/' => Http::response([
                'Message' => 'Request approved.',
                'Entries' => ['request_id' => $requestId, 'tokens' => $rows],
            ], 200),
        ]);
    }

    // ───────────────────────── شکلِ درخواست

    public function test_the_request_carries_the_api_key_header_and_multipart_fields(): void
    {
        $this->subscriber('tok-a');
        $this->fakeAccepted([['token' => 'tok-a', 'status' => 'Sent', 'cost' => 25]]);

        $this->service()->sendToTokens(
            ['tok-a'], 'سفارش جدید', 'مریم کریمی — OR-11', 'https://tg.tamironline.com/orders/11', 24
        );

        Http::assertSent(function (ClientRequest $request) {
            $this->assertSame('test-key', $request->header('apiKey')[0] ?? null);
            $this->assertStringContainsString('multipart/form-data', $request->header('Content-Type')[0] ?? '');

            $names = array_column($request->data(), 'name');
            foreach ([
                'website_id', 'ttl', 'message.title', 'message.body',
                'message.notification_click.click_url', 'tokens[]',
            ] as $field) {
                $this->assertContains($field, $names, "فیلد {$field} در درخواست نیست.");
            }

            return true;
        });
    }

    public function test_a_title_or_body_over_the_limit_is_clipped(): void
    {
        $this->subscriber('tok-a');
        $this->fakeAccepted([['token' => 'tok-a', 'status' => 'Sent', 'cost' => 1]]);

        $this->service()->sendToTokens(['tok-a'], str_repeat('ا', 400), str_repeat('ب', 900), 'https://x.test/a');

        Http::assertSent(function (ClientRequest $request) {
            $fields = collect($request->data())->keyBy('name');

            $this->assertSame(250, mb_strlen($fields['message.title']['contents']));
            $this->assertSame(400, mb_strlen($fields['message.body']['contents']));

            return true;
        });
    }

    // ───────────────────────── تکه‌کردن

    public function test_more_than_a_thousand_tokens_are_split_into_chunks(): void
    {
        $tokens = array_map(fn ($i) => 'tok-'.$i, range(1, 2500));
        $this->fakeAccepted([]);   // پاسخِ خالی؛ فقط تعدادِ درخواست مهم است

        $this->service()->sendToTokens($tokens, 'ت', 'ب', 'https://x.test/a');

        // ۲۵۰۰ توکن ⇒ ۱۰۰۰ + ۱۰۰۰ + ۵۰۰
        Http::assertSentCount(3);
    }

    public function test_duplicate_tokens_are_sent_once(): void
    {
        $this->fakeAccepted([['token' => 'tok-a', 'status' => 'Sent', 'cost' => 1]]);

        $this->service()->sendToTokens(['tok-a', 'tok-a', 'tok-a'], 'ت', 'ب', 'https://x.test/a');

        Http::assertSent(function (ClientRequest $request) {
            $tokens = array_filter($request->data(), fn ($f) => $f['name'] === 'tokens[]');

            $this->assertCount(1, $tokens);

            return true;
        });
    }

    // ───────────────────────── پردازشِ وضعیت

    public function test_an_invalid_token_revokes_the_subscriber(): void
    {
        $good = $this->subscriber('tok-good');
        $dead = $this->subscriber('tok-dead');

        $this->fakeAccepted([
            ['token' => 'tok-good', 'status' => 'Sent', 'cost' => 25],
            ['token' => 'tok-dead', 'status' => 'InvalidToken', 'cost' => 0],
        ]);

        $result = $this->service()->sendToTokens(['tok-good', 'tok-dead'], 'ت', 'ب', 'https://x.test/a');

        $this->assertSame(['tok-good'], $result->sent);
        $this->assertSame(['tok-dead'], $result->invalid);
        $this->assertSame(25, $result->cost);
        // توکنِ باطل شکستِ ارسال نیست؛ پاک‌سازی است.
        $this->assertTrue($result->ok());

        $this->assertNotNull($dead->fresh()->revoked_at);
        $this->assertSame('InvalidToken', $dead->fresh()->last_status);

        $this->assertNull($good->fresh()->revoked_at);
        $this->assertSame('Sent', $good->fresh()->last_status);
        $this->assertSame(0, $good->fresh()->failed_count);
    }

    public function test_a_successful_send_does_not_touch_last_seen_at(): void
    {
        $subscriber = $this->subscriber('tok-a');
        $before = $subscriber->last_seen_at;
        $this->fakeAccepted([['token' => 'tok-a', 'status' => 'Sent', 'cost' => 5]]);

        $this->service()->sendToTokens(['tok-a'], 'ت', 'ب', 'https://x.test/a');

        // «Sent» یعنی نجوا پذیرفت، نه اینکه مرورگر زنده است.
        $this->assertSame(
            $before->toDateTimeString(),
            $subscriber->fresh()->last_seen_at->toDateTimeString()
        );
    }

    public function test_a_token_najva_says_nothing_about_is_counted_as_failed(): void
    {
        $this->subscriber('tok-a');
        $this->subscriber('tok-silent');
        $this->fakeAccepted([['token' => 'tok-a', 'status' => 'Sent', 'cost' => 5]]);

        $result = $this->service()->sendToTokens(['tok-a', 'tok-silent'], 'ت', 'ب', 'https://x.test/a');

        $this->assertSame(['tok-silent'], $result->failed);
        $this->assertFalse($result->ok());
    }

    // ───────────────────────── خطاها

    public function test_a_whitelist_error_is_reported_without_throwing_or_retrying(): void
    {
        $this->subscriber('tok-a');
        Http::fake(['*/v1/send/token/' => Http::response(['Message' => 'IP mismatch'], 416)]);

        $result = $this->service()->sendToTokens(['tok-a'], 'ت', 'ب', 'https://x.test/a');

        $this->assertFalse($result->ok());
        $this->assertSame(416, $result->errorCode);
        $this->assertStringContainsString('وایت‌لیست', $result->errorMessage);
        $this->assertTrue($result->needsAdminAlarm());
        // تکرارِ ۴۱۶ بی‌فایده است — دقیقاً یک درخواست.
        Http::assertSentCount(1);
    }

    public function test_an_out_of_credit_error_raises_an_admin_alarm(): void
    {
        $this->subscriber('tok-a');
        Http::fake(['*/v1/send/token/' => Http::response([], 418)]);

        $result = $this->service()->sendToTokens(['tok-a'], 'ت', 'ب', 'https://x.test/a');

        $this->assertSame(418, $result->errorCode);
        $this->assertTrue($result->needsAdminAlarm());
        $this->assertSame(418, PushLog::firstOrFail()->error_code);
    }

    public function test_a_server_error_is_retried(): void
    {
        $this->subscriber('tok-a');
        Http::fake(['*/v1/send/token/' => Http::response([], 503)]);

        $this->service()->sendToTokens(['tok-a'], 'ت', 'ب', 'https://x.test/a');

        // یک تلاش + دو تکرار.
        Http::assertSentCount(3);
    }

    public function test_nothing_is_sent_when_the_service_is_not_configured(): void
    {
        config(['services.najva.api_key' => '', 'services.najva.website_id' => '']);
        Http::fake();

        $result = $this->service()->sendToTokens(['tok-a'], 'ت', 'ب', 'https://x.test/a');

        $this->assertFalse($result->ok());
        $this->assertStringContainsString('تنظیم نشده', $result->errorMessage);
        Http::assertNothingSent();
    }

    public function test_an_empty_token_list_never_reaches_the_network(): void
    {
        Http::fake();

        $result = $this->service()->sendToTokens([], 'ت', 'ب', 'https://x.test/a');

        $this->assertTrue($result->isEmpty());
        Http::assertNothingSent();
    }

    // ───────────────────────── لاگ

    public function test_every_token_gets_its_own_log_row(): void
    {
        $this->subscriber('tok-a', technicianId: 3);
        $this->subscriber('tok-b', technicianId: 9);

        $this->fakeAccepted([
            ['token' => 'tok-a', 'status' => 'Sent', 'cost' => 25],
            ['token' => 'tok-b', 'status' => 'InvalidToken', 'cost' => 0],
        ], requestId: 'req-42');

        $this->service()->sendToTokens(
            ['tok-a', 'tok-b'], 'سفارش جدید', 'متن', 'https://tg.tamironline.com/orders/5', 24,
            ['event_key' => 'order_assigned_tech', 'order_id' => 5]
        );

        $this->assertSame(2, PushLog::count());

        $a = PushLog::where('token', 'tok-a')->firstOrFail();
        $this->assertSame(3, $a->technician_id);
        $this->assertSame('Sent', $a->status);
        $this->assertSame(25, $a->cost);
        $this->assertSame('req-42', $a->request_id);
        $this->assertSame('order_assigned_tech', $a->event_key);
        $this->assertSame(5, $a->order_id);

        $this->assertSame('InvalidToken', PushLog::where('token', 'tok-b')->firstOrFail()->status);
        $this->assertSame(1, PushLog::query()->succeeded()->count());
    }

    // ───────────────────────── تستِ اتصال

    public function test_the_connection_check_returns_the_numeric_website_id(): void
    {
        Http::fake([
            '*/v1/sender' => Http::response([
                'Entries' => ['websites' => [['address' => 'https://tg.tamironline.com', 'id' => '45290']]],
            ], 200),
        ]);

        $result = $this->service()->sender();

        $this->assertTrue($result['ok']);
        $this->assertSame('45290', $result['websites'][0]['id']);
    }

    public function test_the_connection_check_explains_a_bad_key(): void
    {
        Http::fake(['*/v1/sender' => Http::response([], 403)]);

        $result = $this->service()->sender();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('کلید API', $result['message']);
    }
}
