@extends('layouts.admin')

@section('page-title', 'داشبورد لیدها')

@section('main')
<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">داشبورد لیدها</h1>
            <p class="text-xs text-gray-500 mt-1">تحلیل تماس‌هایی که سفارش نشده‌اند — بازهٔ زمانی، شهر، منطقه، دستگاه، دلیل.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.leads.index') }}" class="text-xs px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">لیست لیدها →</a>
        </div>
    </div>

    {{-- فیلتر تاریخ + بازهٔ نمودار --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">از تاریخ</label>
                <input type="text" name="from" value="{{ $fromJ }}" placeholder="1405/03/01" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تا تاریخ</label>
                <input type="text" name="to" value="{{ $toJ }}" placeholder="1405/03/30" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">بازهٔ نمودار</label>
                <select name="chart_period" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="day" @selected($chartPeriod === 'day')>روزانه (۳۰ روز)</option>
                    <option value="week" @selected($chartPeriod === 'week')>هفتگی (۱۲ هفته)</option>
                    <option value="month" @selected($chartPeriod === 'month')>ماهانه (۶ ماه)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm rounded-lg">اعمال</button>
            </div>
        </div>
    </form>

    {{-- کارت‌های خلاصه --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 border-rose-500">
            <div class="text-xs text-gray-500">کل لیدها در بازه</div>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ number_format($totalLeads) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 border-emerald-500">
            <div class="text-xs text-gray-500">تبدیل‌شده به سفارش</div>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($convertedInRange) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-r-4 border-amber-500">
            <div class="text-xs text-gray-500">نرخ تبدیل</div>
            <div class="text-2xl font-bold text-amber-700 mt-1">
                {{ $totalLeads > 0 ? number_format($convertedInRange / ($totalLeads + $convertedInRange) * 100, 1) : '0' }}٪
            </div>
        </div>
    </div>

    {{-- نمودار روند زمانی --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">روند ثبت لیدها</h2>
        <canvas id="leadsChart" style="max-height: 280px;"></canvas>
    </div>

    {{-- دلایل عدم سفارش --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">دلایل عدم سفارش</h2>
            @if($reasonBreakdown->isNotEmpty())
                <div class="space-y-2">
                    @php $maxR = $reasonBreakdown->max('count'); @endphp
                    @foreach($reasonBreakdown as $row)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ $row['name'] }}</span>
                                <span class="font-bold text-rose-700">{{ number_format($row['count']) }}</span>
                            </div>
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-rose-500 h-full" style="width: {{ $maxR > 0 ? round($row['count'] / $maxR * 100) : 0 }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                    @if($reasonNullCount > 0)
                        <div class="text-xs text-gray-500 mt-3 pt-2 border-t border-gray-100">
                            ⚠ {{ number_format($reasonNullCount) }} لید بدون دلیل ثبت‌شده (NULL)
                        </div>
                    @endif
                </div>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای در این بازه پیدا نشد.</p>
            @endif
        </div>

        {{-- معرف‌ها --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">معرف‌ها</h2>
            @if($introBreakdown->isNotEmpty())
                <div class="space-y-2">
                    @php $maxI = $introBreakdown->max('cnt'); @endphp
                    @foreach($introBreakdown as $row)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-700 dark:text-gray-300">{{ $row->introduction }}</span>
                                <span class="font-bold text-blue-700">{{ number_format($row->cnt) }}</span>
                            </div>
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-500 h-full" style="width: {{ $maxI > 0 ? round($row->cnt / $maxI * 100) : 0 }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
            @endif
        </div>
    </div>

    {{-- پرتکرارترین دستگاه‌ها و برندها --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">پرتکرارترین دستگاه‌ها</h2>
            @if($topDevices->isNotEmpty())
                <ul class="text-sm space-y-1.5">
                    @foreach($topDevices as $d)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ $d->device?->name ?? '—' }}</span>
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ number_format($d->cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">پرتکرارترین برندها</h2>
            @if($topBrands->isNotEmpty())
                <ul class="text-sm space-y-1.5">
                    @foreach($topBrands as $b)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ $b->brand?->name ?? '—' }}</span>
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ number_format($b->cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
            @endif
        </div>
    </div>

    {{-- شهر و منطقه --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">پرتکرارترین شهرها</h2>
            @if($topCities->isNotEmpty())
                <ul class="text-sm space-y-1.5">
                    @foreach($topCities as $c)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ $c->city?->name ?? '—' }}</span>
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ number_format($c->cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">پرتکرارترین مناطق</h2>
            @if($topRegions->isNotEmpty())
                <ul class="text-sm space-y-1.5">
                    @foreach($topRegions as $r)
                        <li class="flex items-center justify-between">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ $r->region?->name ?? '—' }}
                                <span class="text-xs text-gray-400">({{ $r->region?->city?->name }})</span>
                            </span>
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ number_format($r->cnt) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
            @endif
        </div>
    </div>

    {{-- ایرادها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">پرتکرارترین ایرادهای مطرح‌شده</h2>
        @if($topProblems->isNotEmpty())
            <ul class="text-sm space-y-1.5">
                @foreach($topProblems as $p)
                    <li class="flex items-center justify-between">
                        <span class="text-gray-700 dark:text-gray-300 truncate" title="{{ $p->problem_title }}">{{ \Illuminate\Support\Str::limit($p->problem_title, 80) }}</span>
                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded ms-2 shrink-0">{{ number_format($p->cnt) }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-xs text-gray-400 text-center py-8">داده‌ای پیدا نشد.</p>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    const ctx = document.getElementById('leadsChart');
    if (! ctx) return;
    const data = @json($chartData);
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'تعداد لید',
                data: data.counts,
                borderColor: 'rgb(244, 63, 94)',
                backgroundColor: 'rgba(244, 63, 94, 0.1)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
})();
</script>
@endsection
