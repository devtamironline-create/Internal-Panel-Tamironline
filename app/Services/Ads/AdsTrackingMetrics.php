<?php

namespace App\Services\Ads;

use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * کوئری‌های داشبوردِ Ads Tracking — همه تجمیعی و ایندکس‌خور؛ هیچ‌کدام در
 * مسیرِ ingestion اجرا نمی‌شوند.
 */
class AdsTrackingMetrics
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $source = null,       // website|pwa|null
        public readonly ?string $attributed = null,   // attributed|unattributed|null
    ) {}

    /** پایهٔ فیلترشدهٔ رویدادها — ستون‌ها table-qualified تا joinها نشکنند. */
    public function events(): Builder
    {
        $t = 'ads_call_click_events';

        return AdsCallClickEvent::query()
            ->whereBetween("$t.event_time", [$this->from, $this->to])
            ->when($this->source, fn ($q) => $q->where("$t.client_source", $this->source))
            ->when($this->attributed === 'attributed', fn ($q) => $q->where(
                fn ($w) => $w->whereNotNull("$t.gclid")->orWhereNotNull("$t.wbraid")->orWhereNotNull("$t.gbraid")
            ))
            ->when($this->attributed === 'unattributed', fn ($q) => $q
                ->whereNull("$t.gclid")->whereNull("$t.wbraid")->whereNull("$t.gbraid"));
    }

    /** @return array<string, int> */
    public function kpis(): array
    {
        $row = $this->events()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN gclid IS NOT NULL OR wbraid IS NOT NULL OR gbraid IS NOT NULL THEN 1 ELSE 0 END) as attributed')
            ->selectRaw("SUM(CASE WHEN client_source = 'website' THEN 1 ELSE 0 END) as website")
            ->selectRaw("SUM(CASE WHEN client_source = 'pwa' THEN 1 ELSE 0 END) as pwa")
            ->selectRaw('COUNT(DISTINCT ads_attribution_id) as unique_attributions')
            ->selectRaw("SUM(CASE WHEN google_status = 'pending' THEN 1 ELSE 0 END) as pending_upload")
            ->selectRaw("SUM(CASE WHEN google_status = 'failed' THEN 1 ELSE 0 END) as failed_upload")
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'total' => $total,
            'attributed' => (int) ($row->attributed ?? 0),
            'unattributed' => $total - (int) ($row->attributed ?? 0),
            'website' => (int) ($row->website ?? 0),
            'pwa' => (int) ($row->pwa ?? 0),
            'unique_attributions' => (int) ($row->unique_attributions ?? 0),
            'pending_upload' => (int) ($row->pending_upload ?? 0),
            'failed_upload' => (int) ($row->failed_upload ?? 0),
        ];
    }

    /**
     * سری زمانی — ساعتی برای بازهٔ ≤ ۲ روز، وگرنه روزانه.
     *
     * @return array{granularity: string, points: list<array{label: string, website: int, pwa: int, other: int}>}
     */
    public function series(): array
    {
        $hourly = $this->from->diffInHours($this->to) <= 48;
        $bucketExpr = $hourly
            ? "DATE_FORMAT(event_time, '%Y-%m-%d %H:00')"
            : 'DATE(event_time)';
        // sqlite (تست‌ها) DATE_FORMAT ندارد.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $bucketExpr = $hourly ? "strftime('%Y-%m-%d %H:00', event_time)" : 'DATE(event_time)';
        }

        $rows = $this->events()
            ->selectRaw("$bucketExpr as bucket")
            ->selectRaw("SUM(CASE WHEN client_source = 'website' THEN 1 ELSE 0 END) as website")
            ->selectRaw("SUM(CASE WHEN client_source = 'pwa' THEN 1 ELSE 0 END) as pwa")
            ->selectRaw("SUM(CASE WHEN client_source NOT IN ('website','pwa') THEN 1 ELSE 0 END) as other")
            ->groupBy('bucket')->orderBy('bucket')->get();

        return [
            'granularity' => $hourly ? 'hourly' : 'daily',
            'points' => $rows->map(fn ($r) => [
                'label' => (string) $r->bucket,
                'website' => (int) $r->website,
                'pwa' => (int) $r->pwa,
                'other' => (int) $r->other,
            ])->all(),
        ];
    }

    /** @return list<object> ده کلیدواژهٔ برتر بر اساس Call Click. */
    public function topKeywords(int $limit = 10): array
    {
        return $this->events()
            ->join('ads_attributions', 'ads_attributions.id', '=', 'ads_call_click_events.ads_attribution_id')
            ->whereNotNull('ads_attributions.keyword')
            ->selectRaw('ads_attributions.keyword as keyword')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw("SUM(CASE WHEN ads_call_click_events.client_source = 'website' THEN 1 ELSE 0 END) as website")
            ->selectRaw("SUM(CASE WHEN ads_call_click_events.client_source = 'pwa' THEN 1 ELSE 0 END) as pwa")
            ->selectRaw('SUM(CASE WHEN ads_call_click_events.gclid IS NOT NULL OR ads_call_click_events.wbraid IS NOT NULL OR ads_call_click_events.gbraid IS NOT NULL THEN 1 ELSE 0 END) as attributed')
            ->groupBy('ads_attributions.keyword')
            ->orderByDesc('calls')->limit($limit)->get()->all();
    }

    /** @return list<object> شمارش بر اساس placement. */
    public function placements(int $limit = 20): array
    {
        return $this->events()
            ->whereNotNull('placement')
            ->selectRaw('placement, COUNT(*) as calls')
            ->selectRaw("SUM(CASE WHEN client_source = 'website' THEN 1 ELSE 0 END) as website")
            ->selectRaw("SUM(CASE WHEN client_source = 'pwa' THEN 1 ELSE 0 END) as pwa")
            ->groupBy('placement')->orderByDesc('calls')->limit($limit)->get()->all();
    }

    /** @return array<string, mixed> دادهٔ تبِ Diagnostics. */
    public static function diagnostics(): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $todayEvents = AdsCallClickEvent::where('event_time', '>=', $today);

        return [
            'enabled' => (bool) config('ads_tracking.enabled'),
            'last_event_at' => AdsCallClickEvent::max('event_time'),
            'events_today' => (clone $todayEvents)->count(),
            'attributed_today' => (clone $todayEvents)->where(
                fn ($w) => $w->whereNotNull('gclid')->orWhereNotNull('wbraid')->orWhereNotNull('gbraid')
            )->count(),
            'website_today' => (clone $todayEvents)->where('client_source', 'website')->count(),
            'pwa_today' => (clone $todayEvents)->where('client_source', 'pwa')->count(),
            'pending_google' => AdsCallClickEvent::where('google_status', 'pending')->count(),
            'failed_google' => AdsCallClickEvent::where('google_status', 'failed')->count(),
            'total_attributions' => AdsAttribution::count(),
            'allowed_origins' => (array) config('ads_tracking.allowed_origins'),
            'queue_driver' => (string) config('queue.default'),
            'scheduler_heartbeat' => \Illuminate\Support\Facades\Cache::get('scheduler_heartbeat'),
            'google_upload_enabled' => (bool) config('ads_tracking.google_upload_enabled'),
        ];
    }
}
