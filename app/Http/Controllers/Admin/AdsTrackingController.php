<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use App\Services\Ads\AdsTrackingMetrics;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * داشبوردِ Marketing → Ads Tracking — دسترسی: view-ads-tracking (route).
 */
class AdsTrackingController extends Controller
{
    public const PRESETS = [
        'today' => 'امروز',
        'yesterday' => 'دیروز',
        '7d' => '۷ روز اخیر',
        '30d' => '۳۰ روز اخیر',
        'custom' => 'بازهٔ دلخواه',
    ];

    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $source = in_array($request->query('source'), ['website', 'pwa'], true) ? $request->query('source') : null;
        $attributed = in_array($request->query('attributed'), ['attributed', 'unattributed'], true)
            ? $request->query('attributed') : null;
        $tab = in_array($request->query('tab'), ['events', 'attributions', 'diagnostics'], true)
            ? $request->query('tab') : 'events';

        $metrics = new AdsTrackingMetrics($from, $to, $source, $attributed);

        $events = $tab === 'events'
            ? $metrics->events()->with('attribution')->orderByDesc('event_time')->paginate(30)->withQueryString()
            : null;

        $attributions = $tab === 'attributions'
            ? AdsAttribution::query()
                ->whereBetween('last_seen_at', [$from, $to])
                ->when($source, fn ($q) => $q->where('client_source', $source))
                ->withCount('callClicks')
                ->orderByDesc('last_seen_at')->paginate(30)->withQueryString()
            : null;

