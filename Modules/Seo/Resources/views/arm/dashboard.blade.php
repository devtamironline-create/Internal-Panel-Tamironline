@extends('layouts.admin')

@section('page-title', 'بازوی سئو — مرکز فرمان')

@section('main')
<div class="p-6 space-y-6" dir="rtl">

    {{-- سربرگ --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">⚡ مرکز فرمان سئو</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $project->name }} — {{ $project->domain }}
                @if($snapshot)
                    · بازهٔ گزارش: {{ \Morilog\Jalali\Jalalian::fromCarbon($snapshot->period_start)->format('Y/m/d') }}
                    تا {{ \Morilog\Jalali\Jalalian::fromCarbon($snapshot->period_end)->format('Y/m/d') }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('seo.arm.reports.show', 'executive') }}" class="px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm">گزارش مدیریتی</a>
            <a href="{{ route('seo.arm.roadmap.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">برنامه ۹۰ روزه</a>
        </div>
    </div>

    {{-- کارت‌های خلاصه --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($cards as $card)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 relative group">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $card['title'] }}</span>
                    <span class="text-gray-300 dark:text-gray-600 cursor-help" tabindex="0" role="tooltip" aria-label="{{ $card['tooltip'] }}" title="{{ $card['tooltip'] }}">ⓘ</span>
                </div>
                <div class="mt-2 text-xl font-black text-gray-800 dark:text-gray-100" dir="ltr">
                    @if($card['value'] === null)
                        <span class="text-gray-300 dark:text-gray-600 text-base">ثبت نشده</span>
                    @elseif($card['format'] === 'percent')
                        {{ number_format($card['value'] * 100, 2) }}٪
                    @elseif($card['format'] === 'percent100')
                        {{ number_format($card['value']) }}٪
                    @elseif($card['format'] === 'position')
                        {{ number_format($card['value'], 1) }}
                    @else
                        {{ number_format($card['value']) }}
                    @endif
                </div>
                <div class="mt-1 flex items-center gap-2 text-[11px]">
                    @if($card['delta'] !== null)
                        <span @class([
                            'font-bold',
                            'text-emerald-600' => $card['direction'] === 'good',
                            'text-red-600' => $card['direction'] === 'bad',
                            'text-gray-400' => $card['direction'] === 'flat',
                        ]) dir="ltr">{{ $card['delta'] > 0 ? '+' : '' }}{{ $card['delta'] }}٪</span>
                        <span class="text-gray-400">نسبت به بازهٔ قبل</span>
                    @endif
                </div>
                <p class="mt-1 text-[11px] leading-5 text-gray-500 dark:text-gray-400">{{ $card['interpretation'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- امتیاز سلامت --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-gray-800 dark:text-gray-100">امتیاز سلامت سئو</h2>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300" title="این عدد امتیاز عملیاتی داخلی تعمیرآنلاین است و متریک گوگل نیست.">امتیاز داخلی</span>
            </div>
            @php($score = $health['score'])
            <div class="flex items-center gap-5 mt-4">
                <div class="relative w-28 h-28 shrink-0">
                    <svg viewBox="0 0 120 120" class="w-28 h-28 -rotate-90">
                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="12" class="stroke-gray-100 dark:stroke-gray-700"/>
                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="{{ round(3.2673 * $score, 1) }} 326.73"
                                @class(['stroke-emerald-500' => $score >= 70, 'stroke-amber-500' => $score >= 45 && $score < 70, 'stroke-red-500' => $score < 45])/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-black text-gray-800 dark:text-gray-100">{{ $score }}</span>
                        <span class="text-[10px] text-gray-400">از ۱۰۰</span>
                    </div>
                </div>
                <div class="flex-1 space-y-1.5">
                    @foreach($health['components'] as $c)
                        <div>
                            <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $c['label'] }} <span class="text-gray-300 dark:text-gray-600">({{ $c['weight'] }}٪)</span></span>
                                <span dir="ltr">{{ round($c['score']) }}</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div @class(['h-full rounded-full', 'bg-emerald-500' => $c['score'] >= 70, 'bg-amber-500' => $c['score'] >= 45 && $c['score'] < 70, 'bg-red-500' => $c['score'] < 45]) style="width: {{ min(100, $c['score']) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- پیشرفت ۹۰ روزه --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-gray-800 dark:text-gray-100">برنامهٔ ۹۰ روزه</h2>
                <a href="{{ route('seo.arm.roadmap.index') }}" class="text-xs text-brand-600 hover:underline">جزئیات ←</a>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ $progress['day'] }}</div>
                    <div class="text-[10px] text-gray-500">روز جاری</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ $progress['week'] }}</div>
                    <div class="text-[10px] text-gray-500">هفته</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ $progress['phase'] }}</div>
                    <div class="text-[10px] text-gray-500">فاز</div>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $progress['phase_info']['title'] }} <span class="text-gray-400">(روزهای {{ $progress['phase_info']['days'] }})</span></p>
            <div class="mt-3">
                <div class="flex justify-between text-[11px] text-gray-500 mb-1">
                    <span>انجام به‌موقعِ کارهای موعدرسیده</span><span dir="ltr">{{ $progress['completion_percent'] }}٪</span>
                </div>
                <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-brand-600 rounded-full" style="width: {{ $progress['completion_percent'] }}%"></div>
                </div>
                <div class="flex justify-between mt-2 text-[11px]">
                    <span class="text-emerald-600">✅ {{ $progress['completed'] }} انجام‌شده</span>
                    <span @class(['text-red-600 font-bold' => $progress['delayed'] > 0, 'text-gray-400' => $progress['delayed'] === 0])>⏰ {{ $progress['delayed'] }} عقب‌افتاده</span>
                    <span class="text-gray-400">{{ $progress['total'] }} کل</span>
                </div>
            </div>
            @if($progress['milestones']->isNotEmpty())
                <div class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-2 max-h-32 overflow-y-auto space-y-1">
                    @foreach($progress['milestones'] as $m)
                        <div class="flex items-center gap-2 text-[11px]">
                            <span @class(['w-2 h-2 rounded-full shrink-0', 'bg-emerald-500' => $m->status === 'completed', 'bg-gray-300 dark:bg-gray-600' => $m->status !== 'completed'])></span>
                            <span class="text-gray-500 dark:text-gray-400 truncate">هفته {{ $m->week_number }} — {{ $m->title }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- مشکلات فنی + خط تولید محتوا --}}
        <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-gray-800 dark:text-gray-100">مشکلات فنی باز</h2>
                    <a href="{{ route('seo.arm.issues.index') }}" class="text-xs text-brand-600 hover:underline">همه ←</a>
                </div>
                <div class="grid grid-cols-4 gap-2 mt-3 text-center">
                    @foreach(['critical' => ['بحرانی','text-red-600'], 'high' => ['بالا','text-amber-600'], 'medium' => ['متوسط','text-blue-600'], 'low' => ['پایین','text-gray-500']] as $sev => [$label, $cls])
                        <a href="{{ route('seo.arm.issues.index', ['severity' => $sev]) }}" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2 hover:ring-1 ring-brand-400">
                            <div class="text-xl font-black {{ $cls }}">{{ $issues_summary[$sev] }}</div>
                            <div class="text-[10px] text-gray-500">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-gray-800 dark:text-gray-100">خط تولید محتوا</h2>
                    <a href="{{ route('seo.arm.calendar.index') }}" class="text-xs text-brand-600 hover:underline">تقویم ←</a>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                    @foreach(['planned' => 'برنامه‌ریزی', 'writing' => 'در نگارش', 'review' => 'در بازبینی', 'ready' => 'آماده/زمان‌بندی', 'published' => 'منتشرشده', 'delayed' => 'عقب‌افتاده'] as $key => $label)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                            <div @class(['text-lg font-black', 'text-red-600' => $key === 'delayed' && $content_pipeline[$key] > 0, 'text-emerald-600' => $key === 'published', 'text-gray-800 dark:text-gray-100' => ! in_array($key, ['delayed', 'published'])])>{{ $content_pipeline[$key] }}</div>
                            <div class="text-[10px] text-gray-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- مرکز اقدام امروز --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100">🎯 امروز چه کارهایی باید انجام شود؟</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4">
            @foreach([
                ['title' => '⏰ کارهای عقب‌افتاده', 'items' => $action_center['overdue_tasks'], 'route' => route('seo.arm.roadmap.index', ['status' => 'planned']), 'accent' => 'red'],
                ['title' => '📌 کارهای امروز', 'items' => $action_center['today_tasks'], 'route' => route('seo.arm.roadmap.index'), 'accent' => 'blue'],
                ['title' => '📝 محتوای منتظر بازبینی', 'items' => $action_center['awaiting_review'], 'route' => route('seo.arm.calendar.index', ['view' => 'list']), 'accent' => 'amber'],
                ['title' => '🚨 مشکلات بحرانی', 'items' => $action_center['critical_issues'], 'route' => route('seo.arm.issues.index', ['severity' => 'critical']), 'accent' => 'red'],
                ['title' => '♻️ صفحات نیازمند به‌روزرسانی', 'items' => $action_center['refresh_pages'], 'route' => route('seo.arm.pages.index', ['workflow_status' => 'refresh']), 'accent' => 'amber'],
                ['title' => '🔗 پیگیری لینک‌سازی', 'items' => $action_center['link_followups'], 'route' => route('seo.arm.links.index'), 'accent' => 'blue'],
            ] as $panel)
                <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $panel['title'] }}</span>
                        <a href="{{ $panel['route'] }}" class="text-[11px] text-brand-600 hover:underline">همه</a>
                    </div>
                    @forelse($panel['items'] as $it)
                        <div class="flex items-center justify-between gap-2 py-1 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                            <span class="text-xs text-gray-600 dark:text-gray-300 truncate">{{ $it->title }}</span>
                            @if(isset($it->priority))
                                @include('seo::arm.partials._priority', ['priority' => $it->priority])
                            @endif
                        </div>
                    @empty
                        <p class="text-[11px] text-gray-400 py-2">موردی نیست — عالی است ✅</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>

    {{-- رادار فرصت (ربع impact/effort) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-gray-800 dark:text-gray-100">📡 رادار فرصت‌ها</h2>
            <a href="{{ route('seo.arm.opportunities.index') }}" class="text-xs text-brand-600 hover:underline">همهٔ فرصت‌ها ←</a>
        </div>
        <p class="text-[11px] text-gray-400 mt-1">محور افقی: اثر (راست = بیشتر) · محور عمودی: تلاش (بالا = کمتر). حباب بزرگ‌تر = امتیاز فرصت بالاتر. ابتدا سراغ ربعِ «برد سریع» بروید.</p>
        <div class="grid grid-cols-2 gap-2 mt-4">
            @foreach([
                'quick_win' => ['🏆 برد سریع — اثر بالا، تلاش کم', 'border-emerald-300 bg-emerald-50/60 dark:bg-emerald-900/10'],
                'strategic' => ['🧗 استراتژیک — اثر بالا، تلاش زیاد', 'border-blue-300 bg-blue-50/60 dark:bg-blue-900/10'],
                'filler' => ['🧩 پرکننده — اثر کم، تلاش کم', 'border-gray-200 bg-gray-50/60 dark:bg-gray-700/20'],
                'avoid' => ['⛔ پرهیز — اثر کم، تلاش زیاد', 'border-red-200 bg-red-50/40 dark:bg-red-900/10'],
            ] as $quad => [$label, $cls])
                <div class="border {{ $cls }} rounded-lg p-3 min-h-[110px]">
                    <div class="text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-2">{{ $label }} <span class="text-gray-400">({{ count($radar[$quad]) }})</span></div>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse(array_slice($radar[$quad], 0, 6) as $opp)
                            <a href="{{ route('seo.arm.opportunities.index', ['quadrant' => $quad]) }}"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-[11px] text-gray-600 dark:text-gray-300 hover:border-brand-400"
                               title="امتیاز فرصت: {{ number_format($opp->opportunity_score, 1) }} — {{ $opp->recommended_action }}">
                                <span class="w-2 h-2 rounded-full bg-brand-500" style="transform: scale({{ min(2.2, 0.8 + $opp->opportunity_score / 40) }})"></span>
                                <span class="max-w-[160px] truncate">{{ $opp->title }}</span>
                            </a>
                        @empty
                            <span class="text-[11px] text-gray-400">فرصتی در این ربع نیست.</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- صفحات اولویت‌دار --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-gray-800 dark:text-gray-100">💰 صفحات اولویت‌دار (P0/P1)</h2>
            <a href="{{ route('seo.arm.pages.index') }}" class="text-xs text-brand-600 hover:underline">همهٔ صفحات ←</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">صفحه</th>
                        <th class="p-3">نوع</th>
                        <th class="p-3">کلمهٔ اصلی</th>
                        <th class="p-3">رتبه</th>
                        <th class="p-3">ایمپرشن</th>
                        <th class="p-3">CTR</th>
                        <th class="p-3">تبدیل</th>
                        <th class="p-3">اولویت</th>
                        <th class="p-3">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($priority_pages as $page)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="p-3">
                                <div class="font-medium text-gray-800 dark:text-gray-100 max-w-[240px] truncate">{{ $page->title }}</div>
                                <div class="text-[11px] text-gray-400 truncate" dir="ltr">{{ $page->path ?: ($page->needsUrlMapping() ? '⚠ نیازمند نگاشت URL' : $page->url) }}</div>
                            </td>
                            <td class="p-3 text-center text-xs text-gray-500">{{ $page->typeLabel() }}</td>
                            <td class="p-3 text-center text-xs text-gray-600 dark:text-gray-300">{{ $page->primary_keyword ?: '—' }}</td>
                            <td class="p-3 text-center font-bold" dir="ltr">{{ $page->current_position !== null ? number_format($page->current_position, 1) : '—' }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->impressions) }}</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->ctr * 100, 2) }}٪</td>
                            <td class="p-3 text-center" dir="ltr">{{ number_format($page->conversions) }}</td>
                            <td class="p-3 text-center">@include('seo::arm.partials._priority', ['priority' => $page->priority])</td>
                            <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $page->workflow_status, 'label' => $page->statusLabel()])</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">@include('seo::arm.partials._empty', ['message' => 'هنوز صفحه‌ای ثبت نشده است.', 'hint' => 'seeder فاز ۱ را اجرا کنید.'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- فعالیت‌های اخیر --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">🕘 فعالیت‌های اخیر سئو</h2>
        @forelse($activity as $log)
            <div class="flex items-start gap-3 py-2 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                <span class="w-2 h-2 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                <div class="min-w-0">
                    <p class="text-xs text-gray-600 dark:text-gray-300">{{ $log->description ?: $log->action }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i') }} · {{ $log->subject }}</p>
                </div>
            </div>
        @empty
            @include('seo::arm.partials._empty', ['message' => 'هنوز فعالیتی ثبت نشده است.', 'icon' => '🕘'])
        @endforelse
    </div>
</div>
@endsection
