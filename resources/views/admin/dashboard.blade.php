@extends('layouts.admin')
@section('page-title', 'داشبورد')
@section('main')
@php($snap = $stats['snapshot'] ?? [])
@php($rng = $stats['range'] ?? [])
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

    {{-- ─── فیلترِ بازهٔ تاریخِ شمسی ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 flex items-center gap-2 flex-wrap border border-gray-100 dark:border-gray-700"
         x-data="{ showCustom: {{ $activePreset === 'custom' ? 'true' : 'false' }} }">
        @foreach($presets as $key => $p)
            <a href="{{ route('admin.dashboard', ['from_date' => $p['from'], 'to_date' => $p['to']]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $activePreset === $key ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                {{ $p['label'] }}
            </a>
        @endforeach
        <button type="button" @click="showCustom = !showCustom"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $activePreset === 'custom' ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
            تاریخِ انتخابی
        </button>

        <form method="GET" x-show="showCustom" x-cloak class="flex items-end gap-2 flex-wrap border-r border-gray-300 dark:border-gray-600 pr-3 me-2">
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-1">از</label>
                <input type="text" name="from_date" value="{{ $fromJ }}" dir="ltr" placeholder="1405/04/01" readonly
                       class="jalali-datepicker w-28 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded cursor-pointer bg-white text-xs">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-gray-500 mb-1">تا</label>
                <input type="text" name="to_date" value="{{ $toJ }}" dir="ltr" placeholder="1405/04/31" readonly
                       class="jalali-datepicker w-28 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded cursor-pointer bg-white text-xs">
            </div>
            <button class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold">اعمال</button>
        </form>

        <div class="text-[11px] text-gray-500 me-auto">
            بازهٔ فعلی: <b dir="ltr">{{ $fromJ }}</b> تا <b dir="ltr">{{ $toJ }}</b>
        </div>
    </div>

    {{-- ─── تیله‌های بازه: کل = سفارش + لید ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-blue-100 dark:border-blue-900/40">
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($rng['total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">کل (سفارش + لید)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($rng['orders'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ثبتِ سفارش</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($rng['leads'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">لید</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($rng['cancelled'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">لغو/رد‌شده</p>
        </div>
    </div>

    {{-- ─── تیله‌های بازه (خط دوم): تکمیل، درآمد، نرخ تبدیل ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-emerald-100 dark:border-emerald-900/40">
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($rng['completed'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکمیل‌شده در بازه</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-emerald-100 dark:border-emerald-900/40 col-span-2 lg:col-span-1">
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ number_format($rng['revenue'] ?? 0) }} <span class="text-xs font-normal text-gray-400">تومان</span></p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">درآمدِ سفارش‌های تکمیل‌شده</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            @php($conv = ($rng['total'] ?? 0) > 0 ? round(($rng['orders'] ?? 0) / $rng['total'] * 100) : 0)
            <p class="text-3xl font-bold text-sky-600 dark:text-sky-400">{{ number_format($conv) }}٪</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">نرخِ تبدیل (سفارش از کل)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border {{ ($snap['unassigned'] ?? 0) > 0 ? 'border-amber-200 dark:border-amber-800' : 'border-gray-100 dark:border-gray-700' }}">
            <p class="text-3xl font-bold {{ ($snap['unassigned'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-800 dark:text-gray-100' }}">{{ number_format($snap['unassigned'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">بدونِ تکنسین (اکنون)</p>
        </div>
    </div>

    {{-- ─── تیله‌های لحظه‌ای (مستقل از بازه) ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($snap['open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">سفارش‌های باز (اکنون)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border {{ ($snap['delayed_open'] ?? 0) > 0 ? 'border-rose-200 dark:border-rose-800' : 'border-gray-100 dark:border-gray-700' }}">
            <p class="text-2xl font-bold {{ ($snap['delayed_open'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-gray-100' }}">{{ number_format($snap['delayed_open'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">معطل (+۳ روز)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($snap['techs_active'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">تکنسینِ فعال</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($snap['customers_total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">کل مشتری‌ها</p>
        </div>
    </div>

    {{-- ─── تیله‌های امروز/این‌ماه (مستقل از بازه) ─── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($snap['today_total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">کل امروز (سفارش + لید)</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($snap['month_total'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">کل این ماه</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($coverage['province_count'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">استان‌های تحت پوشش</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($coverage['city_count'] ?? 0) }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">شهرهای تحت پوشش</p>
        </div>
    </div>

    {{-- ─── پوشش سرویس‌دهی (استان/شهرهای واقعی — مشهد و…) ─── --}}
    @if(!empty($coverage['provinces']))
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">پوششِ سرویس‌دهی</h3>
                <p class="text-xs text-gray-400">استان‌ها و شهرهایی که تکنسینِ فعال دارند — خودکار از پروفایلِ تکنسین‌ها</p>
            </div>
            @if(Route::has('crm.technicians.service-coverage'))
            <a href="{{ route('crm.technicians.service-coverage') }}" class="text-xs text-blue-600 hover:underline">مدیریت پوشش ↗</a>
            @endif
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($coverage['provinces'] as $p)
                <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sm text-gray-800 dark:text-gray-100">{{ $p['name'] }}</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-[11px] font-bold">{{ number_format($p['techs']) }} تکنسین</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1.5">{{ implode('، ', $p['cities']) }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ─── نمودارها: روندِ بازه + امروز به تفکیک ساعت ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">روندِ سفارش‌ها در بازه</h3>
            <p class="text-xs text-gray-400 mb-2">کل (سفارش + لید) و لید — تاریخِ شمسی</p>
            <div id="chart-trend"></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">امروز به تفکیکِ ساعت</h3>
            <p class="text-xs text-gray-400 mb-2">کل (سفارش + لید) و سفارش ثبت‌شده در هر ساعت</p>
            <div id="chart-hourly"></div>
        </div>
    </div>

    {{-- ─── نمودارها: توزیعِ وضعیت + پرتکرارترین دستگاه‌ها ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">توزیعِ وضعیتِ سفارش‌ها</h3>
            <p class="text-xs text-gray-400 mb-2">سهمِ هر وضعیت در بازهٔ انتخاب‌شده</p>
            @if(!empty($statusBreakdown['data']))
                <div id="chart-status"></div>
            @else
                <p class="text-center text-gray-400 py-10 text-sm">داده‌ای در این بازه نیست.</p>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">پرتکرارترین دستگاه‌ها</h3>
            <p class="text-xs text-gray-400 mb-2">بیشترین سفارشِ تعمیر در بازهٔ انتخاب‌شده</p>
            @if(!empty($topDevices['data']))
                <div id="chart-devices"></div>
            @else
                <p class="text-center text-gray-400 py-10 text-sm">داده‌ای در این بازه نیست.</p>
            @endif
        </div>
    </div>

    {{-- ─── سفارشاتِ امروز به تفکیکِ منطقه (دایره‌ای) ─── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">سفارش‌های بازه به تفکیکِ استان</h3>
            <p class="text-xs text-gray-400 mb-2">سهمِ هر استان از سفارش‌های بازهٔ انتخاب‌شده (همهٔ استان‌های دارای سفارش)</p>
            @if(!empty($ordersByRegion['data']))
                <div id="chart-region"></div>
            @else
                <p class="text-center text-gray-400 py-10 text-sm">در این بازه سفارشی ثبت نشده.</p>
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
                        {{-- statusِ خام را می‌خوانیم تا castِ enum روی مقدارِ legacy خطا ندهد --}}
                        @php($st = \Modules\CRM\Enums\OrderStatus::tryFrom((string) $o->getRawOriginal('status')))
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
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $st?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">{{ $st?->label() ?? '—' }}</span>
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

    // ۱) روندِ بازه — کل (سفارش+لید) و لید
    const trend = @json($trend);
    new ApexCharts(document.querySelector('#chart-trend'), Object.assign({}, base, {
        chart: Object.assign({ type: 'bar', height: 260 }, base.chart),
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
        series: [
            { name: 'کل (سفارش+لید)', data: trend.total, color: '#3b82f6' },
            { name: 'لید', data: trend.leads, color: '#f59e0b' },
        ],
        xaxis: { categories: trend.labels, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '10px' }, formatter: (v) => Math.round(v) } },
    })).render();

    // ۲) امروز به تفکیک ساعت — کل و لید
    const hourly = @json($hourly);
    new ApexCharts(document.querySelector('#chart-hourly'), Object.assign({}, base, {
        chart: Object.assign({ type: 'area', height: 260 }, base.chart),
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
        series: [
            { name: 'کل (سفارش+لید)', data: hourly.total, color: '#3b82f6' },
            { name: 'سفارش ثبت‌شده', data: hourly.orders, color: '#10b981' },
        ],
        xaxis: { categories: hourly.labels, tickAmount: 12, labels: { style: { fontSize: '10px' } } },
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

    // ۵) سفارشاتِ امروز به تفکیکِ منطقه (دایره‌ای)
    const region = @json($ordersByRegion);
    if (region.data && region.data.length) {
        new ApexCharts(document.querySelector('#chart-region'), {
            chart: { type: 'pie', height: 300, fontFamily: 'inherit', foreColor: axisColor },
            series: region.data,
            labels: region.labels,
            colors: region.colors,
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '٪' },
        }).render();
    }
});
</script>
@endcanany
@endsection
