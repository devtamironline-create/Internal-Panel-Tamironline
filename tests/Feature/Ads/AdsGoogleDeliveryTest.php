<?php

namespace Tests\Feature\Ads;

use App\Models\AdsCallClickEvent;
use App\Services\Ads\Google\GoogleCallConversionUploader;
use App\Services\Ads\Google\GoogleDataManagerService;
use App\Services\Ads\Google\GoogleDataManagerTokenProvider;
use App\Services\Ads\Google\GoogleDeliveryException;
use App\Services\Ads\Google\GoogleHttpClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * فاز ۲ ردیابی تماس — تحویل Conversion به Google Data Manager.
 *
 * قواعد قفل‌شده:
 *   - پروکسی اجباری + پیکربندی ناقص = صفر درخواست (fail-closed، بدون fallback)
 *   - خرابی پروکسی/Google هرگز event را از DB نمی‌اندازد
 *   - transactionId = event_id (retry همان است، کلیک جدید جدید است)
 *   - بدون PII و بدون conversionValue در payload
 */
class AdsGoogleDeliveryTest extends TestCase
{
    private string $credsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_16_210000_create_ads_tracking_tables.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_17_120000_add_google_delivery_columns_to_ads_call_click_events.php',
            '--force' => true,
        ]);

        // credential ساختگی با کلید RSA واقعی — امضای JWT باید واقعاً کار کند.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credsPath = tempnam(sys_get_temp_dir(), 'svc');
        file_put_contents($this->credsPath, json_encode([
            'client_email' => 'svc@test-project.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));

        config()->set('ads_tracking.google', [
            'upload_enabled' => true,
            'validate_only' => false,
            'customer_id' => '2274478841',
            'conversion_action_id' => '7724022711',
            'conversion_action_name' => 'TO | SERVER CALL CLICK | OMD',
            'credentials_path' => $this->credsPath,
            'oauth_token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/datamanager',
            'base_url' => 'https://datamanager.googleapis.com/v1',
            'proxy' => ['enabled' => true, 'url' => 'http://136.244.91.19:3128', 'username' => '', 'password' => ''],
            'batch_size' => 1,
            'request_timeout' => 30,
            'connect_timeout' => 10,
            'max_attempts' => 10,
            'token_safety_margin' => 300,
        ]);
        config()->set('ads_tracking.google_upload_enabled', true);
    }

    protected function tearDown(): void
    {
        @unlink($this->credsPath);
        parent::tearDown();
    }

    private function event(array $extra = []): AdsCallClickEvent
    {
        static $i = 0;
        $i++;

        return AdsCallClickEvent::forceCreate(array_merge([
            'event_id' => 'call_TEST'.$i.'_'.uniqid(),
            'client_source' => 'website',
            'gclid' => 'Cj0KCQtestGclid'.$i,
            'event_time' => now()->subMinutes(5),
            'google_status' => 'pending',
        ], $extra));
    }

    private function fakeGoogleOk(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            'datamanager.googleapis.com/*' => Http::response(['requestId' => 'req-123-abc']),
        ]);
    }

    // ───────────────────────── پروکسی و fail-closed

    public function test_transport_options_carry_the_proxy_for_both_schemes(): void
    {
        $options = GoogleHttpClient::fromConfig()->options();

        $this->assertSame('http://136.244.91.19:3128', $options['proxy']['https']);
        $this->assertSame('http://136.244.91.19:3128', $options['proxy']['http']);
        $this->assertTrue($options['verify'], 'TLS verification هرگز نباید خاموش شود.');
    }

    public function test_proxy_credentials_are_injected_into_the_proxy_url(): void
    {
        config()->set('ads_tracking.google.proxy.username', 'tamir');
        config()->set('ads_tracking.google.proxy.password', 'p@ss');

        $options = GoogleHttpClient::fromConfig()->options();

        $this->assertSame('http://tamir:p%40ss@136.244.91.19:3128', $options['proxy']['https']);
    }

    public function test_enabled_but_incomplete_proxy_fails_closed_with_zero_requests(): void
    {
        config()->set('ads_tracking.google.proxy.url', '');
        Http::fake();

        $event = $this->event();
        $stats = GoogleCallConversionUploader::fromConfig()->uploadPending();

        // هیچ درخواستی — نه مستقیم، نه از جای دیگر.
        Http::assertNothingSent();

        $event->refresh();
        $this->assertSame('pending', $event->google_status, 'event باید برای retry بماند.');
        $this->assertSame('PROXY_UNAVAILABLE', $event->google_error_code);
        $this->assertNotNull($event->google_next_retry_at);
        $this->assertSame(1, $stats['retried']);
    }

    public function test_a_dead_proxy_preserves_the_event_and_never_falls_back_to_direct(): void
    {
        // اتصال شکست می‌خورد (پروکسی down) — event می‌ماند و بعداً retry می‌شود.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused: 136.244.91.19:3128'));

        $event = $this->event();
        GoogleCallConversionUploader::fromConfig()->uploadPending();

        $event->refresh();
        $this->assertSame('pending', $event->google_status);
        $this->assertSame('CONNECTION', $event->google_error_code);
        $this->assertSame(1, $event->google_attempts);
        $this->assertNotNull($event->google_next_retry_at, 'باید backoff داشته باشد.');
        $this->assertDatabaseCount('ads_call_click_events', 1); // هرگز حذف نمی‌شود
    }

    // ───────────────────────── OAuth

    public function test_the_token_request_goes_to_oauth_endpoint_and_is_cached(): void
    {
        $this->fakeGoogleOk();

        $tokens = GoogleDataManagerTokenProvider::fromConfig();
        $this->assertSame('ya29.test-token', $tokens->token());
        $this->assertSame('ya29.test-token', $tokens->token()); // از کش

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && str_contains((string) $request['assertion'], '.'); // JWT سه‌بخشی
        });
    }

    public function test_an_oauth_server_error_is_retryable(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'internal'], 500)]);

        try {
            GoogleDataManagerTokenProvider::fromConfig()->token();
            $this->fail('انتظار exception بود.');
        } catch (GoogleDeliveryException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_an_oauth_invalid_grant_is_permanent(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            GoogleDataManagerTokenProvider::fromConfig()->token();
            $this->fail('انتظار exception بود.');
        } catch (GoogleDeliveryException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_missing_credentials_fail_safely_without_any_request(): void
    {
        config()->set('ads_tracking.google.credentials_path', '/nonexistent/creds.json');
        Http::fake();

        $event = $this->event();
        GoogleCallConversionUploader::fromConfig()->uploadPending();

        Http::assertNothingSent();
        $event->refresh();
        $this->assertSame('failed', $event->google_status, 'خطای credential دائمی است.');
        $this->assertSame('CREDENTIALS_MISSING', $event->google_error_code);
    }

    // ───────────────────────── mapping و payload

    public function test_the_payload_maps_our_contract_and_carries_no_pii_or_value(): void
    {
        $this->fakeGoogleOk();
        $event = $this->event([
            'phone_number' => '02177612345',
            'event_time' => '2026-08-17 10:30:00', // Asia/Tehran
        ]);

        GoogleCallConversionUploader::fromConfig()->uploadPending();

        Http::assertSent(function ($request) use ($event) {
            if (! str_contains($request->url(), 'datamanager.googleapis.com/v1/events:ingest')) {
                return false;
            }
            $body = $request->data();
            $sent = $body['events'][0];

            return $body['destinations'][0]['operatingAccount'] === ['accountType' => 'GOOGLE_ADS', 'accountId' => '2274478841']
                && $body['destinations'][0]['productDestinationId'] === '7724022711'
                && $body['validateOnly'] === false
                && $sent['transactionId'] === $event->event_id
                && $sent['eventSource'] === 'WEB'
                && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $sent['eventTimestamp']) === 1
                && $sent['adIdentifiers'] === ['gclid' => $event->gclid]
                && ! array_key_exists('conversionValue', $sent)
                && ! array_key_exists('currency', $sent)
                && ! str_contains(json_encode($body), '02177612345') // هیچ PII
                && $request->hasHeader('Authorization', 'Bearer ya29.test-token');
        });
    }

    public function test_wbraid_and_gbraid_are_mapped_when_gclid_is_absent(): void
    {
        $service = GoogleDataManagerService::fromConfig();

        $w = $service->buildEvent($this->event(['gclid' => null, 'wbraid' => 'wb-123']));
        $this->assertSame(['wbraid' => 'wb-123'], $w['adIdentifiers']);

        $g = $service->buildEvent($this->event(['gclid' => null, 'gbraid' => 'gb-456']));
        $this->assertSame(['gbraid' => 'gb-456'], $g['adIdentifiers']);
    }

    public function test_an_event_without_any_google_identifier_is_never_sent(): void
    {
        $this->fakeGoogleOk();
        $event = $this->event(['gclid' => null, 'google_status' => 'pending']);

        $stats = GoogleCallConversionUploader::fromConfig()->uploadPending();

        Http::assertNothingSent();
        $this->assertSame(0, $stats['claimed']);
        $this->assertSame('pending', $event->fresh()->google_status);
    }

    // ───────────────────────── چرخهٔ وضعیت

    public function test_successful_ingest_stores_the_request_id_and_moves_to_processing(): void
    {
        $this->fakeGoogleOk();
        $event = $this->event();

        GoogleCallConversionUploader::fromConfig()->uploadPending();

        $event->refresh();
        $this->assertSame('processing', $event->google_status);
        $this->assertSame('req-123-abc', $event->google_request_id);
        $this->assertSame(1, $event->google_attempts);
        $this->assertNotNull($event->google_last_attempt_at);
        $this->assertNull($event->google_error);
    }

    public function test_validate_only_mode_validates_but_keeps_the_event_pending(): void
    {
        config()->set('ads_tracking.google.validate_only', true);
        $this->fakeGoogleOk();
        $event = $this->event();

        $stats = GoogleCallConversionUploader::fromConfig()->uploadPending();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'events:ingest')
            || $request->data()['validateOnly'] === true);

        $event->refresh();
        $this->assertSame('pending', $event->google_status, 'در حالت validate هیچ conversion واقعی ساخته نمی‌شود.');
        $this->assertNull($event->google_request_id);
        $this->assertSame(1, $stats['validated']);
    }

    public function test_request_status_success_marks_the_event_uploaded(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response(['requestStatusPerDestination' => [['requestStatus' => 'SUCCESS']]]),
        ]);
        $event = $this->event(['google_status' => 'processing', 'google_request_id' => 'req-9', 'google_error' => 'old']);

        $stats = GoogleCallConversionUploader::fromConfig()->pollProcessing();

        $event->refresh();
        $this->assertSame('uploaded', $event->google_status);
        $this->assertNotNull($event->google_uploaded_at);
        $this->assertNull($event->google_error);
        $this->assertSame(1, $stats['uploaded']);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'requestStatus:retrieve')
            || str_contains($request->url(), 'requestId=req-9'));
    }

    public function test_still_processing_keeps_the_status_and_is_polled_later(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response(['requestStatusPerDestination' => [['requestStatus' => 'PROCESSING']]]),
        ]);
        $event = $this->event([
            'google_status' => 'processing', 'google_request_id' => 'req-9',
            'google_last_attempt_at' => now()->subHour(),
        ]);

        GoogleCallConversionUploader::fromConfig()->pollProcessing();

        $event->refresh();
        $this->assertSame('processing', $event->google_status);
        $this->assertNotNull($event->google_last_status_checked_at);
    }

    public function test_a_permanently_failed_request_is_marked_failed_with_the_error(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response([
                'requestStatusPerDestination' => [[
                    'requestStatus' => 'FAILED',
                    'errorInfo' => [['reason' => 'INVALID_ARGUMENT', 'errorMessage' => 'bad identifier']],
                ]],
            ]),
        ]);
        $event = $this->event(['google_status' => 'processing', 'google_request_id' => 'req-9', 'google_last_attempt_at' => now()]);

        GoogleCallConversionUploader::fromConfig()->pollProcessing();

        $event->refresh();
        $this->assertSame('failed', $event->google_status);
        $this->assertStringContainsString('INVALID_ARGUMENT', $event->google_error);
    }

    public function test_a_transient_failed_request_goes_back_to_pending_for_retry(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response([
                'requestStatusPerDestination' => [[
                    'requestStatus' => 'FAILED',
                    'errorInfo' => [['reason' => 'UNAVAILABLE', 'errorMessage' => 'service unavailable, retry later']],
                ]],
            ]),
        ]);
        $event = $this->event(['google_status' => 'processing', 'google_request_id' => 'req-9', 'google_attempts' => 1, 'google_last_attempt_at' => now()]);

        GoogleCallConversionUploader::fromConfig()->pollProcessing();

        $event->refresh();
        $this->assertSame('pending', $event->google_status);
        $this->assertNotNull($event->google_next_retry_at);
    }

    public function test_poll_transport_failure_does_not_change_the_event_status(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('proxy down'));
        $event = $this->event(['google_status' => 'processing', 'google_request_id' => 'req-9']);

        GoogleCallConversionUploader::fromConfig()->pollProcessing();

        $this->assertSame('processing', $event->fresh()->google_status);
    }

    // ───────────────────────── idempotency و هم‌زمانی

    public function test_retrying_the_same_event_reuses_the_same_transaction_id(): void
    {
        $event = $this->event();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);

        for ($round = 1; $round <= 3; $round++) {
            $event->fresh()->forceFill(['google_next_retry_at' => null])->save();
            GoogleCallConversionUploader::fromConfig()->uploadPending();
        }

        $sentTransactionIds = [];
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'events:ingest')) {
                $sentTransactionIds[] = $request->data()['events'][0]['transactionId'];
            }
        }

        $this->assertCount(3, $sentTransactionIds);
        $this->assertSame([$event->event_id, $event->event_id, $event->event_id], $sentTransactionIds,
            'retry همان transactionId را می‌فرستد — سمت Google یک conversion منطقی است.');
    }

    public function test_three_calls_on_one_gclid_are_three_distinct_submissions(): void
    {
        $this->fakeGoogleOk();
        $gclid = 'Cj0KCQsharedGclidABC';
        $events = collect([1, 2, 3])->map(fn () => $this->event(['gclid' => $gclid]));

        GoogleCallConversionUploader::fromConfig()->uploadPending();

        $sent = [];
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'events:ingest')) {
                $sent[] = $request->data()['events'][0]['transactionId'];
            }
        }

        $this->assertCount(3, $sent, 'Count=Every — سه کلیک واقعی، سه conversion.');
        $this->assertSame($events->pluck('event_id')->sort()->values()->all(), collect($sent)->sort()->values()->all());
    }

    public function test_two_workers_cannot_claim_the_same_event(): void
    {
        $event = $this->event();
        $uploader = GoogleCallConversionUploader::fromConfig();

        $this->assertTrue($uploader->claim($event));
        $this->assertFalse($uploader->claim(AdsCallClickEvent::find($event->id)), 'claim دوم باید شکست بخورد.');
    }

    public function test_stuck_sending_events_are_recovered(): void
    {
        $event = $this->event(['google_status' => 'sending', 'google_last_attempt_at' => now()->subHour()]);
        $fresh = $this->event(['google_status' => 'sending', 'google_last_attempt_at' => now()]);

        GoogleCallConversionUploader::fromConfig()->recoverStuckSending();

        $this->assertSame('pending', $event->fresh()->google_status);
        $this->assertSame('sending', $fresh->fresh()->google_status, 'sending تازه نباید دست بخورد.');
    }

    // ───────────────────────── سوییچ‌ها

    public function test_the_upload_command_is_a_noop_while_the_switch_is_off(): void
    {
        config()->set('ads_tracking.google.upload_enabled', false);
        Http::fake();
        $this->event();

        $this->artisan('ads:google-upload')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_max_attempts_moves_the_event_to_failed(): void
    {
        config()->set('ads_tracking.google.max_attempts', 2);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'datamanager.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);
        $event = $this->event(['google_attempts' => 1]);

        GoogleCallConversionUploader::fromConfig()->uploadPending();

        $this->assertSame('failed', $event->fresh()->google_status, 'تلاش دوم = سقف → failed.');
    }

    public function test_admin_retry_failed_requeues_without_resetting_identity(): void
    {
        $event = $this->event(['google_status' => 'failed', 'google_attempts' => 10, 'google_error' => 'x']);

        $count = GoogleCallConversionUploader::fromConfig()->retryFailed();

        $this->assertSame(1, $count);
        $event->refresh();
        $this->assertSame('pending', $event->google_status);
        $this->assertSame(0, $event->google_attempts);
        $this->assertSame($event->event_id, $event->fresh()->event_id, 'event_id (transactionId) هرگز عوض نمی‌شود.');
    }
}
