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
        ]);
    }

    public function show(int $event)
    {
        $event = AdsCallClickEvent::with('attribution')->findOrFail($event);

        return view('admin.ads-tracking.event', ['event' => $event]);
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
