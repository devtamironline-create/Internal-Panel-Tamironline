@extends('layouts.admin')

@section('page-title', 'Ads Tracking')

@section('main')
<?php
    $mask = function (?string $id) {
        if (blank($id)) return '—';
        return mb_strlen($id) <= 12 ? $id : mb_substr($id, 0, 7).'…'.mb_substr($id, -4);
    };
    $qs = fn (array $extra) => request()->fullUrlWithQuery($extra + ['page' => null]);
    $sourceBadge = fn (?string $s) => match ($s) {
        'website' => '<span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Website</span>',
        'pwa' => '<span class="text-xs px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">PWA</span>',
        default => '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">نامشخص</span>',
    };
    $statusBadge = fn (string $s) => match ($s) {
        'pending' => '<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">در صف Google</span>',
        'uploaded' => '<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">آپلودشده</span>',
        'failed' => '<span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">ناموفق</span>',
        'processing' => '<span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">در حال پردازش</span>',
        'ignored' => '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">نادیده</span>',
        default => '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">بدون شناسه</span>',
    };
?>
<div class="space-y-4" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📣 Ads Tracking — ردیابی تماس تبلیغات</h1>
            <p class="text-sm text-gray-500 mt-1">از {{ $from }} تا {{ $to }} — دیتابیس پنل منبع حقیقت است؛ آپلود Google در این مرحله خاموش است.</p>
        </div>
    </div>

    {{-- فیلترها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center gap-1 flex-wrap text-xs">
            @foreach($presets as $key => $label)
                @if($key !== 'custom')
                    <a href="{{ $qs(['range' => $key, 'from' => null, 'to' => null]) }}"
                       class="px-3 py-1.5 rounded-lg border {{ $preset === $key ? 'bg-emerald-600 text-white border-emerald-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endif
            @endforeach
            <form method="GET" class="flex items-center gap-1">
                <input type="hidden" name="range" value="custom">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($source)<input type="hidden" name="source" value="{{ $source }}">@endif
                @if($attributed)<input type="hidden" name="attributed" value="{{ $attributed }}">@endif
                <input type="text" name="from" value="{{ request('from', $from) }}" dir="ltr" autocomplete="off"
                       class="jalali-datepicker w-28 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-center">
                <span class="text-gray-400">تا</span>
                <input type="text" name="to" value="{{ request('to', $to) }}" dir="ltr" autocomplete="off"
                       class="jalali-datepicker w-28 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-center">
                <button class="px-3 py-1.5 rounded-lg {{ $preset === 'custom' ? 'bg-emerald-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">اعمال</button>
            </form>
        </div>
        <div class="flex items-center gap-4 flex-wrap text-xs">
            <div class="flex items-center gap-1">
                <span class="text-gray-400">منبع:</span>
                @foreach(['' => 'همه', 'website' => 'Website', 'pwa' => 'PWA'] as $key => $label)
                    <a href="{{ $qs(['source' => $key ?: null]) }}"
                       class="px-2.5 py-1 rounded-lg border {{ ($source ?? '') === $key ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 border-gray-800' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-1">
                <span class="text-gray-400">Attribution:</span>
                @foreach(['' => 'همه', 'attributed' => 'دارد', 'unattributed' => 'ندارد'] as $key => $label)
                    <a href="{{ $qs(['attributed' => $key ?: null]) }}"
                       class="px-2.5 py-1 rounded-lg border {{ ($attributed ?? '') === $key ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 border-gray-800' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <?php
        $pct = fn (int $part) => $kpis['total'] > 0 ? round($part / $kpis['total'] * 100, 1).'%' : '—';
        $cards = [
            ['label' => 'Call Clicks', 'value' => $kpis['total'], 'sub' => null, 'color' => 'text-gray-800 dark:text-gray-100'],
            ['label' => 'Attributed', 'value' => $kpis['attributed'], 'sub' => $pct($kpis['attributed']), 'color' => 'text-emerald-600'],
            ['label' => 'Unattributed', 'value' => $kpis['unattributed'], 'sub' => $pct($kpis['unattributed']), 'color' => 'text-amber-600'],
            ['label' => 'Website', 'value' => $kpis['website'], 'sub' => $pct($kpis['website']), 'color' => 'text-blue-600'],
            ['label' => 'PWA', 'value' => $kpis['pwa'], 'sub' => $pct($kpis['pwa']), 'color' => 'text-violet-600'],
            ['label' => 'Unique Attributions', 'value' => $kpis['unique_attributions'], 'sub' => null, 'color' => 'text-gray-800 dark:text-gray-100'],
            ['label' => 'Pending Google', 'value' => $kpis['pending_upload'], 'sub' => $googleHealth['upload_enabled'] ? 'در صف ارسال' : 'آپلود خاموش', 'color' => 'text-amber-600'],
            ['label' => 'Failed Upload', 'value' => $kpis['failed_upload'], 'sub' => null, 'color' => 'text-rose-600'],
        ];
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
        @foreach($cards as $card)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-[11px] text-gray-500 mb-1">{{ $card['label'] }}</div>
                <div class="text-lg font-bold {{ $card['color'] }}">{{ number_format($card['value']) }}</div>
                @if($card['sub'])<div class="text-[10px] text-gray-400">{{ $card['sub'] }}</div>@endif
            </div>
        @endforeach
    </div>

    {{-- Calls Over Time --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Calls Over Time ({{ $series['granularity'] === 'hourly' ? 'ساعتی' : 'روزانه' }})</h2>
            <div class="flex items-center gap-3 text-[11px] text-gray-500">
                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500 inline-block"></span> Website</span>
                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-violet-500 inline-block"></span> PWA</span>
            </div>
        </div>
        @if(empty($series['points']))
            <div class="text-center text-gray-400 text-sm py-8">در این بازه رویدادی ثبت نشده است.</div>
        @else
            <?php
                $pts = $series['points'];
                $n = count($pts);
                $maxV = max(1, ...array_map(fn ($p) => $p['website'] + $p['pwa'] + $p['other'], $pts));
                $plotW = 700.0; $plotH = 150.0; $x0 = 30.0; $y0 = 165.0;
                $bw = max(4, min(34, ($plotW / max(1, $n)) - 4));
                $labelStep = max(1, (int) ceil($n / 12));
            ?>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 760 190" class="w-full min-w-[640px]" style="direction: ltr;">
                    <line x1="{{ $x0 }}" y1="{{ $y0 }}" x2="{{ $x0 + $plotW }}" y2="{{ $y0 }}" stroke="currentColor" class="text-gray-200 dark:text-gray-700"/>
                    <text x="2" y="18" font-size="9" class="fill-gray-400">{{ $maxV }}</text>
                    @foreach($pts as $i => $p)
                        <?php
                            $x = $x0 + 4 + $i * ($plotW / max(1, $n));
                            $hW = (int) round($p['website'] / $maxV * $plotH);
                            $hP = (int) round($p['pwa'] / $maxV * $plotH);
                            $hO = (int) round($p['other'] / $maxV * $plotH);
                            $y = $y0;
                        ?>
                        @if($hW > 0)<?php $y -= $hW; ?><rect x="{{ round($x,1) }}" y="{{ $y }}" width="{{ $bw }}" height="{{ $hW }}" rx="1.5" class="fill-blue-500"><title>{{ $p['label'] }} — Website: {{ $p['website'] }}</title></rect>@endif
                        @if($hP > 0)<?php $y -= $hP; ?><rect x="{{ round($x,1) }}" y="{{ $y }}" width="{{ $bw }}" height="{{ $hP }}" rx="1.5" class="fill-violet-500"><title>{{ $p['label'] }} — PWA: {{ $p['pwa'] }}</title></rect>@endif
                        @if($hO > 0)<?php $y -= $hO; ?><rect x="{{ round($x,1) }}" y="{{ $y }}" width="{{ $bw }}" height="{{ $hO }}" rx="1.5" class="fill-gray-400"><title>{{ $p['label'] }} — سایر: {{ $p['other'] }}</title></rect>@endif
                        @if($i % $labelStep === 0 || $i === $n - 1)
                            <text x="{{ round($x + $bw / 2, 1) }}" y="{{ $y0 + 14 }}" text-anchor="middle" font-size="8" class="fill-gray-400">{{ mb_substr($p['label'], 5) }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
            {{-- Attribution breakdown bar --}}
            @if($kpis['total'] > 0)
                <div class="mt-3">
                    <div class="flex h-3 rounded-full overflow-hidden">
                        <div class="bg-emerald-500" style="width: {{ round($kpis['attributed'] / $kpis['total'] * 100, 1) }}%" title="Attributed"></div>
                        <div class="bg-amber-400" style="width: {{ round($kpis['unattributed'] / $kpis['total'] * 100, 1) }}%" title="Unattributed"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-gray-500 mt-1">
                        <span>Attributed: {{ number_format($kpis['attributed']) }} ({{ $pct($kpis['attributed']) }})</span>
                        <span>Unattributed: {{ number_format($kpis['unattributed']) }} ({{ $pct($kpis['unattributed']) }})</span>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Google Delivery Health — وضعیت تحویل Conversion به Google Ads --}}
    <?php
        $gh = $googleHealth;
        $sc = $gh['status_counts'];
        $chip = fn (string $label, int $v, string $color) => ['label' => $label, 'value' => $v, 'color' => $color];
        $googleChips = [
            $chip('Pending', (int) ($sc['pending'] ?? 0), 'text-amber-600'),
            $chip('Sending', (int) ($sc['sending'] ?? 0), 'text-sky-600'),
            $chip('Processing', (int) ($sc['processing'] ?? 0), 'text-blue-600'),
            $chip('Uploaded', (int) ($sc['uploaded'] ?? 0), 'text-emerald-600'),
            $chip('Failed', (int) ($sc['failed'] ?? 0), 'text-rose-600'),
            $chip('Not Ready', (int) ($sc['not_ready'] ?? 0), 'text-gray-500'),
            $chip('Ignored', (int) ($sc['ignored'] ?? 0), 'text-gray-400'),
        ];
        $onOff = fn (bool $b, string $on = 'روشن', string $off = 'خاموش') => $b
            ? '<span class="text-emerald-600 font-bold">'.$on.'</span>'
            : '<span class="text-gray-400 font-bold">'.$off.'</span>';
        $testResult = session('google_test_result') ?: $gh['last_check'];
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">Google Delivery Health</h2>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('admin.marketing.ads-tracking.test-connection') }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold">تست اتصال گوگل</button>
                </form>
                @if((int) ($sc['failed'] ?? 0) > 0)
                    <form method="POST" action="{{ route('admin.marketing.ads-tracking.retry-failed') }}"
                          onsubmit="return confirm('همهٔ رویدادهای ناموفق دوباره وارد صف ارسال شوند؟ (duplicate ساخته نمی‌شود)');">
                        @csrf
                        <button class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">ارسال دوبارهٔ ناموفق‌ها ({{ number_format($sc['failed']) }})</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 text-xs">
            <div><span class="text-gray-400">Google Upload:</span> {!! $onOff($gh['upload_enabled'], 'Enabled', 'Disabled') !!}</div>
            <div><span class="text-gray-400">Validate Only:</span> {!! $onOff($gh['validate_only'], 'Yes', 'No') !!}</div>
            <div><span class="text-gray-400">Proxy:</span>
                <span dir="ltr" class="font-mono">{{ $gh['proxy_host'] }}</span>
                {!! $onOff($gh['proxy_enabled'], 'اجباری', 'غیرفعال!') !!}
            </div>
            <div><span class="text-gray-400">Credential:</span> {!! $onOff($gh['credentials_ready'], 'آماده', 'در دسترس نیست') !!}</div>
            <div><span class="text-gray-400">Customer:</span> <span dir="ltr" class="font-mono">{{ $gh['customer_id'] ?: '—' }}</span></div>
            <div class="md:col-span-2"><span class="text-gray-400">Conversion:</span>
                <span dir="ltr" class="font-mono">{{ $gh['conversion_action_id'] ?: '—' }}</span>
                <span class="text-gray-500">{{ $gh['conversion_action_name'] }}</span>
            </div>
            <div><span class="text-gray-400">Last Attempt:</span> {{ $gh['last_attempt_at'] ?: '—' }}</div>
            <div><span class="text-gray-400">Last Successful Upload:</span> {{ $gh['last_uploaded_at'] ?: '—' }}</div>
            <div class="md:col-span-2"><span class="text-gray-400">Last Request ID:</span> <span dir="ltr" class="font-mono break-all">{{ $gh['last_request_id'] ?: '—' }}</span></div>
            <div><span class="text-gray-400">Last Request Status:</span> {{ $gh['last_request_status'] ?: '—' }}</div>
            <div><span class="text-gray-400">Oldest Pending:</span> {{ $gh['oldest_pending_at'] ?: '—' }}</div>
        </div>

        <div class="flex items-center gap-4 flex-wrap border-t border-gray-100 dark:border-gray-700 pt-2">
            @foreach($googleChips as $c)
                <span class="text-xs"><span class="text-gray-400">{{ $c['label'] }}:</span> <span class="font-bold {{ $c['color'] }}">{{ number_format($c['value']) }}</span></span>
            @endforeach
        </div>

        @if(is_array($testResult) && isset($testResult['steps']))
            <div class="border-t border-gray-100 dark:border-gray-700 pt-2">
                <div class="text-[11px] text-gray-400 mb-1">آخرین تست اتصال: {{ $testResult['at'] ?? '' }}</div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
                    @foreach(['proxy' => 'Proxy', 'oauth' => 'OAuth', 'data_manager' => 'Data Manager', 'destination' => 'Destination'] as $key => $label)
                        <?php $step = $testResult['steps'][$key] ?? ['ok' => false, 'message' => '—']; ?>
                        <div class="rounded-lg border p-2 {{ $step['ok'] ? 'border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800' : 'border-rose-200 bg-rose-50 dark:bg-rose-900/20 dark:border-rose-800' }}">
                            <div class="font-bold {{ $step['ok'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">{{ $label }} {{ $step['ok'] ? '✓' : '✗' }}</div>
                            <div class="text-gray-500 dark:text-gray-400 break-all" dir="auto">{{ $step['message'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-1 text-sm">
        @foreach(['events' => 'Call Events', 'attributions' => 'Attributions', 'diagnostics' => 'Diagnostics'] as $key => $label)
            <a href="{{ $qs(['tab' => $key]) }}"
               class="px-4 py-2 rounded-t-lg border border-b-0 {{ $tab === $key ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 font-bold text-gray-900 dark:text-gray-100' : 'bg-gray-100 dark:bg-gray-900 border-transparent text-gray-500' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'events')
        {{-- Call Events Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-3 py-2 text-right">زمان</th>
                        <th class="px-3 py-2 text-right">منبع</th>
                        <th class="px-3 py-2 text-right">صفحه</th>
                        <th class="px-3 py-2 text-right">Placement</th>
                        <th class="px-3 py-2 text-right">کلیدواژه</th>
                        <th class="px-3 py-2 text-right">کمپین</th>
                        <th class="px-3 py-2 text-right">دستگاه</th>
                        <th class="px-3 py-2 text-right">Google ID</th>
                        <th class="px-3 py-2 text-right">وضعیت Google</th>
                        <th class="px-3 py-2 text-right">Event</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $e)
                        <tr class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-3 py-2 whitespace-nowrap text-xs">@jdatetime($e->event_time)</td>
                            <td class="px-3 py-2">{!! $sourceBadge($e->client_source) !!}</td>
                            <td class="px-3 py-2 text-xs text-gray-500 max-w-[160px] truncate" dir="ltr">{{ $e->page_path ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $e->placement ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $e->attribution?->keyword ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs" dir="ltr">{{ $e->attribution?->campaign_id ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $e->attribution?->device ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs font-mono" dir="ltr">{{ $mask($e->gclid ?: $e->wbraid ?: $e->gbraid) }}</td>
                            <td class="px-3 py-2">{!! $statusBadge($e->google_status) !!}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('admin.marketing.ads-tracking.event', $e->id) }}"
                                   class="text-emerald-600 text-xs hover:underline font-mono" dir="ltr">{{ $mask($e->event_id) }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">در این بازه Call Click ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events)<div>{{ $events->links() }}</div>@endif

        {{-- Top Keywords + Placements --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
                <div class="px-4 pt-3 text-sm font-bold text-gray-800 dark:text-gray-100">Top Keywords by Call Click</div>
                <p class="px-4 text-[10px] text-gray-400">بر اساس ValueTrack ذخیره‌شده — گزارش رسمی Google نیست.</p>
                <table class="w-full text-sm mt-1">
                    <thead><tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-3 py-2 text-right">کلیدواژه</th><th class="px-3 py-2 text-right">Calls</th>
                        <th class="px-3 py-2 text-right">Website</th><th class="px-3 py-2 text-right">PWA</th>
                        <th class="px-3 py-2 text-right">Attributed٪</th>
                    </tr></thead>
                    <tbody>
                        @forelse($topKeywords as $k)
                            <tr class="border-b border-gray-50 dark:border-gray-700/50">
                                <td class="px-3 py-2">{{ $k->keyword }}</td>
                                <td class="px-3 py-2 font-bold">{{ $k->calls }}</td>
                                <td class="px-3 py-2">{{ $k->website }}</td>
                                <td class="px-3 py-2">{{ $k->pwa }}</td>
                                <td class="px-3 py-2">{{ $k->calls > 0 ? round($k->attributed / $k->calls * 100).'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
                <div class="px-4 pt-3 text-sm font-bold text-gray-800 dark:text-gray-100">Placements</div>
                <p class="px-4 text-[10px] text-gray-400">کدام دکمهٔ تماس بیشترین استفاده را دارد.</p>
                <table class="w-full text-sm mt-1">
                    <thead><tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-3 py-2 text-right">Placement</th><th class="px-3 py-2 text-right">Calls</th>
                        <th class="px-3 py-2 text-right">Website</th><th class="px-3 py-2 text-right">PWA</th>
                    </tr></thead>
                    <tbody>
                        @forelse($placements as $p)
                            <tr class="border-b border-gray-50 dark:border-gray-700/50">
                                <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ $p->placement }}</td>
                                <td class="px-3 py-2 font-bold">{{ $p->calls }}</td>
                                <td class="px-3 py-2">{{ $p->website }}</td>
                                <td class="px-3 py-2">{{ $p->pwa }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'attributions')
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-3 py-2 text-right">First Seen</th>
                        <th class="px-3 py-2 text-right">Last Seen</th>
                        <th class="px-3 py-2 text-right">منبع</th>
                        <th class="px-3 py-2 text-right">Landing</th>
                        <th class="px-3 py-2 text-right">کلیدواژه</th>
                        <th class="px-3 py-2 text-right">کمپین</th>
                        <th class="px-3 py-2 text-right">Ad Group</th>
                        <th class="px-3 py-2 text-right">دستگاه</th>
                        <th class="px-3 py-2 text-right">Google ID</th>
                        <th class="px-3 py-2 text-right">Calls</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attributions as $a)
                        <tr class="border-b border-gray-50 dark:border-gray-700/50">
                            <td class="px-3 py-2 text-xs whitespace-nowrap">@jdatetime($a->first_seen_at)</td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">@jdatetime($a->last_seen_at)</td>
                            <td class="px-3 py-2">{!! $sourceBadge($a->client_source) !!}</td>
                            <td class="px-3 py-2 text-xs text-gray-500 max-w-[160px] truncate" dir="ltr">{{ $a->landing_path ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $a->keyword ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs" dir="ltr">{{ $a->campaign_id ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs" dir="ltr">{{ $a->adgroup_id ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $a->device ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs font-mono" dir="ltr">{{ $mask($a->googleIdLabel()) }}</td>
                            <td class="px-3 py-2 font-bold">{{ $a->call_clicks_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">در این بازه attribution ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($attributions)<div>{{ $attributions->links() }}</div>@endif
    @else
        {{-- Diagnostics --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-2 text-sm">
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-2">وضعیت ردیابی</h2>
                <?php
                    $d = $diagnostics;
                    $pctOf = fn ($part, $total) => $total > 0 ? round($part / $total * 100, 1).'%' : '—';
                ?>
                <div class="flex justify-between"><span class="text-gray-500">Tracking</span><span class="font-bold {{ $d['enabled'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $d['enabled'] ? 'فعال' : 'غیرفعال' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">آخرین رویداد</span><span dir="ltr">{{ $d['last_event_at'] ? \Morilog\Jalali\Jalalian::fromDateTime($d['last_event_at'])->format('Y/m/d H:i') : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">رویدادهای امروز</span><span class="font-bold">{{ number_format($d['events_today']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Attributed امروز</span><span>{{ $pctOf($d['attributed_today'], $d['events_today']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Unattributed امروز</span><span>{{ $pctOf($d['events_today'] - $d['attributed_today'], $d['events_today']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Website امروز</span><span>{{ $pctOf($d['website_today'], $d['events_today']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">PWA امروز</span><span>{{ $pctOf($d['pwa_today'], $d['events_today']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">در صف Google</span><span class="font-bold text-amber-600">{{ number_format($d['pending_google']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Failed Google</span><span class="font-bold text-rose-600">{{ number_format($d['failed_google']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">کل Attributions</span><span>{{ number_format($d['total_attributions']) }}</span></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-2 text-sm">
                <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-2">Config Health</h2>
                <div>
                    <div class="text-gray-500 text-xs mb-1">Allowed Origins</div>
                    @foreach($d['allowed_origins'] as $origin)
                        <div class="font-mono text-xs" dir="ltr">{{ $origin }}</div>
                    @endforeach
                </div>
                <div class="flex justify-between pt-2"><span class="text-gray-500">Queue Driver</span><span class="font-mono text-xs">{{ $d['queue_driver'] }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Scheduler</span>
                    <span class="{{ $d['scheduler_heartbeat'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $d['scheduler_heartbeat'] ? 'فعال — '.$d['scheduler_heartbeat'] : 'ضربان ثبت نشده' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Google Upload</span>
                    <span class="{{ $d['google_upload_enabled'] ? 'text-emerald-600' : 'text-gray-500' }}">{{ $d['google_upload_enabled'] ? 'Enabled' : 'Disabled' }}{{ $d['google_validate_only'] ? ' (Validate Only)' : '' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Proxy اجباری</span>
                    <span class="{{ $d['google_proxy_enabled'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $d['google_proxy_enabled'] ? 'بله (fail-closed)' : 'خیر — خطرناک!' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Credential</span>
                    <span class="{{ $d['google_credentials_ready'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $d['google_credentials_ready'] ? 'آماده' : 'در دسترس نیست' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">Sending / Processing</span>
                    <span>{{ number_format($d['sending_google']) }} / {{ number_format($d['processing_google']) }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">قدیمی‌ترین Pending</span>
                    <span dir="ltr">{{ $d['oldest_pending_google'] ? \Morilog\Jalali\Jalalian::fromDateTime($d['oldest_pending_google'])->format('Y/m/d H:i') : '—' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">آخرین تلاش / آپلود موفق</span>
                    <span dir="ltr" class="text-xs">{{ $d['last_google_attempt_at'] ?: '—' }} / {{ $d['last_google_uploaded_at'] ?: '—' }}</span>
                </div>
                <div class="flex justify-between"><span class="text-gray-500">آخرین Request ID</span>
                    <span dir="ltr" class="font-mono text-[10px] break-all">{{ $d['last_google_request_id'] ?: '—' }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
