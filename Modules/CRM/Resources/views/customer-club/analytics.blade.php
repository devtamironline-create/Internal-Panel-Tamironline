@extends('layouts.admin')

@section('page-title', 'باشگاه مشتریان — تحلیل ورودی‌ها')

@section('main')
@php
    $fmt = fn ($n) => number_format((int) $n);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<div class="p-6 space-y-5" dir="rtl">

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">👥 باشگاه مشتریان — تحلیل ورودی‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">اکتساب، تبدیل، تقاضا، زمان‌بندی، درآمد و نگه‌داشتِ مشتریان اپ و پنل.</p>
        </div>
    </div>

    {{-- فیلترها --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-wrap items-end gap-3">
        <label class="block text-xs text-gray-500">بازهٔ زمانی
            <select name="range" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                @foreach($ranges as $key => $label)
                    <option value="{{ $key }}" @selected($filters['range'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if($sources)
        <label class="block text-xs text-gray-500">منبع سفارش
            <select name="source" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach($sources as $s)
                    <option value="{{ $s }}" @selected($filters['source'] === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </label>
        @endif
        @if($cities)
        <label class="block text-xs text-gray-500">شهر
            <select name="city_id" class="mt-1 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همهٔ شهرها</option>
                @foreach($cities as $c)
                    <option value="{{ $c['id'] }}" @selected((string) $filters['city_id'] === (string) $c['id'])>{{ $c['name'] }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <button class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">اعمال</button>
        <a href="{{ route('crm.customer-club.analytics') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    {{-- کارت‌های KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        @php($cards = [
            ['label' => 'کل مشتریان', 'value' => $fmt($acquisition['total']), 'sub' => $fmt($acquisition['new_in_range']).' ثبت‌نام در این بازه', 'tone' => 'gray'],
            ['label' => 'نرخ تبدیل به سفارش', 'value' => $pct($conversion['rate']).'٪', 'sub' => $fmt($conversion['with_orders']).' سفارش داده · '.$fmt($conversion['without_orders']).' نداده', 'tone' => 'blue'],
            ['label' => 'نرخ خرید تکراری', 'value' => $pct($retention['repeat_rate']).'٪', 'sub' => $fmt($retention['repeat']).' تکراری از '.$fmt($retention['with_orders']), 'tone' => 'purple'],
            ['label' => 'میانگین ارزش سفارش', 'value' => $fmt($revenue['aov']), 'sub' => 'تومان (AOV)', 'tone' => 'emerald'],
            ['label' => 'درآمدِ انجام‌شده', 'value' => $fmt($revenue['total_revenue']), 'sub' => $fmt($revenue['completed_orders']).' سفارشِ انجام‌شده', 'tone' => 'emerald'],
            ['label' => 'میانگین تا اولین سفارش', 'value' => $conversion['avg_days_to_first_order'] === null ? '—' : $pct($conversion['avg_days_to_first_order']), 'sub' => 'روز پس از ثبت‌نام', 'tone' => 'amber'],
        ])
        @foreach($cards as $card)
            <div @class([
                'rounded-xl border p-4',
                'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => $card['tone'] === 'gray',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' => $card['tone'] === 'blue',
                'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800' => $card['tone'] === 'purple',
                'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' => $card['tone'] === 'amber',
                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' => $card['tone'] === 'emerald',
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
            ['key' => 'vip', 'title' => 'مشتریانِ VIP', 'count' => $segments['vip'], 'desc' => 'حداقل ۳ سفارش — ارزشمندترین‌ها.', 'tone' => 'emerald'],
            ['key' => 'at-risk', 'title' => 'در خطرِ ریزش', 'count' => $segments['at_risk'], 'desc' => 'آخرین سفارش بینِ ۹۰ تا ۳۶۵ روزِ پیش.', 'tone' => 'rose'],
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

    {{-- روندِ ثبت‌نام + روندِ سفارش (۱۲ ماه) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([['عنوان' => 'روندِ ثبت‌نامِ مشتریان (۱۲ ماه)', 'data' => $acquisition['trend'], 'color' => 'bg-blue-500'], ['عنوان' => 'روندِ سفارش‌ها (۱۲ ماه)', 'data' => $temporal['monthly'], 'color' => 'bg-emerald-500']] as $chart)
        @php($maxV = max(1, collect($chart['data'])->max('count')))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">{{ $chart['عنوان'] }}</div>
            <div class="flex items-end gap-1 h-40">
                @foreach($chart['data'] as $pt)
                    <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $pt['label'] }}: {{ $fmt($pt['count']) }}">
                        <span class="text-[9px] text-gray-500" dir="ltr">{{ $pt['count'] ? $fmt($pt['count']) : '' }}</span>
                        <div class="w-full {{ $chart['color'] }} rounded-t" style="height: {{ (int) round($pt['count'] / $maxV * 100) }}%; min-height: {{ $pt['count'] > 0 ? '3px' : '0' }};"></div>
                        <span class="text-[8px] text-gray-400 mt-1 whitespace-nowrap" dir="ltr">{{ $pt['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Top دستگاه / برند / شهر --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach([['عنوان' => 'بیشترین سفارش بر اساس دستگاه', 'data' => $demand['top_devices']], ['عنوان' => 'بر اساس برند', 'data' => $demand['top_brands']], ['عنوان' => 'بر اساس شهر', 'data' => $demand['top_cities']]] as $tbl)
        @php($maxV = max(1, collect($tbl['data'])->max('count')))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">{{ $tbl['عنوان'] }}</div>
            @forelse($tbl['data'] as $row)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300">
                        <span>{{ $row['name'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($row['count']) }}</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5">
                        <div class="h-full bg-brand-500 rounded-full" style="width: {{ (int) round($row['count'] / $maxV * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p>
            @endforelse
        </div>
        @endforeach
    </div>

    {{-- فانلِ وضعیت + منبع سفارش --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">سفارش‌ها به تفکیک وضعیت</div>
            <div class="flex flex-wrap gap-2">
                @forelse($demand['status_funnel'] as $s)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs {{ $s['badge'] }}">
                        <span>{{ $s['label'] }}</span><span class="font-black" dir="ltr">{{ $fmt($s['count']) }}</span>
                    </span>
                @empty
                    <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">منبعِ سفارش</div>
            @forelse($demand['source_split'] as $s)
                <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300 mb-1.5">
                    <span>{{ $s['source'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($s['count']) }}</span>
                </div>
            @empty
                <p class="text-[12px] text-gray-400">ستونِ منبع موجود نیست یا داده‌ای نیست.</p>
            @endforelse
        </div>
    </div>

    {{-- Heatmap ساعت × روزِ هفته --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 overflow-x-auto">
        <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">ساعاتِ اوجِ سفارش (ساعت × روزِ هفته)</div>
        <table class="text-[10px] border-collapse">
            <thead>
                <tr>
                    <th class="p-1 text-gray-400 sticky right-0 bg-white dark:bg-gray-800"></th>
                    @for($h = 0; $h < 24; $h++)
                        <th class="p-1 text-gray-400 font-normal" dir="ltr">{{ $h }}</th>
                    @endfor
                    <th class="p-1 text-gray-500">جمع</th>
                </tr>
            </thead>
            <tbody>
                @foreach($temporal['heatmap'] as $row)
                    <tr>
                        <td class="p-1 font-bold text-gray-600 dark:text-gray-300 whitespace-nowrap sticky right-0 bg-white dark:bg-gray-800">{{ $row['weekday'] }}</td>
                        @foreach($row['hours'] as $count)
                            @php($op = $temporal['heatmap_max'] > 0 ? round($count / $temporal['heatmap_max'], 2) : 0)
                            <td class="w-6 h-6 text-center align-middle rounded" dir="ltr"
                                style="background: rgba(0,80,157,{{ $op }}); color: {{ $op > 0.5 ? '#fff' : '#6b7280' }};"
                                title="{{ $row['weekday'] }} ساعت {{ $loop->index }}: {{ $fmt($count) }}">{{ $count ?: '' }}</td>
                        @endforeach
                        <td class="p-1 font-black text-gray-700 dark:text-gray-200" dir="ltr">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- نگه‌داشت: توزیع سفارش + RFM + درآمد بر دستگاه --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">توزیعِ تعدادِ سفارش به‌ازای مشتری</div>
            @php($distMax = max(1, max($retention['distribution'])))
            @foreach($retention['distribution'] as $bucket => $count)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300">
                        <span>{{ $bucket }} سفارش</span><span dir="ltr" class="font-bold">{{ $fmt($count) }}</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5">
                        <div class="h-full bg-purple-500 rounded-full" style="width: {{ (int) round($count / $distMax * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">سگمنت‌بندیِ RFM</div>
            @php($segLabels = ['champions' => 'قهرمانان', 'loyal' => 'وفادار', 'new' => 'تازه‌وارد', 'at_risk' => 'در خطرِ ریزش', 'dormant' => 'خفته', 'others' => 'سایر'])
            @foreach($segLabels as $k => $label)
                <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300 mb-1.5">
                    <span>{{ $label }}</span><span dir="ltr" class="font-bold">{{ $fmt($retention['segments'][$k] ?? 0) }}</span>
                </div>
            @endforeach
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">درآمد بر اساس دستگاه</div>
            @php($revMax = max(1, collect($revenue['top_devices_by_revenue'])->max('total')))
            @forelse($revenue['top_devices_by_revenue'] as $row)
                <div class="mb-2">
                    <div class="flex justify-between text-[12px] text-gray-600 dark:text-gray-300">
                        <span>{{ $row['name'] }}</span><span dir="ltr" class="font-bold">{{ $fmt($row['total']) }}</span>
                    </div>
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden mt-0.5">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ (int) round($row['total'] / $revMax * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-[12px] text-gray-400">داده‌ای در این بازه نیست.</p>
            @endforelse
        </div>
    </div>

    <p class="text-[11px] text-gray-400 leading-5">
        نکته: تفکیکِ «کانالِ ثبت‌نام» (اپ/وب) در سطحِ مشتری در دیتابیس نگه‌داری نمی‌شود؛ تفکیکِ منبع از روی «منبعِ سفارش» است.
        ساعت/روزِ هفته به وقتِ محلی محاسبه شده است.
    </p>
</div>
@endsection
