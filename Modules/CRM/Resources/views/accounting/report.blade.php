@extends('layouts.admin')

@section('page-title', 'گزارش هزینه‌ها')

@section('main')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">گزارش هزینه‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">تجمیع بر اساس {{ $groups[$group] }}</p>
        </div>
        <a href="{{ route('crm.costs.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">← لیست هزینه‌ها</a>
    </div>

    {{-- انتخاب نوع گروه‌بندی --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm px-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            @foreach($groups as $key => $label)
                <a href="{{ route('crm.costs.report', array_merge(request()->except('page'), ['group' => $key])) }}"
                   class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                          {{ $group === $key ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @include('crm::accounting._filters', ['action' => route('crm.costs.report'), 'extraHidden' => ['group' => $group]])

    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">جمع کل (با فیلتر فعلی)</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($grandTotal) }} <span class="text-xs font-normal text-gray-400">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">تعداد سند هزینه</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($grandCount) }}</div>
        </div>
    </div>

    {{-- نمودارها — دایره‌ای (سهم هر {{ $groups[$group] }}) + خطی روزانه --}}
    @if($grandCount > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
                <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">سهم هر {{ $groups[$group] }}</div>
                <div id="expensePie"></div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 lg:col-span-2">
                <div class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                    روند روزانه بر اساس {{ $groups[$group] }}
                    <span class="text-[10px] font-normal text-gray-400">
                        ({{ $filters['from_date'] !== '' || $filters['to_date'] !== '' ? 'بازهٔ انتخابی' : '۳۰ روز اخیر' }})
                    </span>
                </div>
                <div id="expenseLine"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var pieData = @json($pie);
            var lineData = @json($lineChart);
            var fontStack = 'Vazirmatn, system-ui, sans-serif';
            var fmt = function (v) { return Number(v || 0).toLocaleString('fa-IR'); };

            if (pieData.values.length) {
                new ApexCharts(document.querySelector('#expensePie'), {
                    chart: { type: 'donut', height: 300, fontFamily: fontStack },
                    series: pieData.values,
                    labels: pieData.labels,
                    legend: { position: 'bottom', fontSize: '11px' },
                    dataLabels: { formatter: function (val) { return val.toFixed(1) + '٪'; } },
                    tooltip: { y: { formatter: function (v) { return fmt(v) + ' تومان'; } } },
                }).render();
            }

            if (lineData.series.length) {
                new ApexCharts(document.querySelector('#expenseLine'), {
                    chart: { type: 'area', height: 300, fontFamily: fontStack, toolbar: { show: false }, zoom: { enabled: false } },
                    series: lineData.series,
                    xaxis: { categories: lineData.dates, labels: { rotate: -45, style: { fontSize: '10px' } }, tickAmount: Math.min(15, lineData.dates.length) },
                    yaxis: { labels: { formatter: fmt } },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
                    dataLabels: { enabled: false },
                    legend: { position: 'bottom', fontSize: '11px' },
                    tooltip: { y: { formatter: function (v) { return fmt(v) + ' تومان'; } } },
                }).render();
            }
        });
        </script>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">{{ $groups[$group] }}</th>
                    <th class="p-3 text-start">تعداد سند</th>
                    <th class="p-3 text-start">جمع مبلغ (تومان)</th>
                    <th class="p-3 text-start w-1/3">سهم از کل</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($rows as $row)
                    @php
                        $pct = $grandTotal > 0 ? round(((int) $row->total / $grandTotal) * 100, 1) : 0;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 font-medium">{{ $row->label }}</td>
                        <td class="p-3 text-gray-600">{{ number_format($row->count) }}</td>
                        <td class="p-3 font-bold">{{ number_format($row->total) }}</td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-brand-600 rounded-full" style="width: {{ min(100, $pct) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-12">{{ $pct }}٪</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-8 text-center text-sm text-gray-400">داده‌ای برای این گزارش وجود ندارد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($group === 'tag' && $rows->isNotEmpty())
        <p class="text-xs text-gray-400">
            توجه: هزینه‌ای که چند تگ دارد در سطر هر تگ جداگانه جمع می‌شود؛ بنابراین جمع سطرهای این گزارش می‌تواند از «جمع کل» بیشتر باشد.
        </p>
    @endif
</div>
@endsection
