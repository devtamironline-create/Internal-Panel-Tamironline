@extends('layouts.admin')

@section('page-title', 'گوگل ادز')

@section('main')
@php use Morilog\Jalali\Jalalian; @endphp
<div class="space-y-4" x-data="{ editId: null }">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">گوگل ادز — دفترِ روزانه</h1>
            <p class="text-sm text-gray-500 mt-1">هر روز یک ردیف: مبلغِ واریزیِ ادز، لیر، قیمتِ لیر + شارژِ کیف‌پول و تعدادِ سفارشِ همان روز.</p>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 text-left">
            <div>جمعِ کلِ ادز: <b class="text-gray-800 dark:text-gray-100">{{ number_format($totals['ad_amount']) }}</b> تومان</div>
            <div>جمعِ لیر: <b class="text-gray-800 dark:text-gray-100">{{ number_format($totals['lira_count'], 2) }}</b></div>
        </div>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs leading-7 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-300">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- نمودارِ ماهانه — هر روز دو میله: هزینهٔ ادز و شارژِ کیف‌پول --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('crm.google-ads.index', ['month' => $chart['next_month']]) }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" title="ماه بعد">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100 min-w-[110px] text-center">{{ $chart['month_label'] }}</span>
                <a href="{{ route('crm.google-ads.index', ['month' => $chart['prev_month']]) }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" title="ماه قبل">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="text-gray-500 dark:text-gray-400">جمعِ شارژ کیف‌پول: <b class="text-green-600 dark:text-green-400" dir="ltr">{{ number_format($chart['total_income']) }}</b></span>
                <span class="text-gray-500 dark:text-gray-400">جمعِ ادز: <b class="text-red-600 dark:text-red-400" dir="ltr">{{ number_format($chart['total_ad']) }}</b></span>
            </div>
        </div>
        <div id="gads-chart" wire:ignore></div>
        <p class="text-[11px] text-gray-400 mt-1 text-center">به تفکیکِ روزِ ماه — سبز: شارژِ کیف‌پولِ تکنسین (همان ستونِ جدول)، قرمز: هزینهٔ ادز (تومان).</p>
    </div>

    @push('scripts')
    <script>
    // پارسِ عددِ ورودی با هر سبکِ جداکننده — آینهٔ parseFlexibleNumber سمتِ سرور،
    // تا باکسِ «مبلغ (خودکار)» دقیقاً همان عددی را نشان دهد که ذخیره خواهد شد.
    // (قبلاً Number('5,970') = NaN بود و همیشه «۰» نشان می‌داد.)
    window.gadsNum = function (v) {
        v = String(v ?? '')
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .replace(/٫/g, '.').replace(/[٬،]/g, ',')
            .replace(/[^0-9.,]/g, '');
        if (!v) return 0;
        var ld = v.lastIndexOf('.'), lc = v.lastIndexOf(',');
        if (ld !== -1 && lc !== -1) {
            var dec = ld > lc ? '.' : ',';
            v = v.split(dec === '.' ? ',' : '.').join('');
            if (dec === ',') v = v.replace(',', '.');
        } else if (lc !== -1) {
            if (/^\d{1,3}(,\d{3})+$/.test(v)) v = v.split(',').join('');
            else if (v.split(',').length === 2 && v.length - lc - 1 <= 2) v = v.replace(',', '.');
            else v = v.split(',').join('');
        } else if (ld !== -1) {
            if (/^\d{1,3}(\.\d{3})+$/.test(v)) v = v.split('.').join('');
            else if (v.split('.').length > 2) v = v.split('.').join('');
        }
        return Number(v) || 0;
    };
    (function () {
        function initGadsChart() {
            var el = document.getElementById('gads-chart');
            if (!el || typeof ApexCharts === 'undefined') return;
            var dark = document.documentElement.classList.contains('dark');
            var fmt = function (v) { return Number(v || 0).toLocaleString('fa-IR'); };
            new ApexCharts(el, {
                chart: { type: 'line', height: 320, fontFamily: 'inherit', toolbar: { show: false }, foreColor: dark ? '#9ca3af' : '#374151', zoom: { enabled: false } },
                theme: { mode: dark ? 'dark' : 'light' },
                series: [
                    { name: 'شارژ کیف‌پول', data: @json($chart['income_series']) },
                    { name: 'هزینهٔ ادز', data: @json($chart['ad_series']) }
                ],
                colors: ['#16a34a', '#dc2626'],
                stroke: { curve: 'smooth', width: 3 },
                markers: { size: 0, hover: { size: 4 } },
                dataLabels: { enabled: false },
                xaxis: { categories: @json($chart['labels']), tickPlacement: 'on', axisTicks: { show: false }, tooltip: { enabled: false } },
                {{-- یک محورِ مشترک برای هر دو سری — دو محورِ جدا مقایسهٔ درآمد/هزینه را گمراه‌کننده می‌کرد --}}
                yaxis: { labels: { formatter: fmt }, title: { text: 'تومان' } },
                legend: { position: 'top', horizontalAlign: 'right' },
                tooltip: { shared: true, intersect: false, y: { formatter: function (v) { return fmt(v) + ' تومان'; } } },
                grid: { borderColor: dark ? 'rgba(148,163,184,.15)' : 'rgba(148,163,184,.25)' },
                noData: { text: 'داده‌ای برای این ماه نیست' }
            }).render();
        }
        if (document.readyState !== 'loading') initGadsChart();
        else document.addEventListener('DOMContentLoaded', initGadsChart);
    })();
    </script>
    @endpush

    {{-- فرمِ افزودنِ ردیفِ روز (فقط با دسترسیِ manage) --}}
    @if($canManage)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">ثبتِ روزِ جدید <span class="text-[11px] font-normal text-gray-400">(معمولاً امروز برای دیروز)</span></div>
        <form method="POST" action="{{ route('crm.google-ads.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3"
              x-data="{ lira: @js((string) old('lira_count', '')), price: @js((string) old('lira_unit_price', '')),
                        get total(){ return Math.round(gadsNum(this.lira) * gadsNum(this.price)); },
                        fmt(n){ return Number(n||0).toLocaleString('fa-IR'); } }">
            @csrf
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">تاریخ (شمسی)</label>
                <input type="text" name="date" value="{{ old('date', $yesterday) }}" dir="ltr" placeholder="1405/04/22"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center">
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">تعداد لیر</label>
                <input type="text" name="lira_count" value="{{ old('lira_count') }}" x-model="lira" dir="ltr" inputmode="decimal" placeholder="مثلاً 120"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center">
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">قیمتِ هر لیر</label>
                <input type="text" name="lira_unit_price" value="{{ old('lira_unit_price') }}" x-model="price" dir="ltr" inputmode="numeric" placeholder="تومان"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center">
            </div>
            <div>
                <label class="block text-[11px] text-gray-500 mb-1">مبلغِ تومنی (خودکار)</label>
                <div dir="ltr" class="w-full px-3 py-2 border border-dashed border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-700 rounded text-sm text-center font-bold text-emerald-700 dark:text-emerald-300"
                     x-text="fmt(total)">۰</div>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-bold">ثبت ردیف</button>
            </div>
            <div class="md:col-span-5">
                <label class="block text-[11px] text-gray-500 mb-1">یادداشتِ این روز (اختیاری)</label>
                <textarea name="note" rows="2" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm leading-7">{{ old('note') }}</textarea>
            </div>
        </form>
    </div>
    @endif

    {{-- جدولِ روزها --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-600 dark:text-gray-300">
                    <tr class="text-center">
                        <th class="px-3 py-3 font-bold">تاریخ</th>
                        <th class="px-3 py-3 font-bold">مبلغِ ادز (تومان)</th>
                        <th class="px-3 py-3 font-bold">تعداد لیر</th>
                        <th class="px-3 py-3 font-bold">قیمتِ هر لیر</th>
                        <th class="px-3 py-3 font-bold bg-amber-50 dark:bg-amber-900/20">شارژِ کیف‌پولِ تکنسین</th>
                        <th class="px-3 py-3 font-bold bg-amber-50 dark:bg-amber-900/20">تعداد سفارش</th>
                        <th class="px-3 py-3 font-bold">یادداشت</th>
                        @if($canManage)<th class="px-3 py-3 font-bold">عملیات</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $row)
                        @php $e = $row['model']; @endphp
                        <tr class="text-center text-gray-800 dark:text-gray-100">
                            <td class="px-3 py-3 font-bold" dir="ltr">{{ Jalalian::fromCarbon($e->date)->format('Y/m/d') }}</td>
                            <td class="px-3 py-3" dir="ltr">{{ number_format((int) $e->ad_amount) }}</td>
                            <td class="px-3 py-3" dir="ltr">{{ number_format((float) $e->lira_count, 2) }}</td>
                            <td class="px-3 py-3" dir="ltr">{{ number_format((int) $e->lira_unit_price) }}</td>
                            <td class="px-3 py-3 bg-amber-50/50 dark:bg-amber-900/10" dir="ltr">{{ number_format($row['wallet_charge']) }}</td>
                            <td class="px-3 py-3 bg-amber-50/50 dark:bg-amber-900/10" dir="ltr">{{ number_format($row['order_count']) }}</td>
                            <td class="px-3 py-3 text-right text-xs text-gray-600 dark:text-gray-300 max-w-[220px]">{{ $e->note }}</td>
                            @if($canManage)
                            <td class="px-3 py-3 whitespace-nowrap">
                                <button type="button" @click="editId = (editId === {{ $e->id }} ? null : {{ $e->id }})" class="text-sky-600 hover:underline text-xs">ویرایش</button>
                                <form method="POST" action="{{ route('crm.google-ads.destroy', $e) }}" class="inline" onsubmit="return confirm('این ردیف حذف شود؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:underline text-xs mr-2">حذف</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @if($canManage)
                        <tr x-show="editId === {{ $e->id }}" x-cloak class="bg-sky-50/50 dark:bg-sky-900/10">
                            <td colspan="8" class="px-3 py-3">
                                <form method="POST" action="{{ route('crm.google-ads.update', $e) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end"
                                      x-data="{ lira: '{{ (float) $e->lira_count }}', price: '{{ (int) $e->lira_unit_price }}',
                                                get total(){ return Math.round(gadsNum(this.lira) * gadsNum(this.price)); },
                                                fmt(n){ return Number(n||0).toLocaleString('fa-IR'); } }">
                                    @csrf @method('PUT')
                                    <div><label class="block text-[11px] text-gray-500 mb-1">تاریخ</label>
                                        <input type="text" name="date" value="{{ Jalalian::fromCarbon($e->date)->format('Y/m/d') }}" dir="ltr" class="w-full px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center"></div>
                                    <div><label class="block text-[11px] text-gray-500 mb-1">تعداد لیر</label>
                                        <input type="text" name="lira_count" value="{{ (float) $e->lira_count }}" x-model="lira" dir="ltr" class="w-full px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center"></div>
                                    <div><label class="block text-[11px] text-gray-500 mb-1">قیمتِ لیر</label>
                                        <input type="text" name="lira_unit_price" value="{{ (int) $e->lira_unit_price }}" x-model="price" dir="ltr" class="w-full px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm text-center"></div>
                                    <div><label class="block text-[11px] text-gray-500 mb-1">مبلغِ ادز (خودکار)</label>
                                        <div dir="ltr" class="w-full px-2 py-1.5 border border-dashed border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-700 rounded text-sm text-center font-bold text-emerald-700 dark:text-emerald-300" x-text="fmt(total)"></div></div>
                                    <div class="md:col-span-2"><label class="block text-[11px] text-gray-500 mb-1">یادداشت</label>
                                        <input type="text" name="note" value="{{ $e->note }}" class="w-full px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm"></div>
                                    <div class="md:col-span-6">
                                        <button type="submit" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold">ذخیرهٔ تغییرات</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-3 py-10 text-center text-gray-400 text-sm">هنوز ردیفی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
