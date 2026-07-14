@extends('layouts.admin')

@section('page-title', 'داشبورد لینک‌سازی داخلی')

@section('main')
@php
    use Modules\Seo\Models\LinkSuggestion;
    $stats = $analysis['stats'];
    $islands = collect($analysis['islands'])->sortByDesc('avg');
    $typeLabels = ['device' => 'صفحه خدمات', 'combo' => 'خدمات - برند', 'brand' => 'برند', 'article' => 'مقاله'];
    $scoreColor = fn (int $s) => $s >= 70 ? 'text-emerald-600' : ($s >= 40 ? 'text-amber-600' : 'text-rose-600');
    $scoreDot = fn (int $s) => $s >= 70 ? 'bg-emerald-500' : ($s >= 40 ? 'bg-amber-500' : 'bg-rose-500');
@endphp
<div class="p-6 max-w-7xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">داشبورد لینک‌سازی داخلی</h1>
            <p class="text-sm text-gray-500 mt-1">مدیریت لینک‌های داخلی و رشد صفحات مهم سایت — تحلیل از محتوای دیتابیس ({{ $analysis['generated_at'] }})</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('seo.admin.internal-links.index', ['fresh' => 1]) }}"
               class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-50">🔄 بروزرسانی داده‌ها</a>
            <a href="{{ route('seo.admin.internal-links.suggestions') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700">پیشنهادها ({{ $suggestionStats['pending'] }})</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">{{ session('error') }}</div>@endif

    {{-- راهنمای سیستم --}}
    <details class="mb-5 rounded-2xl border border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/10 p-4">
        <summary class="text-sm font-bold text-blue-800 dark:text-blue-300 cursor-pointer">📘 راهنمای سیستم — هر شاخص یعنی چه و چطور کار کنم؟</summary>
        <div class="mt-3 grid md:grid-cols-2 gap-x-8 gap-y-2 text-[12px] text-gray-700 dark:text-gray-300 leading-7">
            <div><b>امتیاز لینک‌سازی (۰–۱۰۰):</b> کیفیتِ اتصالِ هر صفحه به بقیهٔ سایت. فرمول: لینکِ ورودی (تا ۴۰ امتیاز، هر لینک ۸)، داشتنِ حداقل یک لینکِ خروجی (۲۰)، نزدیکی به منو/عمقِ کم (تا ۲۰)، و اتصال به صفحهٔ مادرِ جزیره (۲۰). <span class="text-emerald-600 font-bold">۷۰+</span> خوب، <span class="text-amber-600 font-bold">۴۰–۶۹</span> متوسط، <span class="text-rose-600 font-bold">زیر ۴۰</span> ضعیف.</div>
            <div><b>صفحهٔ یتیم:</b> صفحه‌ای که <u>هیچ</u> صفحهٔ دیگری از داخلِ محتوا به آن لینک نداده — گوگل سخت پیدایش می‌کند. راه‌حل: از مقالات/صفحاتِ مرتبط به آن لینک بدهید (بخشِ پیشنهادها همین کار را می‌کند).</div>
            <div><b>عمق خزش:</b> حداقل تعدادِ کلیک از منوی اصلی تا آن صفحه. صفحاتِ مادرِ خدمات (در منو) عمقِ ۱ هستند. «۵+» یعنی از هیچ مسیری قابلِ رسیدن نیست. صفحاتِ مهم باید عمقِ ≤۳ داشته باشند.</div>
            <div><b>جزیره:</b> خوشهٔ موضوعیِ هر دستگاه — صفحهٔ مادر (مثلاً «تعمیر لباسشویی») + صفحاتِ برندِ همان دستگاه + مقالاتِ مرتبط. هدف: همهٔ اعضای جزیره به مادر لینک بدهند و مادر به برندها.</div>
            <div><b>لینک به ریدایرکت:</b> لینکِ داخلی که به URLِ ۳۰۱‌شده می‌رود؛ باید مستقیم به مقصدِ نهایی اصلاح شود (از بخشِ «اصلاح لینک‌های داخلی» → «ساختِ قاعده از ریدایرکت‌ها»).</div>
            <div><b>جریانِ کار:</b> ۱) «تولید پیشنهادها» را بزنید ۲) در صفحهٔ پیشنهادها هر مورد را تأیید/رد کنید (انکر قابلِ ویرایش است) ۳) «اعمال» بزنید — لینک فقط روی اولین تکرارِ انکر در متنِ آزاد اضافه می‌شود ۴) هر اعمال قابلِ «بازگردانی» است.</div>
        </div>
        <p class="mt-2 text-[11px] text-gray-500">📄 مستندِ کامل: <code dir="ltr">docs/SEO_INTERNAL_LINKING.md</code> — تحلیل هر ۱۵ دقیقه کش می‌شود؛ «بروزرسانی داده‌ها» آن را تازه می‌کند.</p>
    </details>

    {{-- کارت‌های آمار --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ $stats['avg_score'] }}<span class="text-sm text-gray-400">/100</span></div>
            <div class="text-[11px] text-gray-500 mt-1">میانگین امتیاز لینک‌سازی</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold {{ $stats['orphans'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $stats['orphans'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">صفحات یتیم (بدون لینک ورودی)</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold {{ $stats['low_link'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $stats['low_link'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">صفحات با لینک کم (≤۱)</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold {{ $stats['links_to_redirects'] > 0 ? 'text-violet-600' : 'text-emerald-600' }}">{{ $stats['links_to_redirects'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">لینک به ریدایرکت</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold {{ $stats['deep_pages'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $stats['deep_pages'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">صفحات عمیق (۴+ کلیک)</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div class="text-3xl font-bold text-gray-700 dark:text-gray-200">{{ $stats['total'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">کل صفحات تحلیل‌شده</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        {{-- وضعیت جزیره‌ها --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-1">وضعیت جزیره‌ها</div>
            <div class="text-[11px] text-gray-500 mb-2">میانگین امتیاز لینک‌سازی داخلی هر جزیره (دستگاه)</div>
            <div id="island-chart"></div>
            <table class="w-full text-[11px] mt-2">
                <thead class="text-gray-500"><tr class="text-center">
                    <th class="py-1 text-right" title="خوشهٔ موضوعیِ هر دستگاه">جزیره (دستگاه)</th>
                    <th title="مجموعِ صفحاتِ عضو: مادر + برندها + مقالاتِ مرتبط">تعداد صفحات</th>
                    <th title="مقالاتِ بلاگِ مرتبط با این دستگاه">مقالات</th>
                    <th title="اعضایی که به صفحهٔ مادرِ خدمات لینک داده‌اند">لینک به صفحهٔ مادر</th>
                    <th title="اعضای بدونِ هیچ لینکِ ورودی">صفحات یتیم</th>
                    <th title="میانگینِ امتیازِ لینک‌سازیِ اعضای جزیره (۰–۱۰۰)">میانگین امتیاز</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($islands as $slug => $i)
                    <tr class="text-center text-gray-700 dark:text-gray-200">
                        <td class="py-1.5 text-right font-bold">{{ $i['label'] }}</td>
                        <td>{{ $i['members'] }}</td>
                        <td>{{ $i['articles'] }}</td>
                        <td>{{ $i['linked_mother'] }}</td>
                        <td class="{{ $i['orphans'] > 0 ? 'text-rose-600 font-bold' : '' }}">{{ $i['orphans'] }}</td>
                        <td class="font-bold {{ $scoreColor($i['avg']) }}">{{ $i['avg'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- پیشنهادهای امروز --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-bold text-gray-800 dark:text-gray-100">💡 پیشنهادهای لینک‌سازی</div>
                <form method="POST" action="{{ route('seo.admin.internal-links.generate') }}">
                    @csrf
                    <button class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-700">تولید پیشنهادها</button>
                </form>
            </div>
            @forelse($topSuggestions as $s)
                <div class="flex items-start justify-between gap-2 py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-800 dark:text-gray-100 leading-6">
                            از <b>{{ $s->page_label }}</b> به <span dir="ltr" class="font-mono text-[10px]">{{ $s->target_path }}</span> لینک بده
                        </div>
                        <div class="text-[10px] text-gray-400 mt-0.5">{{ $s->reason }} — انکر: «{{ $s->anchor }}»</div>
                    </div>
                    <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold
                        {{ $s->priority === 'high' ? 'bg-rose-100 text-rose-700' : ($s->priority === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                        اولویت {{ LinkSuggestion::PRIORITY_LABELS[$s->priority] ?? $s->priority }}
                    </span>
                </div>
            @empty
                <p class="text-xs text-gray-400 py-4 text-center">پیشنهادی در انتظار نیست — «تولید پیشنهادها» را بزنید.</p>
            @endforelse
            <a href="{{ route('seo.admin.internal-links.suggestions') }}" class="block text-center text-xs text-blue-600 font-bold mt-3">مشاهده همه پیشنهادها ←</a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        {{-- صفحات نیازمند اقدام --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">⚠️ صفحات نیازمند اقدام فوری (کمترین امتیاز)</div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="text-gray-500"><tr class="text-center">
                        <th class="py-2 text-right">صفحه</th>
                        <th>نوع صفحه</th>
                        <th title="امتیازِ لینک‌سازیِ داخلی از ۱۰۰ — فرمول در «راهنمای سیستم»">امتیاز لینک‌سازی</th>
                        <th title="تعداد کلیک از منوی اصلی تا این صفحه">عمق خزش</th>
                        <th title="چند صفحهٔ دیگر به این صفحه لینک داده‌اند — ۰ یعنی یتیم">لینک ورودی</th>
                        <th title="این صفحه به چند صفحهٔ داخلیِ دیگر لینک داده">لینک خروجی</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($needsAction as $p)
                        <tr class="text-center text-gray-700 dark:text-gray-200">
                            <td class="py-2 text-right"><span dir="ltr" class="font-mono text-[10px] text-blue-600">{{ $p['path'] }}</span></td>
                            <td>{{ $typeLabels[$p['type']] ?? $p['type'] }}</td>
                            <td><span class="inline-flex items-center gap-1 font-bold {{ $scoreColor($p['score']) }}"><span class="w-1.5 h-1.5 rounded-full {{ $scoreDot($p['score']) }}"></span>{{ $p['score'] }}</span></td>
                            <td>{{ $p['depth'] }}</td>
                            <td class="{{ $p['in'] === 0 ? 'text-rose-600 font-bold' : '' }}">{{ $p['in'] }}</td>
                            <td>{{ $p['out'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- توزیع امتیاز بر اساس عمق --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-sm font-bold text-gray-800 dark:text-gray-100 mb-3">توزیع بر اساس عمق خزش</div>
            <table class="w-full text-xs">
                <thead class="text-gray-500"><tr class="text-center"><th class="py-1">عمق</th><th>تعداد صفحه</th><th>میانگین امتیاز</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($analysis['depth_dist'] as $depth => $d)
                    <tr class="text-center text-gray-700 dark:text-gray-200">
                        <td class="py-2 font-bold">{{ $depth >= 5 ? '۵+ (بدون مسیر)' : $depth }}</td>
                        <td>{{ $d['count'] }}</td>
                        <td class="font-bold {{ $scoreColor($d['avg']) }}">{{ $d['avg'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="text-[10px] text-gray-400 mt-3 leading-5">عمق از منوی اصلی (صفحات مادر خدمات = عمق ۱) روی لینک‌های محتوایی محاسبه شده است.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function init() {
        var el = document.getElementById('island-chart');
        if (!el || typeof ApexCharts === 'undefined') return;
        var dark = document.documentElement.classList.contains('dark');
        new ApexCharts(el, {
            chart: { type: 'bar', height: 220, fontFamily: 'inherit', toolbar: { show: false }, foreColor: dark ? '#9ca3af' : '#374151' },
            series: [{ name: 'میانگین امتیاز', data: @json($islands->pluck('avg')->values()) }],
            xaxis: { categories: @json($islands->pluck('label')->values()) },
            colors: ['#3b82f6'],
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, distributed: false } },
            dataLabels: { enabled: true },
            yaxis: { max: 100 },
            grid: { borderColor: dark ? 'rgba(148,163,184,.15)' : 'rgba(148,163,184,.25)' },
            legend: { show: false }
        }).render();
    }
    if (document.readyState !== 'loading') init(); else document.addEventListener('DOMContentLoaded', init);
})();
</script>
@endpush
@endsection
