{{-- گزارش مدیریتی --}}
@php use Morilog\Jalali\Jalalian; @endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">وضعیت کلی</h2>
        <div class="flex items-center gap-4">
            <div class="text-4xl font-black {{ $health['score'] >= 70 ? 'text-emerald-600' : ($health['score'] >= 45 ? 'text-amber-600' : 'text-red-600') }}">{{ $health['score'] }}</div>
            <div class="text-xs text-gray-500 leading-6">
                امتیاز سلامت داخلی از ۱۰۰ · روز {{ $progress['day'] }} از ۹۰ ({{ $progress['phase_info']['title'] }})<br>
                انجام به‌موقع کارها: <b dir="ltr">{{ $progress['completion_percent'] }}٪</b> — {{ $progress['delayed'] }} کار عقب‌افتاده
            </div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">تأثیر روی لید و فروش</h2>
        @if($snapshot && ($snapshot->organic_leads !== null || $snapshot->phone_call_leads !== null))
            <div class="grid grid-cols-3 gap-2 text-center">
                <div><div class="text-xl font-black text-gray-800 dark:text-gray-100">{{ number_format($snapshot->organic_leads ?? 0) }}</div><div class="text-[10px] text-gray-500">لید ارگانیک</div></div>
                <div><div class="text-xl font-black text-gray-800 dark:text-gray-100">{{ number_format($snapshot->phone_call_leads ?? 0) }}</div><div class="text-[10px] text-gray-500">تماس تلفنی</div></div>
                <div><div class="text-xl font-black text-gray-800 dark:text-gray-100">{{ number_format($snapshot->app_login_leads ?? 0) }}</div><div class="text-[10px] text-gray-500">ورود اپ</div></div>
            </div>
        @else
            <p class="text-xs text-gray-500 leading-6">دادهٔ لید هنوز ثبت نشده است. با اتصال GA4 در فاز بعد، این بخش خودکار پر می‌شود. هدف استراتژیک فعلی: انتقال اعتبار مقالات به صفحات خدمات و افزایش تماس/ورود اپ.</p>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">دستاوردها / کارهای انجام‌شده</h2>
        @forelse($done as $item)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">
                ✅ {{ $item->title }} <span class="text-gray-400">({{ $item->completed_at ? Jalalian::fromCarbon($item->completed_at)->format('Y/m/d') : '' }})</span>
            </div>
        @empty
            <p class="text-xs text-gray-400">در این بازه کاری تکمیل نشده است.</p>
        @endforelse
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">کارهای عقب‌افتاده</h2>
        @forelse($delayed as $item)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">
                ⏰ {{ $item->title }} <span class="text-red-500">(موعد: {{ $item->planned_date ? Jalalian::fromCarbon($item->planned_date->copy())->format('Y/m/d') : '' }})</span>
            </div>
        @empty
            <p class="text-xs text-emerald-600">هیچ کار عقب‌افتاده‌ای نیست ✅</p>
        @endforelse
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">فرصت‌های مهم</h2>
        @forelse($top_opportunities as $opp)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">
                📈 {{ $opp->title }} <b class="text-brand-600" dir="ltr">{{ number_format($opp->opportunity_score, 1) }}</b>
            </div>
        @empty
            <p class="text-xs text-gray-400">فرصت بازی ثبت نشده است.</p>
        @endforelse
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">ریسک‌ها</h2>
        @forelse($risks as $issue)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">
                ⚠️ {{ $issue->title }} <span class="text-gray-400">({{ $issue->severityLabel() }} · {{ $issue->statusLabel() }})</span>
            </div>
        @empty
            <p class="text-xs text-emerald-600">ریسک بحرانی بازی ثبت نشده است.</p>
        @endforelse
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">برنامهٔ هفتهٔ آینده</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        @forelse($next_week as $item)
            <div class="text-xs py-1.5 text-gray-600 dark:text-gray-300 flex items-center gap-2">
                <span class="text-gray-400 font-mono shrink-0" dir="ltr">{{ $item->planned_date ? Jalalian::fromCarbon($item->planned_date->copy())->format('m/d') : '' }}</span>
                <span class="truncate">{{ $item->title }}</span>
                @include('seo::arm.partials._priority', ['priority' => $item->priority])
            </div>
        @empty
            <p class="text-xs text-gray-400">برای هفتهٔ آینده کاری برنامه‌ریزی نشده است.</p>
        @endforelse
    </div>
</div>
