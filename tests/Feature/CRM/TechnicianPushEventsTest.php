<?php

namespace Tests\Feature\CRM;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\PushEvent;
use Modules\CRM\Jobs\SendTechnicianPush;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\PushLog;
use Modules\CRM\Models\TechnicianPushToken;
use Modules\CRM\Support\TechPushPolicy;
use Tests\TestCase;

/**
 * رویدادهای پوشِ تکنسین — ضدِتکرار، ساعتِ مجاز، و چند مرورگر.
 *
 * Job مستقیم اجرا می‌شود (`handle`) نه از راهِ صف: تصمیمِ «صف یا بعد از
 * پاسخ» جای دیگری تست می‌شود و این‌جا منطقِ خودِ ارسال موضوع است.
 */
class TechnicianPushEventsTest extends TestCase
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
            'Modules/CRM/Database/Migrations/2026_08_03_120000_add_fingerprint_to_crm_push_logs_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        config([
            'services.najva.api_key' => 'test-key',
            'services.najva.website_id' => '45290',
            'services.tech_app.url' => 'https://tg.tamironline.com',
        ]);
    }

    // ───────────────────────── helpers

    private function token(string $token, int $technicianId = 7, bool $revoked = false): TechnicianPushToken
    {
        return TechnicianPushToken::create([
            'technician_id' => $technicianId,
            'token' => $token,
            'provider' => 'najva',
            'platform' => 'web',
            'revoked_at' => $revoked ? now() : null,
        ]);
    }

    private function accept(array $tokens): void
    {
        Http::fake([
            '*/v1/send/token/' => Http::response([
                'Message' => 'Request approved.',
                'Entries' => [
                    'request_id' => 'req-1',
                    'tokens' => array_map(fn ($t) => ['token' => $t, 'status' => 'Sent', 'cost' => 25], $tokens),
                ],
            ], 200),
        ]);
    }

    private function fire(SendTechnicianPush $job): void
    {
        $job->handle(app(\Modules\CRM\Services\Najva\NajvaPushService::class));
    }

    private function assignJob(int $technicianId = 7, string $fingerprint = 'assign:1000'): SendTechnicianPush
    {
        return new SendTechnicianPush(
            $technicianId,
            PushEvent::OrderAssigned,
            ['order_code' => 'OR-11', 'customer_name' => 'مریم کریمی', 'device_name' => 'یخچال'],
            orderId: 11,
            fingerprint: $fingerprint,
        );
    }

    // ───────────────────────── محتوای اعلان

    public function test_the_assignment_push_carries_the_documented_title_body_and_link(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire($this->assignJob());

        Http::assertSent(function (ClientRequest $request) {
            $fields = collect($request->data())->keyBy('name');

            $this->assertSame('سفارش جدید', $fields['message.title']['contents']);
            $this->assertSame('مریم کریمی — OR-11 · یخچال', $fields['message.body']['contents']);
            $this->assertSame(
                'https://tg.tamironline.com/orders/11',
                $fields['message.notification_click.click_url']['contents']
            );
            $this->assertSame('24', $fields['ttl']['contents']);

            return true;
        });
    }

    public function test_the_sla_event_uses_a_six_hour_ttl(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire(new SendTechnicianPush(
            7, PushEvent::SlaDue, ['order_code' => 'OR-9'], orderId: 9, fingerprint: 'sla:new:5'
        ));

        Http::assertSent(function (ClientRequest $request) {
            $fields = collect($request->data())->keyBy('name');
            // مهلت بعد از شش ساعت بی‌معنا می‌شود.
            $this->assertSame('6', $fields['ttl']['contents']);

            return true;
        });
    }

    public function test_an_announcement_links_to_the_announcements_page(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire(new SendTechnicianPush(7, PushEvent::Announcement, ['title' => 'تعطیلی پنج‌شنبه']));

        Http::assertSent(function (ClientRequest $request) {
            $fields = collect($request->data())->keyBy('name');

            $this->assertSame('اطلاعیهٔ جدید', $fields['message.title']['contents']);
            $this->assertSame('تعطیلی پنج‌شنبه', $fields['message.body']['contents']);
            $this->assertSame(
                'https://tg.tamironline.com/announcements',
                $fields['message.notification_click.click_url']['contents']
            );

            return true;
        });
    }

    public function test_an_unknown_variable_does_not_leak_a_raw_placeholder(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        // بدونِ customer_name و device_name.
        $this->fire(new SendTechnicianPush(
            7, PushEvent::OrderAssigned, ['order_code' => 'OR-11'], orderId: 11, fingerprint: 'assign:1'
        ));

        Http::assertSent(function (ClientRequest $request) {
            $body = collect($request->data())->keyBy('name')['message.body']['contents'];

            $this->assertStringNotContainsString('{', $body);

            return true;
        });
    }

    // ───────────────────────── چند مرورگر

    public function test_every_browser_of_one_technician_goes_in_a_single_request(): void
    {
        $this->token('tok-desktop');
        $this->token('tok-phone');
        $this->accept(['tok-desktop', 'tok-phone']);

        $this->fire($this->assignJob());

        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $request) {
            $tokens = array_filter($request->data(), fn ($f) => $f['name'] === 'tokens[]');

            $this->assertCount(2, $tokens);

            return true;
        });
    }

    public function test_a_revoked_browser_is_skipped(): void
    {
        $this->token('tok-live');
        $this->token('tok-dead', revoked: true);
        $this->accept(['tok-live']);

        $this->fire($this->assignJob());

        Http::assertSent(function (ClientRequest $request) {
            $tokens = array_column(
                array_filter($request->data(), fn ($f) => $f['name'] === 'tokens[]'),
                'contents'
            );

            $this->assertSame(['tok-live'], array_values($tokens));

            return true;
        });
    }

    public function test_a_technician_without_any_token_never_reaches_the_network(): void
    {
        Http::fake();

        $this->fire($this->assignJob(technicianId: 99));

        Http::assertNothingSent();
    }

    // ───────────────────────── ضدِتکرار

    public function test_the_same_assignment_is_not_pushed_twice(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire($this->assignJob());
        $this->fire($this->assignJob());

        Http::assertSentCount(1);
        $this->assertSame(1, PushLog::count());
    }

    public function test_a_re_assignment_is_a_new_event_and_pushes_again(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire($this->assignJob(fingerprint: 'assign:1000'));
        // تخصیصِ مجدد ⇒ assigned_at تازه ⇒ اثرِ انگشتِ تازه.
        $this->fire($this->assignJob(fingerprint: 'assign:2000'));

        Http::assertSentCount(2);
    }

    public function test_a_failed_send_does_not_block_a_later_retry(): void
    {
        $this->token('tok-a');

        // سه ۵۰۳ (یک تلاش + دو تکرارِ داخلیِ سرویس) و بعد یک موفق.
        // `Http::fake()` دوباره‌فراخوانی، استابِ قبلی را جایگزین نمی‌کند
        // بلکه با آن ادغام می‌شود — پس ترتیب باید صریح باشد.
        Http::fake([
            '*/v1/send/token/' => Http::sequence()
                ->push([], 503)
                ->push([], 503)
                ->push([], 503)
                ->push([
                    'Message' => 'Request approved.',
                    'Entries' => [
                        'request_id' => 'req-2',
                        'tokens' => [['token' => 'tok-a', 'status' => 'Sent', 'cost' => 25]],
                    ],
                ], 200),
        ]);

        $this->fire($this->assignJob());
        // ارسالِ ناموفق «انجام‌شده» حساب نمی‌شود؛ تلاشِ بعدی باید برود.
        $this->fire($this->assignJob());

        $this->assertSame(1, PushLog::query()->succeeded()->count());
    }

    public function test_an_announcement_is_not_deduplicated(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire(new SendTechnicianPush(7, PushEvent::Announcement, ['title' => 'اول']));
        $this->fire(new SendTechnicianPush(7, PushEvent::Announcement, ['title' => 'دوم']));

        // دو اطلاعیهٔ مختلف، دو اعلان.
        Http::assertSentCount(2);
    }

    // ───────────────────────── ساعتِ مجاز

    public function test_an_announcement_is_held_outside_the_allowed_window(): void
    {
        CrmSetting::set(TechPushPolicy::START_KEY, '8');
        CrmSetting::set(TechPushPolicy::END_KEY, '22');

        $this->assertFalse(TechPushPolicy::withinWindow(
            PushEvent::Announcement,
            CarbonImmutable::parse('2026-08-03 03:00')
        ));
        $this->assertTrue(TechPushPolicy::withinWindow(
            PushEvent::Announcement,
            CarbonImmutable::parse('2026-08-03 10:00')
        ));
    }

    public function test_a_deadline_ignores_the_allowed_window(): void
    {
        CrmSetting::set(TechPushPolicy::START_KEY, '8');
        CrmSetting::set(TechPushPolicy::END_KEY, '22');

        // مهلتی که تا صبح نگه داشته شود، همان مهلتی است که از دست رفته.
        $this->assertTrue(TechPushPolicy::withinWindow(
            PushEvent::SlaDue,
            CarbonImmutable::parse('2026-08-03 03:00')
        ));
        $this->assertTrue(TechPushPolicy::withinWindow(
            PushEvent::OrderAssigned,
            CarbonImmutable::parse('2026-08-03 03:00')
        ));
    }

    public function test_the_quiet_window_actually_stops_the_send(): void
    {
        $this->token('tok-a');
        Http::fake();
        CrmSetting::set(TechPushPolicy::START_KEY, '8');
        CrmSetting::set(TechPushPolicy::END_KEY, '22');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 03:00', config('app.timezone')));

        try {
            $this->fire(new SendTechnicianPush(7, PushEvent::Announcement, ['title' => 'نیمه‌شب']));
            Http::assertNothingSent();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    // ───────────────────────── مقاومت

    public function test_a_najva_outage_never_escapes_the_job(): void
    {
        $this->token('tok-a');
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        // هیچ استثنایی نباید بیرون بیاید — Job در `afterResponse` اجرا
        // می‌شود و کسی نیست که آن را بگیرد.
        $this->fire($this->assignJob());

        $this->assertSame(PushLog::STATUS_FAILED, PushLog::firstOrFail()->status);
    }

    public function test_the_fingerprint_is_recorded_on_the_log_row(): void
    {
        $this->token('tok-a');
        $this->accept(['tok-a']);

        $this->fire($this->assignJob(fingerprint: 'assign:7777'));

        $log = PushLog::firstOrFail();
        $this->assertSame('assign:7777', $log->fingerprint);
        $this->assertSame('order_assigned_tech', $log->event_key);
        $this->assertSame(11, $log->order_id);
    }
}
