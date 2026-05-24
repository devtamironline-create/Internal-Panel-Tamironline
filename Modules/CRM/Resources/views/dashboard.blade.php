@extends('layouts.admin')

@section('page-title', 'داشبورد CRM')

@section('main')
@php
    use Modules\CRM\Enums\OrderStatus;
@endphp

<div class="p-4 md:p-6 space-y-5">

    {{-- ─── هدر + فیلتر بازه ─── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">📊 داشبورد خدمات تعمیرات</h1>
            <p class="text-xs text-gray-500 mt-1">آمار سفارشات بازهٔ انتخابی + تأخیرها + روند کل</p>
        </div>
        <form method="GET" class="flex items-end gap-2 flex-wrap">
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-1">از تاریخ</label>
                <input type="text" name="from_date" value="{{ $fromJ }}" dir="ltr" placeholder="1405/03/01" readonly
                       class="jalali-datepicker w-32 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white text-xs">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-1">تا تاریخ</label>
                <input type="text" name="to_date" value="{{ $toJ }}" dir="ltr" placeholder="1405/03/31" readonly
                       class="jalali-datepicker w-32 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white text-xs">
            </div>
            <input type="hidden" name="chart_period" value="{{ $chartPeriod }}">
            <button class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold">اعمال</button>
        </form>
    </div>

    {{-- ─── کارت‌های خلاصه ─── --}}
    @php
        $box = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 border border-gray-100 dark:border-gray-700';
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">سفارشات بازه</div>
            <div class="text-xl font-bold mt-1 text-gray-900 dark:text-gray-100">{{ number_format($ordersInRange) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">انجام شده</div>
            <div class="text-xl font-bold mt-1 text-emerald-600">{{ number_format($ordersCompletedInRange) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">کنسل / رد</div>
            <div class="text-xl font-bold mt-1 text-rose-600">{{ number_format($ordersCancelledInRange) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">باز (کل)</div>
            <div class="text-xl font-bold mt-1 text-amber-600">{{ number_format($ordersOpenAll) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">مشتریان (کل)</div>
            <div class="text-xl font-bold mt-1 text-sky-600">{{ number_format($customersTotal) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="text-[10px] text-gray-500 uppercase">تکنسین فعال</div>
            <div class="text-xl font-bold mt-1 text-indigo-600">{{ number_format($techsActive) }}</div>
        </div>
    </div>

    {{-- ─── 1) کاشی‌های نحوه آشنایی ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">🪟 سفارشات به تفکیک نحوه آشنایی</h2>
            <span class="text-[10px] text-gray-500">{{ number_format($introTotal) }} سفارش با نحوه آشنایی + {{ number_format($introNullCount) }} بدون</span>
        </div>
        @if($introBreakdown->isEmpty())
            <div class="text-center py-6 text-gray-400 text-xs">در این بازه سفارشی با نحوه آشنایی ثبت نشده.</div>
        @else
        @php
            $colors = [
                'bg-sky-50 border-sky-200 text-sky-800',
                'bg-emerald-50 border-emerald-200 text-emerald-800',
                'bg-purple-50 border-purple-200 text-purple-800',
                'bg-amber-50 border-amber-200 text-amber-800',
                'bg-rose-50 border-rose-200 text-rose-800',
                'bg-indigo-50 border-indigo-200 text-indigo-800',
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach($introBreakdown as $i => $intro)
                @php
                    $color = $colors[$i % count($colors)];
                    $pct = $introTotal > 0 ? round(($intro->cnt / $introTotal) * 100, 1) : 0;
                @endphp
                <a href="{{ route('crm.orders.index', ['introduction' => $intro->introduction, 'from_date' => $fromJ, 'to_date' => $toJ]) }}"
                   class="block p-3 rounded-xl border-2 {{ $color }} hover:scale-105 transition-transform">
                    <div class="text-xs font-bold truncate" title="{{ $intro->introduction }}">{{ $intro->introduction }}</div>
                    <div class="text-2xl font-bold mt-2" dir="ltr">{{ number_format($intro->cnt) }}</div>
                    <div class="text-[10px] mt-1 opacity-75">{{ $pct }}%</div>
                </a>
            @endforeach
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- ─── 2) پرتکرارترین خدمات (دستگاه‌ها) ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">⭐ ۵ خدمت پرتکرار (در بازه)</h2>
            @if($topDevices->isEmpty())
                <div class="text-center py-6 text-gray-400 text-xs">داده‌ای نیست.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-[10px] text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-right">دستگاه</th>
                            <th class="px-3 py-2 text-center">کل</th>
                            <th class="px-3 py-2 text-center text-emerald-700">انجام شده</th>
                            <th class="px-3 py-2 text-center text-rose-700">کنسل / رد</th>
                            <th class="px-3 py-2 text-center text-gray-400">سایر</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($topDevices as $idx => $d)
                            @php $other = (int) $d->total_count - (int) $d->completed_count - (int) $d->cancelled_count; @endphp
                            <tr>
                                <td class="px-3 py-2 font-bold">
                                    <span class="inline-block w-5 h-5 leading-5 text-center bg-indigo-100 text-indigo-700 rounded-full me-1 text-[10px]">{{ $idx + 1 }}</span>
                                    {{ $d->device?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center font-bold">{{ number_format($d->total_count) }}</td>
                                <td class="px-3 py-2 text-center text-emerald-700">{{ number_format($d->completed_count) }}</td>
                                <td class="px-3 py-2 text-center text-rose-700">{{ number_format($d->cancelled_count) }}</td>
                                <td class="px-3 py-2 text-center text-gray-500">{{ number_format(max(0, $other)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ─── 5) نمودار روند ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">📈 روند سفارشات</h2>
                <div class="inline-flex rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                    @php $baseQ = request()->query(); @endphp
                    <a href="{{ route('crm.dashboard', array_merge($baseQ, ['chart_period' => 'week'])) }}"
                       class="px-3 py-1 text-xs {{ $chartPeriod === 'week' ? 'bg-brand-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700' }}">هفتگی (۱۲)</a>
                    <a href="{{ route('crm.dashboard', array_merge($baseQ, ['chart_period' => 'month'])) }}"
                       class="px-3 py-1 text-xs {{ $chartPeriod === 'month' ? 'bg-brand-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700' }}">ماهانه (۶)</a>
                </div>
            </div>
            <div id="orders-trend-chart" style="min-height:280px"></div>
        </div>
    </div>

    {{-- ─── 6) سفارشات با تأخیر بیش از ۳ روز ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-bold text-rose-700">⚠ سفارشات با تأخیر بیش از ۳ روز پس از تخصیص</h2>
                <p class="text-[11px] text-gray-500 mt-1">سفارشاتی که به تکنسین تخصیص داده شده‌اند ولی هنوز انجام نشده‌اند.</p>
            </div>
            <span class="px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold">{{ number_format($delayedOpenCount) }} سفارش</span>
        </div>
        @if($delayedOpenOrders->isEmpty())
            <div class="p-8 text-center text-gray-400 text-sm">🎉 هیچ سفارش معطل بیش از ۳ روزی نیست!</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs">
                    <tr>
                        <th class="px-3 py-2 text-right text-gray-500">کد سفارش</th>
                        <th class="px-3 py-2 text-right text-gray-500">دستگاه</th>
                        <th class="px-3 py-2 text-right text-gray-500">مشتری</th>
                        <th class="px-3 py-2 text-right text-gray-500">تکنسین</th>
                        <th class="px-3 py-2 text-right text-gray-500">وضعیت</th>
                        <th class="px-3 py-2 text-right text-gray-500">تخصیص</th>
                        <th class="px-3 py-2 text-right text-gray-500">تأخیر</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($delayedOpenOrders as $o)
                    @php
                        $days = $o->assigned_at ? (int) $o->assigned_at->diffInDays(now()) : 0;
                    @endphp
                    <tr class="hover:bg-rose-50/30 dark:hover:bg-rose-900/10">
                        <td class="px-3 py-2 text-xs">
                            <a href="{{ route('crm.orders.show', $o->id) }}" target="_blank" class="text-brand-600 hover:underline" dir="ltr">{{ $o->order_code }}</a>
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $o->brand?->name ?? '—' }} / {{ $o->device?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">
                            {{ $o->customer?->first_name ?? '—' }}
                            @if($o->customer?->mobile)<br><span class="text-[10px] text-gray-500" dir="ltr">{{ $o->customer->mobile }}</span>@endif
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $o->technician ? trim($o->technician->firstname_tech ?: ($o->technician->first_name . ' ' . ($o->technician->last_name ?? ''))) : '—' }}</td>
                        <td class="px-3 py-2 text-xs"><span class="px-2 py-0.5 rounded-full text-[10px] {{ $o->status?->badgeClass() ?? 'bg-gray-100' }}">{{ $o->status?->label() ?? '—' }}</span></td>
                        <td class="px-3 py-2 text-[10px] text-gray-500" dir="ltr">@jdatetime($o->assigned_at)</td>
                        <td class="px-3 py-2 text-xs font-bold {{ $days >= 7 ? 'text-rose-700' : 'text-amber-700' }}">{{ $days }} روز</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($delayedOpenCount > $delayedOpenOrders->count())
                <div class="p-3 text-center text-xs text-gray-500">
                    {{ number_format($delayedOpenOrders->count()) }} مورد اول از {{ number_format($delayedOpenCount) }} نمایش داده شد.
                </div>
            @endif
        </div>
        @endif
    </div>

</div>

<script src="/vendor/js/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = @json($chartData);
    if (typeof ApexCharts === 'undefined') return;

    const options = {
        chart: {
            type: 'bar',
            height: 280,
            stacked: false,
            toolbar: { show: false },
            fontFamily: 'inherit',
        },
        plotOptions: {
            bar: { columnWidth: '60%', borderRadius: 4 },
        },
        series: [
            { name: 'کل سفارشات', data: chartData.totals, color: '#6366f1' },
            { name: 'انجام شده', data: chartData.completed, color: '#10b981' },
            { name: 'کنسل / رد', data: chartData.cancelled, color: '#ef4444' },
        ],
        xaxis: {
            categories: chartData.labels,
            labels: { style: { fontSize: '10px' } },
        },
        yaxis: { labels: { style: { fontSize: '10px' } } },
        dataLabels: { enabled: false },
        legend: { position: 'top', fontSize: '11px' },
        tooltip: {
            y: {
                formatter: function (val, opts) {
                    const totals = chartData.totals[opts.dataPointIndex];
                    if (opts.seriesIndex === 0 || totals === 0) return val;
                    const pct = ((val / totals) * 100).toFixed(1);
                    return val + ' (' + pct + '٪)';
                }
            }
        },
    };

    const chart = new ApexCharts(document.querySelector('#orders-trend-chart'), options);
    chart.render();
});
</script>
@endsection
