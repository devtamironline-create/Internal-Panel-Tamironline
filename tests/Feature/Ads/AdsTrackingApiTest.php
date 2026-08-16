<?php

namespace Tests\Feature\Ads;

use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * دو endpoint عمومیِ ردیابی — قواعد قفل‌شده:
 *
 *   - attribution: کلیکِ جدیدِ Google هرگز رکوردِ قبلی را بازنویسی نمی‌کند.
 *   - call-click: event_id یکتا (retry = یک رکورد)، سه کلیکِ واقعی = سه
 *     رکورد، بدونِ attribution هم ثبت می‌شود، snapshot از DB خودمان.
 *   - هیچ تماسی به Google نمی‌رود (google_upload خاموش).
 */
class AdsTrackingApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_16_210000_create_ads_tracking_tables.php',
            '--force' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function attributionPayload(array $extra = []): array
    {
        return array_merge([
            'client_source' => 'website',
            'gclid' => 'Cj0KCQtestGclid123',
            'campaign_id' => '123',
            'adgroup_id' => '456',
            'keyword' => 'تعمیر لباسشویی بوش',
            'match_type' => 'e',
            'device' => 'm',
            'network' => 'g',
            'creative_id' => '789',
            'landing_url' => 'https://tamironline.com/repair/bosch?x=1',
            'referrer' => 'https://www.google.com/',
        ], $extra);
    }

    // ───────────────────────── Attribution

    public function test_creates_a_website_attribution_with_a_public_ulid(): void
    {
        $response = $this->postJson('/api/ads/attribution', $this->attributionPayload())
            ->assertCreated()
            ->assertJson(['ok' => true, 'created' => true, 'has_google_id' => true]);

        $row = AdsAttribution::firstOrFail();
        $this->assertSame($row->attribution_id, $response->json('attribution_id'));
        $this->assertSame(26, strlen($row->attribution_id)); // ULID — نه id افزایشی
        $this->assertSame('website', $row->client_source);
        $this->assertSame('/repair/bosch', $row->landing_path);
        $this->assertNotNull($row->first_seen_at);
        $this->assertNotNull($row->expires_at);
        $this->assertEqualsWithDelta(90, now()->diffInDays($row->expires_at), 1); // TTL از config
        // IP خام ذخیره نمی‌شود — فقط هش.
        $this->assertNotNull($row->ip_hash);
        $this->assertSame(64, strlen($row->ip_hash));
    }

    public function test_creates_a_pwa_attribution(): void
    {
        $this->postJson('/api/ads/attribution', $this->attributionPayload([
            'client_source' => 'pwa', 'landing_url' => 'https://app.tamironline.com/home',
        ]))->assertCreated();

        $this->assertSame('pwa', AdsAttribution::firstOrFail()->client_source);
    }

    public function test_wbraid_and_gbraid_are_accepted_and_a_google_id_is_not_mandatory(): void
    {
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['gclid' => null, 'wbraid' => 'wb-1']))
            ->assertCreated()->assertJson(['has_google_id' => true]);
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['gclid' => null, 'gbraid' => 'gb-1']))
            ->assertCreated()->assertJson(['has_google_id' => true]);
        // بدونِ هیچ شناسهٔ Google هم قابلِ ثبت است — وضعیتش مشخص می‌ماند.
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['gclid' => null]))
            ->assertCreated()->assertJson(['has_google_id' => false]);

        $this->assertSame(3, AdsAttribution::count());
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['landing_url' => 'not-a-url']))
            ->assertUnprocessable();
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['client_source' => 'evil']))
            ->assertUnprocessable();
        $this->postJson('/api/ads/attribution', $this->attributionPayload([
            'metadata' => array_fill_keys(range(1, 30), 'x'), // بزرگ‌تر از سقف
        ]))->assertUnprocessable();

        $this->assertSame(0, AdsAttribution::count());
    }

    public function test_a_known_attribution_is_touched_not_duplicated(): void
    {
        $id = $this->postJson('/api/ads/attribution', $this->attributionPayload())->json('attribution_id');
        $before = AdsAttribution::firstOrFail()->last_seen_at;

        $this->travel(10)->minutes();
        $this->postJson('/api/ads/attribution', $this->attributionPayload(['attribution_id' => $id]))
            ->assertOk()->assertJson(['created' => false, 'attribution_id' => $id]);

        $this->assertSame(1, AdsAttribution::count());
        $this->assertTrue(AdsAttribution::firstOrFail()->last_seen_at->gt($before));
    }

    /** کلیکِ تبلیغاتیِ جدید = رکوردِ جدید — تاریخچه بازنویسی نمی‌شود. */
    public function test_a_new_google_click_never_overwrites_history(): void
    {
        $id = $this->postJson('/api/ads/attribution', $this->attributionPayload())->json('attribution_id');

        $response = $this->postJson('/api/ads/attribution', $this->attributionPayload([
            'attribution_id' => $id, 'gclid' => 'Cj0KCQanotherClick999',
        ]))->assertCreated()->assertJson(['created' => true]);

        $this->assertSame(2, AdsAttribution::count());
        $this->assertNotSame($id, $response->json('attribution_id'));
        $this->assertSame('Cj0KCQtestGclid123', AdsAttribution::where('attribution_id', $id)->first()->gclid);
    }

    // ───────────────────────── Call Click

    private function createAttribution(array $extra = []): string
    {
        return $this->postJson('/api/ads/attribution', $this->attributionPayload($extra))->json('attribution_id');
    }

    public function test_records_an_attributed_call_click_with_a_server_side_snapshot(): void
    {
        $attributionId = $this->createAttribution();

        $this->postJson('/api/ads/call-click', [
            'event_id' => 'call_01JTEST1',
            'attribution_id' => $attributionId,
            'client_source' => 'website',
            'page_url' => 'https://tamironline.com/repair/bosch',
            'placement' => 'header_mobile',
            'phone_number' => '02112345678',
            // مرورگر gclid نمی‌فرستد — snapshot باید از DB بیاید.
        ])->assertCreated()->assertJson(['ok' => true, 'tracked' => true, 'attributed' => true]);

        $event = AdsCallClickEvent::firstOrFail();
        $this->assertSame('Cj0KCQtestGclid123', $event->gclid);
        $this->assertSame('pending', $event->google_status);
        $this->assertSame('/repair/bosch', $event->page_path);
        $this->assertNotNull($event->ads_attribution_id);
        $this->assertNull($event->google_uploaded_at); // هیچ آپلودی انجام نشده
    }

    public function test_a_call_click_without_attribution_is_never_lost(): void
    {
        $this->postJson('/api/ads/call-click', [
            'event_id' => 'call_01JNOATTR', 'client_source' => 'pwa',
        ])->assertCreated()->assertJson(['tracked' => true, 'attributed' => false]);

        $event = AdsCallClickEvent::firstOrFail();
        $this->assertSame('not_ready', $event->google_status);
        $this->assertSame('pwa', $event->client_source);
    }

    public function test_an_invalid_attribution_id_does_not_lose_the_call_event(): void
    {
        $this->postJson('/api/ads/call-click', [
            'event_id' => 'call_01JBADREF', 'attribution_id' => '01jnope_not_found',
            'client_source' => 'website',
        ])->assertCreated()->assertJson(['tracked' => true, 'attributed' => false]);

        $this->assertSame(1, AdsCallClickEvent::count());
        $this->assertSame('not_ready', AdsCallClickEvent::firstOrFail()->google_status);
    }

    /** retry مرورگر — همان event_id دوبار = فقط یک رکورد. */
    public function test_the_same_event_id_twice_creates_one_event(): void
    {
        $payload = ['event_id' => 'call_01JRETRY', 'client_source' => 'website'];

        $this->postJson('/api/ads/call-click', $payload)->assertCreated()->assertJson(['duplicate' => false]);
        $this->postJson('/api/ads/call-click', $payload)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, AdsCallClickEvent::count());
    }

    /** سناریوی کلیدی: یک gclid + سه event_id یکتا = سه رکوردِ تماس. */
    public function test_one_gclid_with_three_unique_event_ids_creates_three_events(): void
    {
        $attributionId = $this->createAttribution();

        foreach (['call_001', 'call_002', 'call_003'] as $eventId) {
            $this->postJson('/api/ads/call-click', [
                'event_id' => $eventId,
                'attribution_id' => $attributionId,
                'client_source' => 'website',
            ])->assertCreated();
        }

        $this->assertSame(3, AdsCallClickEvent::count());
        $this->assertSame(3, AdsCallClickEvent::where('gclid', 'Cj0KCQtestGclid123')->count());
    }

    /** انتقالِ attribution از سایت به PWA — همان رکورد resolve می‌شود. */
    public function test_a_website_attribution_resolves_for_a_pwa_call(): void
    {
        $attributionId = $this->createAttribution(['client_source' => 'website']);

        $this->postJson('/api/ads/call-click', [
            'event_id' => 'call_01JPWA', 'attribution_id' => $attributionId, 'client_source' => 'pwa',
        ])->assertCreated()->assertJson(['attributed' => true]);

        $event = AdsCallClickEvent::firstOrFail();
        $this->assertSame('pwa', $event->client_source);       // منبعِ تماس: PWA
        $this->assertSame('Cj0KCQtestGclid123', $event->gclid); // attribution وب‌سایت
    }

    public function test_tracking_can_be_disabled_by_config(): void
    {
        config(['ads_tracking.enabled' => false]);

        $this->postJson('/api/ads/attribution', $this->attributionPayload())->assertStatus(503);
        $this->postJson('/api/ads/call-click', ['event_id' => 'call_x1'])->assertStatus(503);
        $this->assertSame(0, AdsCallClickEvent::count());
    }
}
