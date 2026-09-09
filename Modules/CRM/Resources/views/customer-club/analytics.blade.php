@extends('layouts.admin')

@section('page-title', 'باشگاه مشتریان — تحلیل ورودی‌ها')

@section('main')
@php
    $fmt = fn ($n) => number_format((int) $n);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $qp = fn (array $extra = []) => array_merge(['window' => $filters['window'], 'city_id' => $filters['city_id']], $extra);
@endphp
<div class="p-6 space-y-5" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">👥 باشگاه مشتریان — تحلیل ورودی‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">فقط مشتریانِ واقعیِ اپ (موبایلِ تأییدشده): اکتساب، تبدیل، تقاضا، ساعاتِ اوج و RFM.</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- دکمه‌های بازهٔ فعالیت (روند/ساعات اوج/تقاضا) --}}
            @foreach($windows as $w => $label)
                <a href="{{ route('crm.customer-club.analytics', $qp(['window' => $w])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold {{ (int) $filters['window'] === $w ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if($cities)
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex items-end gap-3">
        <input type="hidden" name="window" value="{{ $filters['window'] }}">
        <label class="block text-xs text-gray-500">شهر
            <select name="city_id" onchange="this.form.submit()" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ شهرها</option>
                @foreach($cities as $c)
                    <option value="{{ $c['id'] }}" @selected((string) $filters['city_id'] === (string) $c['id'])>{{ $c['name'] }}</option>
                @endforeach
            </select>
        </label>
        <span class="text-[11px] text-gray-400 pb-2">فیلترِ شهر روی تقاضا/روند/ساعاتِ اوج اعمال می‌شود.</span>
    </form>
    @endif

    {{-- کارت‌های نمای کلی (مادام‌العمر، مشتریانِ واقعی) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @php($ov = $overview)
        @php($cards = [
            ['label' => 'کل مشتریان', 'value' => $fmt($ov['total_customers']), 'sub' => 'حساب‌های واقعی (بدون بلاک/حذف)', 'tone' => 'gray'],
            ['label' => 'مشتریانِ فعال', 'value' => $fmt($ov['active_customers']), 'sub' => 'سفارش در ۹۰ روزِ اخیر', 'tone' => 'emerald'],
            ['label' => 'نرخ تبدیل', 'value' => $pct($ov['conversion_rate']).'٪', 'sub' => $fmt($ov['with_orders']).' دارای سفارش · '.$fmt($ov['without_orders']).' بدون', 'tone' => 'blue'],
            ['label' => 'نرخ خرید تکراری', 'value' => $pct($ov['repeat_rate']).'٪', 'sub' => $fmt($ov['repeat']).' مشتری با ۲+ سفارش', 'tone' => 'purple'],
            ['label' => 'VIP', 'value' => $fmt($segments['vip']), 'sub' => $segments['vip_limit'].' نفرِ برتر بر اساسِ مبلغ خرید', 'tone' => 'amber'],
            ['label' => 'در خطرِ ریزش', 'value' => $fmt($segments['at_risk']), 'sub' => $segments['churn_days'].' روز بدونِ سفارش', 'tone' => 'rose'],
        ])
        @foreach($cards as $card)
            <div @class([
                'rounded-xl border p-4',
                'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => $card['tone'] === 'gray',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' => $card['tone'] === 'blue',
                'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800' => $card['tone'] === 'purple',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' => $card['tone'] === 'amber',
                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' => $card['tone'] === 'emerald',
                'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800' => $card['tone'] === 'rose',
            ])>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                <div class="text-2xl font-black text-gray-800 dark:text-gray-100 mt-1" dir="ltr">{{ $card['value'] }}</div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 leading-4">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- سگمنت‌های اکشن‌پذیر --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @php($segCards = [
            ['key' => 'no-order', 'title' => 'بدونِ سفارش', 'count' => $segments['no_order'], 'desc' => 'ثبت‌نام کرده‌اند ولی هرگز سفارش نداده‌اند.', 'tone' => 'amber'],
            ['key' => 'vip', 'title' => 'VIP (۳۰ نفرِ برتر)', 'count' => $segments['vip'], 'desc' => 'بیشترین مجموعِ مبلغِ خرید.', 'tone' => 'emerald'],
            ['key' => 'at-risk', 'title' => 'در خطرِ ریزش', 'count' => $segments['at_risk'], 'desc' => $segments['churn_days'].' روز از آخرین سفارش گذشته.', 'tone' => 'rose'],
        ])
        @foreach($segCards as $s)
            <div @class([
                'rounded-xl border p-4',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' => $s['tone'] === 'amber',
                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' => $s['tone'] === 'emerald',
                'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800' => $s['tone'] === 'rose',
            ])>
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $s['title'] }}</div>
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-100" dir="ltr">{{ $fmt($s['count']) }}</div>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-5">{{ $s['desc'] }}</p>
                <div class="flex items-center gap-2 mt-3">
                    <a href="{{ route('crm.customer-club.segments.export', ['segment' => $s['key'], 'format' => 'xlsx']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-[11px] font-bold">خروجی اکسل</a>
                    <a href="{{ route('crm.customer-club.segments.export', ['segment' => $s['key'], 'format' => 'csv']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-600 text-white rounded-lg text-[11px]">CSV</a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- روندِ ثبت‌نام + سفارش (در بازهٔ انتخابی) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([['t' => 'روندِ ثبت‌نامِ مشتریان', 'd' => $acquisition_trend, 'color' => 'bg-blue-500'], ['t' => 'روندِ سفارش‌ها', 'd' => $order_trend, 'color' => 'bg-emerald-500']] as $chart)
        @php($maxV = max(1, collect($chart['d']['points'])->max('count')))
        @php($gran = ['day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه'][$chart['d']['granularity']] ?? '')
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex justify-between items-center mb-3">
                <div class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $chart['t'] }}</div>
                <div class="text-[10px] text-gray-400">{{ $windows[$filters['window']] ?? '' }} · {{ $gran }}</div>
            </div>
            <div class="flex items-end gap-0.5 h-40 overflow-x-auto">
                @foreach($chart['d']['points'] as $pt)
                    <div class="flex-1 min-w-[8px] flex flex-col items-center justify-end h-full" title="{{ $pt['label'] }}: {{ $fmt($pt['count']) }}">
                        <span class="text-[8px] text-gray-500" dir="ltr">{{ $pt['count'] ? $fmt($pt['count']) : '' }}</span>
                        <div class="w-full {{ $chart['color'] }} rounded-t" style="height: {{ (int) round($pt['count'] / $maxV * 100) }}%; min-height: {{ $pt['count'] > 0 ? '3px' : '0' }};"></div>
                        <span class="text-[7px] text-gray-400 mt-1 whitespace-nowrap" dir="ltr">{{ $pt['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Top دستگاه / برند / شهر (در بازهٔ انتخابی) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @php($devMax = max(1, collect($demand['top_devices'])->max('count')))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">بیشترین سفارش بر اساس دستگاه</div>
            @forelse($demand['top_devices'] as $row)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300"><span>{{ $row['name'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($row['count']) }}</span></div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-brand-500 rounded-full" style="width: {{ (int) round($row['count'] / $devMax * 100) }}%"></div></div>
                </div>
            @empty <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p> @endforelse
        </div>

        @php($brMax = max(1, collect($demand['top_brands']['items'])->max('count') ?: 1))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">بر اساس برند (برندهای واقعی)</div>
            @forelse($demand['top_brands']['items'] as $row)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300"><span>{{ $row['name'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($row['count']) }}</span></div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-indigo-500 rounded-full" style="width: {{ (int) round($row['count'] / $brMax * 100) }}%"></div></div>
                </div>
            @empty <p class="text-[12px] text-gray-400">برندِ واقعی‌ای در این بازه نیست.</p> @endforelse
            @if($demand['top_brands']['unspecified'] > 0)
                <div class="mt-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-700 text-[11px] text-amber-700 dark:text-amber-300">
                    بدونِ برندِ مشخص: <b dir="ltr">{{ $fmt($demand['top_brands']['unspecified']) }}</b>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ $demand['top_brands']['unspecified_note'] }}</div>
                </div>
            @endif
        </div>

        @php($ctMax = max(1, collect($demand['top_cities'])->max('count') ?: 1))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">بر اساس شهر</div>
            @forelse($demand['top_cities'] as $row)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300"><span>{{ $row['name'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($row['count']) }}</span></div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-cyan-500 rounded-full" style="width: {{ (int) round($row['count'] / $ctMax * 100) }}%"></div></div>
                </div>
            @empty <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p> @endforelse
        </div>
    </div>

    {{-- فانلِ وضعیت --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">سفارش‌ها به تفکیک وضعیت (در بازه)</div>
        <div class="flex flex-wrap gap-2">
            @forelse($demand['status_funnel'] as $s)
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs {{ $s['badge'] }}"><span>{{ $s['label'] }}</span><span class="font-black" dir="ltr">{{ $fmt($s['count']) }}</span></span>
            @empty <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p> @endforelse
        </div>
    </div>

    {{-- Heatmap ساعت × روزِ هفته (در بازه) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto">
        <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">ساعاتِ اوجِ سفارش (ساعت × روزِ هفته) — {{ $windows[$filters['window']] ?? '' }}</div>
        <table class="text-[10px] border-collapse">
            <thead><tr><th class="p-1 sticky right-0 bg-white dark:bg-gray-800"></th>
                @for($h = 0; $h < 24; $h++)<th class="p-1 text-gray-400 font-normal" dir="ltr">{{ $h }}</th>@endfor
                <th class="p-1 text-gray-500">جمع</th></tr></thead>
            <tbody>
                @foreach($heatmap['rows'] as $row)
                    <tr>
                        <td class="p-1 font-bold text-gray-600 dark:text-gray-300 whitespace-nowrap sticky right-0 bg-white dark:bg-gray-800">{{ $row['weekday'] }}</td>
                        @foreach($row['hours'] as $hh => $count)
                            @php($op = $heatmap['max'] > 0 ? round($count / $heatmap['max'], 2) : 0)
                            <td class="w-6 h-6 text-center align-middle rounded" dir="ltr"
                                style="background: rgba(0,80,157,{{ $op }}); color: {{ $op > 0.5 ? '#fff' : '#6b7280' }};"
                                title="{{ $row['weekday'] }} ساعت {{ $hh }}: {{ $fmt($count) }}">{{ $count ?: '' }}</td>
                        @endforeach
                        <td class="p-1 font-black text-gray-700 dark:text-gray-200" dir="ltr">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- RFM: ماتریسِ R×F + سگمنت‌ها --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-1">ماتریسِ RFM (تازگی × تکرار)</div>
            <div class="text-[10px] text-gray-400 mb-3">هر خانه = تعدادِ مشتری با آن امتیازِ تازگی (R) و تکرار (F). امتیاز ۱ تا ۵ بر پایهٔ چارک‌پنجم.</div>
            @php($mMax = 0)@foreach($rfm['matrix'] as $fr)@foreach($fr as $v)@php($mMax = max($mMax, $v))@endforeach @endforeach
            <table class="text-[11px] border-collapse mx-auto">
                <thead><tr><th class="p-1 text-gray-400">R\F</th>@for($f = 1; $f <= 5; $f++)<th class="p-1 text-gray-500 w-12" dir="ltr">F{{ $f }}</th>@endfor</tr></thead>
                <tbody>
                    @for($r = 5; $r >= 1; $r--)
                        <tr>
                            <td class="p-1 font-bold text-gray-500" dir="ltr">R{{ $r }}</td>
                            @for($f = 1; $f <= 5; $f++)
                                @php($v = $rfm['matrix'][$r][$f] ?? 0)
                                @php($op = $mMax > 0 ? round($v / $mMax, 2) : 0)
                                <td class="w-12 h-9 text-center align-middle rounded font-bold" dir="ltr"
                                    style="background: rgba(124,58,237,{{ $op }}); color: {{ $op > 0.5 ? '#fff' : '#6b7280' }};">{{ $v ?: '' }}</td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>
            <div class="text-[10px] text-gray-400 mt-2">R۵ = تازه‌ترین خرید · F۵ = بیشترین تکرار · تعدادِ کلِ مشتریانِ دارای سفارش: <b dir="ltr">{{ $fmt($rfm['count']) }}</b></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">سگمنت‌های RFM</div>
            @php($segMax = max(1, collect($rfm['segments'])->max('count') ?: 1))
            @forelse($rfm['segments'] as $seg)
                <div class="mb-1.5">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300"><span>{{ $seg['label'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($seg['count']) }}</span></div>
                    <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-violet-500 rounded-full" style="width: {{ (int) round($seg['count'] / $segMax * 100) }}%"></div></div>
                </div>
            @empty <p class="text-[12px] text-gray-400">هنوز مشتریِ دارای سفارشی نیست.</p> @endforelse
        </div>
    </div>

    <p class="text-[11px] text-gray-400 leading-5">
        «مشتریِ اپ» = موبایلِ تأییدشده با OTP (ورود از اپ/سایت)؛ مشتریانِ واردشده از وردپرس/تلفنیِ قدیمی (تأییدنشده) و حساب‌های بلاک/حذف‌شده شمرده نمی‌شوند — به همین دلیل «کل مشتریان» کلِ جدولِ خام نیست.
        کارت‌های «کل/تبدیل/تکراری/VIP/ریزش/RFM» مادام‌العمرند؛ «روند/تقاضا/ساعاتِ اوج» در بازهٔ انتخابیِ بالا.
        ساعت/روزِ هفته به وقتِ محلی است.
    </p>
</div>
@endsection