        return view('admin.ads-tracking.index', [
            'tab' => $tab,
            'preset' => $preset,
            'presets' => self::PRESETS,
            'from' => JalaliDate::toJalali($from->toDateString()),
            'to' => JalaliDate::toJalali($to->toDateString()),
            'source' => $source,
            'attributed' => $attributed,
            'kpis' => $metrics->kpis(),
            'series' => $metrics->series(),
            'topKeywords' => $tab === 'events' ? $metrics->topKeywords() : [],
            'placements' => $tab === 'events' ? $metrics->placements() : [],
            'events' => $events,
            'attributions' => $attributions,
            'diagnostics' => $tab === 'diagnostics' ? AdsTrackingMetrics::diagnostics() : null,
            'googleHealth' => self::googleHealth(),
        ]);
    }

    public function show(int $event)
    {
        $event = AdsCallClickEvent::with('attribution')->findOrFail($event);

        return view('admin.ads-tracking.event', ['event' => $event]);
    }

    /**
     * «تست اتصال گوگل» — پروکسی → OAuth → validateOnly ingest.
     * هرگز conversion واقعی نمی‌سازد (validateOnly همیشه true است).
     */
    public function testConnection()
    {
        $steps = [
            'proxy' => ['ok' => false, 'message' => ''],
            'oauth' => ['ok' => false, 'message' => ''],
            'data_manager' => ['ok' => false, 'message' => ''],
            'destination' => ['ok' => false, 'message' => ''],
        ];

        // ۱) پروکسی: هر پاسخ HTTP (حتی 404) یعنی تونل CONNECT برقرار شده.
        try {
            $http = \App\Services\Ads\Google\GoogleHttpClient::fromConfig();
            $probe = $http->get((string) config('ads_tracking.google.oauth_token_url'));
            $steps['proxy'] = ['ok' => true, 'message' => 'HTTP '.$probe->status().' از مسیر پروکسی'];
        } catch (\Throwable $e) {
            $steps['proxy'] = ['ok' => false, 'message' => mb_substr($e->getMessage(), 0, 300)];
        }

        // ۲) OAuth: توکن تازه (کش دور ریخته می‌شود تا تست واقعی باشد).
        if ($steps['proxy']['ok']) {
            try {
                $tokens = \App\Services\Ads\Google\GoogleDataManagerTokenProvider::fromConfig();
                $tokens->forget();
                $tokens->token();
                $steps['oauth'] = ['ok' => true, 'message' => 'Access Token گرفته شد'];
            } catch (\Throwable $e) {
                $steps['oauth'] = ['ok' => false, 'message' => mb_substr($e->getMessage(), 0, 300)];
            }
        }

        // ۳) Data Manager + Destination: ingest با validateOnly=true روی
        //    یک event دارای شناسه — conversion واقعی ساخته نمی‌شود.
        if ($steps['oauth']['ok']) {
            $sample = AdsCallClickEvent::query()
                ->where(fn ($q) => $q->whereNotNull('gclid')->orWhereNotNull('wbraid')->orWhereNotNull('gbraid'))
                ->latest('id')->first();

            if (! $sample) {
                $steps['data_manager'] = ['ok' => false, 'message' => 'هیچ event دارای شناسهٔ Google برای validate موجود نیست.'];
            } else {
                try {
                    $result = \App\Services\Ads\Google\GoogleDataManagerService::fromConfig()->ingest([$sample], validateOnly: true);
                    $steps['data_manager'] = ['ok' => true, 'message' => 'validateOnly پذیرفته شد (HTTP '.$result['meta']['http_status'].')'];
                    $steps['destination'] = ['ok' => true, 'message' => 'Customer '.config('ads_tracking.google.customer_id').' / Conversion '.config('ads_tracking.google.conversion_action_id')];
                } catch (\Throwable $e) {
                    $steps['data_manager'] = ['ok' => false, 'message' => mb_substr($e->getMessage(), 0, 300)];
                }
            }
        }

        $result = ['at' => now()->toDateTimeString(), 'steps' => $steps];
        cache()->put('ads_google_last_check', $result, now()->addDays(7));

        return back()->with('google_test_result', $result)
            ->with(collect($steps)->every(fn ($s) => $s['ok']) ? 'success' : 'error',
                collect($steps)->every(fn ($s) => $s['ok'])
                    ? 'تست اتصال گوگل کامل موفق بود.'
                    : 'تست اتصال گوگل ناقص است — جزئیات هر مرحله را ببینید.');
    }

    /** بازگرداندن eventهای failed به صف — transactionId ثابت است، duplicate نمی‌سازد. */
    public function retryFailed()
    {
        $count = \App\Services\Ads\Google\GoogleCallConversionUploader::fromConfig()->retryFailed();

        return back()->with('success', $count > 0
            ? $count.' رویداد ناموفق دوباره وارد صف ارسال شد.'
            : 'رویداد ناموفقی برای ارسال دوباره وجود ندارد.');
    }

    /** دادهٔ بخش «سلامت تحویل گوگل» — فقط DB/cache؛ هیچ تماس زنده‌ای با گوگل. */
    public static function googleHealth(): array
    {
        $g = (array) config('ads_tracking.google', []);

        $statusCounts = AdsCallClickEvent::query()
            ->selectRaw('google_status, COUNT(*) as c')
            ->groupBy('google_status')->pluck('c', 'google_status')->all();

        $lastWithRequest = AdsCallClickEvent::query()
            ->whereNotNull('google_request_id')->latest('google_last_attempt_at')->first();

        $proxyUrl = (string) ($g['proxy']['url'] ?? '');
        $proxyHost = $proxyUrl !== '' ? (parse_url($proxyUrl, PHP_URL_HOST).':'.(parse_url($proxyUrl, PHP_URL_PORT) ?: 3128)) : '—';

        return [
            'upload_enabled' => (bool) ($g['upload_enabled'] ?? false),
            'validate_only' => (bool) ($g['validate_only'] ?? true),
            'proxy_enabled' => (bool) ($g['proxy']['enabled'] ?? false),
            'proxy_host' => $proxyHost,
            'credentials_ready' => is_readable((string) ($g['credentials_path'] ?? '')),
            'customer_id' => (string) ($g['customer_id'] ?? ''),
            'conversion_action_id' => (string) ($g['conversion_action_id'] ?? ''),
            'conversion_action_name' => (string) ($g['conversion_action_name'] ?? ''),
            'status_counts' => $statusCounts,
            'last_attempt_at' => AdsCallClickEvent::max('google_last_attempt_at'),
            'last_uploaded_at' => AdsCallClickEvent::max('google_uploaded_at'),
            'last_request_id' => $lastWithRequest?->google_request_id,
            'last_request_status' => $lastWithRequest?->google_response_meta['request_status']
                ?? ($lastWithRequest ? $lastWithRequest->google_status : null),
            'oldest_pending_at' => AdsCallClickEvent::where('google_status', 'pending')->min('event_time'),
            'last_check' => cache('ads_google_last_check'),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function range(Request $request): array
    {
        $tz = config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $preset = array_key_exists((string) $request->query('range'), self::PRESETS)
            ? (string) $request->query('range') : 'today';

        [$from, $to] = match ($preset) {
            'yesterday' => [$today->subDay(), $today->subSecond()],
            '7d' => [$today->subDays(6), $today->addDay()->subSecond()],
            '30d' => [$today->subDays(29), $today->addDay()->subSecond()],
            'custom' => [
                JalaliDate::isValid((string) $request->query('from'))
                    ? CarbonImmutable::parse(JalaliDate::toGregorian($request->query('from')), $tz)
                    : $today,
                JalaliDate::isValid((string) $request->query('to'))
                    ? CarbonImmutable::parse(JalaliDate::toGregorian($request->query('to')), $tz)->addDay()->subSecond()
                    : $today->addDay()->subSecond(),
            ],
            default => [$today, $today->addDay()->subSecond()],
        };

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to, $preset];
    }
}
