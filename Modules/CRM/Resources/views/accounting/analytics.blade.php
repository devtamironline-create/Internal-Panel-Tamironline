@extends('layouts.admin')

@section('page-title', 'گزارش حسابداری')

@section('main')
<div class="space-y-4" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📊 گزارش حسابداری</h1>
            <p class="text-sm text-gray-500 mt-1">از {{ $from }} تا {{ $to }} — بر اساس {{ $granularities[$granularity] }}</p>
        </div>
        <a href="{{ route('crm.costs.index') }}" class="px-3 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm">← لیست هزینه‌ها</a>
    </div>

    {{-- فیلترها: دانه‌بندی + بازهٔ شمسی --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-end flex-wrap gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">بر اساس</label>
            <div class="flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                @foreach($granularities as $key => $label)
                    <button type="submit" name="granularity" value="{{ $key }}"
                            class="px-4 py-2 text-sm {{ $granularity === $key ? 'bg-emerald-600 text-white font-bold' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">از تاریخ (شمسی)</label>
            <input type="text" name="from" value="{{ request('from', $from) }}" dir="ltr" autocomplete="off"
                   class="jalali-datepicker w-36 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">تا تاریخ (شمسی)</label>
            <input type="text" name="to" value="{{ request('to', $to) }}" dir="ltr" autocomplete="off"
                   class="jalali-datepicker w-36 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
        </div>
        <input type="hidden" name="granularity" value="{{ $granularity }}">
        <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg font-bold">اعمال</button>
        <div class="flex items-center gap-1 text-xs">
            <?php
                $today = \Morilog\Jalali\Jalalian::now();
                $presets = [
                    '۷ روز' => ['from' => \Morilog\Jalali\Jalalian::fromCarbon(now()->subDays(6))->format('Y/m/d'), 'granularity' => 'day'],
                    '۳۰ روز' => ['from' => \Morilog\Jalali\Jalalian::fromCarbon(now()->subDays(29))->format('Y/m/d'), 'granularity' => 'day'],
                    'این ماه' => ['from' => $today->format('Y/m/01'), 'granularity' => 'day'],
                    'امسال' => ['from' => $today->format('Y/01/01'), 'granularity' => 'month'],
                ];
            ?>
            @foreach($presets as $label => $q)
                <a href="{{ route('crm.costs.analytics', $q + ['to' => $today->format('Y/m/d')]) }}"
                   class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <?php
        $series = [
            'invoices' => ['label' => 'مبلغ کل فاکتورها', 'color' => '#3b82f6', 'chip' => 'text-blue-600'],
            'wallet' => ['label' => 'شارژ کیف پول (سود ناخالص)', 'color' => '#10b981', 'chip' => 'text-emerald-600'],
            'expenses' => ['label' => 'هزینه‌ها', 'color' => '#f43f5e', 'chip' => 'text-rose-600'],
            'net' => ['label' => 'سود خالص', 'color' => '#8b5cf6', 'chip' => 'text-violet-600'],
        ];
        $fmtShort = function ($v) {
            $abs = abs($v);
            if ($abs >= 1_000_000_000) return round($v / 1_000_000_000, 1).'B';
            if ($abs >= 1_000_000) return round($v / 1_000_000, 1).'M';
            if ($abs >= 1_000) return round($v / 1_000).'K';
            return (string) $v;
        };
    ?>

    <div x-data="{ on: { invoices: true, wallet: true, expenses: true, net: true } }" class="space-y-4">
        {{-- کارت‌های جمع — کلیک = روشن/خاموش همان سری روی نمودار (سبک Google Ads) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($series as $key => $s)
                <button type="button" @click="on.{{ $key }} = ! on.{{ $key }}"
                        class="text-right bg-white dark:bg-gray-800 rounded-xl border-2 p-4 transition"
                        style="border-color: {{ $s['color'] }};"
                        :class="on.{{ $key }} ? '' : 'opacity-40 grayscale'">
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $s['color'] }};"></span>
                        {{ $s['label'] }}
                    </div>
                    <div class="text-lg font-bold {{ $key === 'net' ? ($totals['net'] >= 0 ? 'text-emerald-600' : 'text-rose-600') : 'text-gray-800 dark:text-gray-100' }}">
                        {{ ($key === 'net' && $totals['net'] > 0 ? '+' : '') }}{{ number_format($totals[$key]) }}
                        <span class="text-xs font-normal text-gray-400">تومان</span>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- نمودار چهارسری --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            @if(empty($buckets))
                <div class="text-center text-gray-400 text-sm py-10">در این بازه داده‌ای نیست.</div>
            @else
                <?php
                    $all = [];
                    foreach ($buckets as $b) { foreach (array_keys($series) as $k) { $all[] = $b[$k]; } }
                    $minV = min(min($all), 0);
                    $maxV = max(max($all), $minV + 1);
                    $n = count($buckets);
                    $plotW = 700.0; $plotH = 200.0; $x0 = 46.0; $y0 = 218.0;
                    $px = fn ($i) => $n > 1 ? $x0 + $i * ($plotW / ($n - 1)) : $x0 + $plotW / 2;
                    $py = fn ($val) => $y0 - (($val - $minV) / ($maxV - $minV)) * $plotH;
                    $labelStep = max(1, (int) ceil($n / 12));
                ?>
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 760 250" class="w-full min-w-[680px]" style="direction: ltr;">
                        <line x1="{{ $x0 }}" y1="{{ round($py(0), 1) }}" x2="{{ $x0 + $plotW }}" y2="{{ round($py(0), 1) }}" stroke="currentColor" class="text-gray-300 dark:text-gray-600" stroke-width="1"/>
                        <text x="4" y="{{ round($py($maxV), 1) + 4 }}" font-size="9" class="fill-gray-400">{{ $fmtShort($maxV) }}</text>
                        <text x="4" y="{{ round($py(0), 1) + 4 }}" font-size="9" class="fill-gray-400">0</text>
                        @if($minV < 0)
                            <text x="4" y="{{ round($py($minV), 1) }}" font-size="9" class="fill-gray-400">{{ $fmtShort($minV) }}</text>
                        @endif

                        @foreach($series as $key => $s)
                            <g x-show="on.{{ $key }}">
                                <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2"
                                          points="{{ collect($buckets)->map(fn ($b, $i) => round($px($i), 1).','.round($py($b[$key]), 1))->implode(' ') }}"/>
                                @foreach($buckets as $i => $b)
                                    <circle cx="{{ round($px($i), 1) }}" cy="{{ round($py($b[$key]), 1) }}" r="3" fill="{{ $s['color'] }}">
                                        <title>{{ $b['label'] }} — {{ $s['label'] }}: {{ number_format($b[$key]) }} تومان</title>
                                    </circle>
                                @endforeach
                            </g>
                        @endforeach

                        @foreach($buckets as $i => $b)
                            @if($i % $labelStep === 0 || $i === $n - 1)
                                <text x="{{ round($px($i), 1) }}" y="{{ $y0 + 16 }}" text-anchor="middle" font-size="9" class="fill-gray-500 dark:fill-gray-400">{{ $b['label'] }}</text>
                            @endif
                        @endforeach
                    </svg>
                </div>
            @endif
        </div>

        {{-- جدول ریز دوره‌ها --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-4 py-2 text-right">دوره</th>
                        @foreach($series as $key => $s)
                            <th class="px-4 py-2 text-right" x-show="on.{{ $key }}">{{ $s['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse(array_reverse($buckets) as $b)
                        <tr class="border-b border-gray-50 dark:border-gray-700/50">
                            <td class="px-4 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $b['label'] }}</td>
                            <td class="px-4 py-2" x-show="on.invoices">{{ number_format($b['invoices']) }}</td>
                            <td class="px-4 py-2" x-show="on.wallet">{{ number_format($b['wallet']) }}</td>
                            <td class="px-4 py-2" x-show="on.expenses">{{ number_format($b['expenses']) }}</td>
                            <td class="px-4 py-2 {{ $b['net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}" x-show="on.net">{{ ($b['net'] > 0 ? '+' : '').number_format($b['net']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
