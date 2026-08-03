@extends('layouts.admin')

@section('page-title', 'پوش نوتیفیکیشن')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🔔 پوش نوتیفیکیشن تکنسین‌ها</h1>
        <p class="text-xs text-gray-500 mt-1">اعلان وب از سرویس نجوا — وضعیت ارسال، پوشش مشترکین و خطاها.</p>
    </div>

    @include('crm::push._nav')

    {{-- بنر قرمز: خطای پیکربندی از همه‌چیز مهم‌تر است --}}
    @if($alarm)
        <div class="bg-rose-50 dark:bg-rose-900/20 border-2 border-rose-300 dark:border-rose-800 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <span class="text-2xl">🚨</span>
                <div class="text-sm">
                    <p class="font-bold text-rose-800 dark:text-rose-200">
                        ارسال پوش متوقف است — خطای {{ $alarm->error_code }}
                    </p>
                    <p class="text-rose-700 dark:text-rose-300 mt-1">
                        @switch($alarm->error_code)
                            @case(403) کلید API نجوا نامعتبر است. در «تنظیمات» بررسی کنید. @break
                            @case(416) IP سرور در وایت‌لیست پنل نجوا ثبت نشده. تا ثبت نشود هیچ اعلانی ارسال نمی‌شود. @break
                            @case(418) اعتبار حساب نجوا تمام شده. حساب را شارژ کنید. @break
                        @endswitch
                    </p>
                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-2">
                        آخرین بار: {{ \App\Support\JalaliDate::toJalali($alarm->created_at?->toDateString()) }}
                        ساعت {{ $alarm->created_at?->format('H:i') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    @unless($configured)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-800 rounded-xl p-4 text-sm text-amber-800 dark:text-amber-200">
            ⚠ سرویس نجوا هنوز تنظیم نشده — کلید API یا شناسهٔ سایت خالی است.
            <a href="{{ route('crm.push.settings') }}" class="underline font-bold">رفتن به تنظیمات</a>
        </div>
    @endunless

    {{-- ─── کارت‌های خلاصه ─── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['امروز', $totals['today'], 'sky'],
            ['۷ روز اخیر', $totals['week'], 'indigo'],
            ['۳۰ روز اخیر', $totals['month'], 'violet'],
            ['نرخ پذیرش نجوا', $totals['accept_rate'].'٪', 'emerald'],
        ] as [$label, $value, $tone])
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold text-{{ $tone }}-600 dark:text-{{ $tone }}-400 mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
            <p class="text-xs text-gray-500">هزینهٔ ۳۰ روز اخیر</p>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($totals['month_cost']) }}</p>
            <p class="text-[11px] text-gray-400 mt-1">جمع فیلد cost گزارش‌شده توسط نجوا</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
            <p class="text-xs text-gray-500">ناموفق در ۳۰ روز</p>
            <p class="text-xl font-bold {{ $totals['month_failed'] > 0 ? 'text-rose-600' : 'text-gray-900 dark:text-gray-100' }} mt-1">
                {{ number_format($totals['month_failed']) }}
            </p>
            <p class="text-[11px] text-gray-400 mt-1">
                <a href="{{ route('crm.push.logs', ['status' => 'failed']) }}" class="underline">دیدن جزئیات</a>
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
            <p class="text-xs text-gray-500">پوشش مشترکین</p>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                {{ $coverage['with_token'] }} / {{ $coverage['technicians'] }}
            </p>
            <p class="text-[11px] {{ $coverage['with_token'] < $coverage['technicians'] ? 'text-amber-600' : 'text-gray-400' }} mt-1">
                {{ $coverage['technicians'] - $coverage['with_token'] }} تکنسین اعلان را فعال نکرده‌اند —
                <a href="{{ route('crm.push.subscribers') }}" class="underline">پیگیری</a>
            </p>
        </div>
    </div>

    {{-- ─── نمودار ۳۰ روزه ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-4">ارسال روزانه (۳۰ روز اخیر)</h2>

        @php $peak = max(1, collect($daily)->max('total')); @endphp

        <div class="flex items-end gap-1 h-32" dir="ltr">
            @foreach($daily as $day)
                @php
                    $height = (int) round($day['total'] / $peak * 100);
                    $okHeight = $day['total'] > 0 ? (int) round($day['ok'] / $day['total'] * $height) : 0;
                @endphp
                <div class="flex-1 flex flex-col justify-end h-full group relative"
                     title="{{ \App\Support\JalaliDate::toJalali($day['day']) }} — {{ $day['total'] }} ارسال ({{ $day['ok'] }} موفق)">
                    <div class="w-full bg-rose-300 dark:bg-rose-800 rounded-t-sm" style="height: {{ max(0, $height - $okHeight) }}%"></div>
                    <div class="w-full bg-indigo-500 dark:bg-indigo-400 {{ $height === $okHeight ? 'rounded-t-sm' : '' }}" style="height: {{ $okHeight }}%"></div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-4 mt-3 text-[11px] text-gray-500">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-indigo-500 inline-block"></span> پذیرفته‌شده</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-rose-300 inline-block"></span> ناموفق</span>
        </div>

        <p class="text-[11px] text-gray-400 mt-3 leading-relaxed">
            «پذیرفته‌شده» یعنی نجوا درخواست را قبول کرد — نه اینکه اعلان روی مرورگر نمایش داده شد.
            نرخ تحویل و کلیک را باید از خودِ نجوا پرسید؛ در صفحهٔ گزارش‌ها دکمهٔ «پیگیری از نجوا» همین کار را می‌کند.
        </p>
    </div>
</div>
@endsection
