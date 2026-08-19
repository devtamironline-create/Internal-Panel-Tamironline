<?php

namespace Tests\Feature\Ads;

use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * کامندِ تطبیقِ پنل با Google Ads — نکتهٔ اصلی: تماس‌هایی که کلیکشان
 * مربوط به روزهای قبل است در گزارشِ «امروز»ِ ادز دیده نمی‌شوند.
 */
class AdsGoogleReconcileCommandTest extends TestCase
{
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
    }

    private function callEvent(string $clickDay, string $status = 'uploaded'): AdsCallClickEvent
    {
        static $i = 0;
        $i++;

        $attribution = AdsAttribution::forceCreate([
            'attribution_id' => str_pad((string) $i, 26, 'A', STR_PAD_LEFT),
            'client_source' => 'website',
            'gclid' => 'gclid-'.$i,
            'first_seen_at' => $clickDay.' 10:00:00',
            'last_seen_at' => $clickDay.' 10:00:00',
            'expires_at' => now()->addDays(90),
        ]);

        return AdsCallClickEvent::forceCreate([
            'event_id' => 'call_'.$i,
            'ads_attribution_id' => $attribution->id,
            'client_source' => 'website',
            'gclid' => 'gclid-'.$i,
            'event_time' => now()->setTime(12, 0),
            'google_status' => $status,
        ]);
    }

    public function test_it_separates_calls_whose_click_happened_on_earlier_days(): void
    {
        $today = now()->format('Y-m-d');

        $this->callEvent($today);                                  // کلیک و تماس هر دو امروز
        $this->callEvent(now()->subDays(3)->format('Y-m-d'));      // کلیکِ سه روز پیش
        $this->callEvent(now()->subDays(10)->format('Y-m-d'));     // کلیکِ ده روز پیش
        $this->callEvent($today, 'not_ready');                     // بدون شناسه — اصلاً ارسال نمی‌شود

        $this->assertSame(0, Artisan::call('ads:google-reconcile'));
        $output = Artisan::output();

        $this->assertStringContainsString('کل کلیک تماس', $output);

        // قیف: ۴ کلیک، ۳ ارسال‌شده، و فقط ۱ مورد کلیکش هم امروز بوده.
        $this->assertStringContainsString('از 3 تماسِ ارسال‌شده، فقط 1 مورد', $output);
        $this->assertStringContainsString('باقی (2 مورد)', $output);
    }

    public function test_an_empty_range_does_not_break(): void
    {
        $this->artisan('ads:google-reconcile', ['--days' => 7])->assertSuccessful();
    }
}
