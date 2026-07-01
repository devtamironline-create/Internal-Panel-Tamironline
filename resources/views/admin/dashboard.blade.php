@extends('layouts.admin')
@section('page-title', 'داشبورد')
@section('main')
@php($r = $stats['repair'] ?? [])
<div class="space-y-6">

    {{-- ─── خوش‌آمد ─── --}}
    <div class="bg-gradient-to-r from-gray-700 to-gray-800 dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-sm p-6 text-white border border-gray-600 dark:border-gray-700">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-xl font-bold">سلام {{ auth()->user()->first_name }}!</h2>
                <p class="text-gray-300 mt-1">{{ \Morilog\Jalali\Jalalian::now()->format('l، j F Y') }}</p>
                <p class="text-gray-400 text-sm mt-1">وضعیت کلیِ خدمات تعمیرات</p>
            </div>
            @canany(['view-crm-dashboard', 'view-crm-orders', 'manage-permissions'])
            @if(Route::has('crm.dashboard'))
            <a href="{{ route('crm.dashboard') }}" class="px-4 py-2 bg-white/15 rounded-lg hover:bg-white/25 transition text-sm font-medium">
                داشبورد کاملِ تعمیرات ↗
            </a>
            @endif
            @endcanany
        </div>
    </div>

    @canany(['view-crm-dashboard', 'view-crm-orders', 'view-crm-reports', 'manage-permissions'])

    {{-- ─── کاشی‌های وضعیت ─── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($r['open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">سفارش‌های باز</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($r['today_new'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ثبت‌شده امروز</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($r['today_completed'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکمیل‌شده امروز</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($r['month_total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">سفارش‌های {{ $jMonthLabel }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border {{ ($r['delayed_open'] ?? 0) > 0 ? 'border-rose-200 dark:border-rose-800' : 'border-gray-100 dark:border-gray-700' }}">
            <p class="text-3xl font-bold {{ ($r['delayed_open'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-gray-100' }}">{{ number_format($r['delayed_open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">معطل (+۳ روز)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($r['techs_active'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکنسین فعال</p>
        </div>
    </div>

    {{-- ─── تحلیلِ ماه ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">تکمیل‌شدهٔ {{ $jMonthLabel }}</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($r['month_completed'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">لغو/رد‌شدهٔ {{ $jMonthLabel }}</p>
            <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ number_format($r['month_cancelled'] ?? 0) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">نرخِ تکمیل ({{ $jMonthLabel }})</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($r['completion_rate'] ?? 0) }}٪</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">کل مشتری‌ها</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($r['customers_total'] ?? 0) }}</p>
        </div>
    </div>

    {{-- ─── نمودارها: امروز به تفکیک ساعت + روند ۱۴ روز ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">سفارش‌های امروز به تفکیکِ ساعت</h3>
            <p class="text-xs text-gray-400 mb-2">ثبت‌شده و تکمیل‌شده در هر ساعت</p>
            <div id="chart-hourly"></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">روندِ ۱۴ روزِ اخیر</h3>
            <p class="text-xs text-gray-400 mb-2">کل / تکمیل‌شده / لغو‌شده (تاریخِ شمسی)</p>
            <div id="chart-trend"></div>
        </div>
    </div>

    {{-- ─── نمودارها: توزیعِ وضعیت + پرتکرارترین دستگاه‌ها ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">توزیعِ وضعیتِ سفارش‌ها ({{ $jMonthLabel }})</h3>
            <p class="text-xs text-gray-400 mb-2">سهمِ هر وضعیت از سفارش‌های این ماه</p>
            @if(!empty($statusBreakdown['data']))
                <div id="chart-status"></div>
            @else
                <p class="text-center text-gray-400 py-10 text-sm">داده‌ای برای این ماه نیست.</p>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">پرتکرارترین دستگاه‌ها ({{ $jMonthLabel }})</h3>
            <p class="text-xs text-gray-400 mb-2">بیشترین سفارشِ تعمیر در این ماه</p>
            @if(!empty($topDevices['data']))
                <div id="chart-devices"></div>
            @else
                <p class="text-center text-gray-400 py-10 text-sm">داده‌ای برای این ماه نیست.</p>
            @endif
        </div>
    </div>

    {{-- ─── آخرین سفارش‌های تعمیر ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">آخرین سفارش‌های تعمیر</h3>
            @if(Route::has('crm.orders.index'))
            <a href="{{ route('crm.orders.index') }}" class="text-xs text-blue-600 hover:underline">همهٔ سفارش‌ها ↗</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-right font-medium">مشتری</th>
                        <th class="px-4 py-2 text-right font-medium">دستگاه</th>
                        <th class="px-4 py-2 text-right font-medium">تکنسین</th>
                        <th class="px-4 py-2 text-right font-medium">وضعیت</th>
                        <th class="px-4 py-2 text-right font-medium">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($recentOrders as $o)
                        @php($tech = trim(($o->technician->first_name ?? '').' '.($o->technician->last_name ?? '')) ?: ($o->technician->firstname_tech ?? '—'))
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-2 text-gray-800 dark:text-gray-100">
                                @if(Route::has('crm.orders.show'))
                                <a href="{{ route('crm.orders.show', $o) }}" class="hover:underline">{{ $o->customer->first_name ?? '—' }}</a>
                                @else
                                {{ $o->customer->first_name ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $o->device->name ?? '—' }}{{ $o->brand ? ' / '.$o->brand->name : '' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $tech }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $o->status?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">{{ $o->status?->label() ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $o->created_at ? \Morilog\Jalali\Jalalian::fromCarbon($o->created_at)->format('Y/m/d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">سفارشی برای نمایش نیست.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-gray-500 border border-gray-100 dark:border-gray-700">
        به پنل تعمیرآنلاین خوش آمدید.
    </div>
    @endcanany

    {{-- ─── تولدهای پیشِ‌رو ─── --}}
    @if(!empty($stats['birthdays']['today']) || !empty($stats['birthdays']['upcoming']))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">🎂 تولدهای پیشِ‌رو</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($stats['birthdays']['today'] as $b)
                <span class="px-3 py-1.5 rounded-lg bg-pink-50 dark:bg-pink-900/20 text-pink-700 dark:text-pink-300 text-sm">🎉 {{ $b['name'] }} — امروز ({{ $b['age'] }} سالگی)</span>
            @endforeach
            @foreach($stats['birthdays']['upcoming'] as $b)
                <span class="px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm">{{ $b['name'] }} — {{ $b['jalali_date'] }} ({{ $b['days_until'] }} روز دیگر)</span>
            @endforeach
        </div>
    </div>
    @endif

</div>

@canany(['view-crm-dashboard', 'view-crm-orders', 'view-crm-reports', 'manage-permissions'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const axisColor = isDark ? '#9ca3af' : '#6b7280';
    const base = {
        chart: { fontFamily: 'inherit', toolbar: { show: false }, foreColor: axisColor },
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? '#374151' : '#f1f5f9' },
        legend: { position: 'top', fontSize: '11px' },
    };

    // ۱) امروز به تفکیک ساعت
    const hourly = @json($hourly);
    new ApexCharts(document.querySelector('#chart-hourly'), Object.assign({}, base, {
        chart: Object.assign({ type: 'area', height: 260 }, base.chart),
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        series: [
            { name: 'ثبت‌شده', data: hourly.created, color: '#3b82f6' },
            { name: 'تکمیل‌شده', data: hourly.completed, color: '#10b981' },
        ],
        xaxis: { categories: hourly.labels, tickAmount: 12, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '10px' }, formatter: (v) => Math.round(v) } },
    })).render();

    // ۲) روند ۱۴ روز
    const trend = @json($trend);
    new ApexCharts(document.querySelector('#chart-trend'), Object.assign({}, base, {
        chart: Object.assign({ type: 'bar', height: 260 }, base.chart),
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
        series: [
            { name: 'کل', data: trend.totals, color: '#6366f1' },
            { name: 'تکمیل', data: trend.completed, color: '#10b981' },
            { name: 'لغو', data: trend.cancelled, color: '#ef4444' },
        ],
        xaxis: { categories: trend.labels, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '10px' }, formatter: (v) => Math.round(v) } },
    })).render();

    // ۳) توزیع وضعیت (donut)
    const status = @json($statusBreakdown);
    if (status.data && status.data.length) {
        new ApexCharts(document.querySelector('#chart-status'), {
            chart: { type: 'donut', height: 280, fontFamily: 'inherit', foreColor: axisColor },
            series: status.data,
            labels: status.labels,
            colors: status.colors,
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '٪' },
            plotOptions: { pie: { donut: { size: '58%' } } },
        }).render();
    }

    // ۴) پرتکرارترین دستگاه‌ها (میله افقی)
    const devices = @json($topDevices);
    if (devices.data && devices.data.length) {
        new ApexCharts(document.querySelector('#chart-devices'), Object.assign({}, base, {
            chart: Object.assign({ type: 'bar', height: 280 }, base.chart),
            plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } },
            series: [{ name: 'سفارش', data: devices.data, color: '#8b5cf6' }],
            xaxis: { categories: devices.labels, labels: { style: { fontSize: '10px' } } },
        })).render();
    }
});
</script>
@endcanany
@endsection
