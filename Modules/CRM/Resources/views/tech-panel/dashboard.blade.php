@extends('crm::tech-panel.layout')

@section('title', 'داشبورد تکنسین')

@php
    $name = trim($technician->firstname_tech ?: $technician->first_name) ?: 'تکنسین';
    $statusLabel = $technician->status === 'active' ? 'فعال' : ($technician->status ?? '—');
    $isActive = $technician->status === 'active';

    // محاسبات مالی برای دو باکس آمار + هدر
    $balance = (int) $technician->true_balance;
    $balanceIsPositive = $balance > 0;
    $balanceIsNegative = $balance < 0;
    $balanceIsZero = $balance === 0;

    if ($balanceIsZero) {
        $financialLabel = 'تسویه';
        $financialColor = 'text-gray-500';
        $financialDot   = 'bg-gray-400';
    } elseif ($balanceIsPositive) {
        $financialLabel = 'بستانکار';
        $financialColor = 'text-emerald-600';
        $financialDot   = 'bg-emerald-500';
    } else {
        $financialLabel = 'بدهکار';
        $financialColor = 'text-rose-600';
        $financialDot   = 'bg-rose-500';
    }
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero header ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-36"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        {{-- Top icon row --}}
        <div class="flex items-center justify-between px-5 pt-5">
            <form action="{{ route('tech.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
                        aria-label="خروج">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>

            <div class="flex items-center gap-2">
                @if($supportPhone)
                    <a href="tel:{{ $supportPhone }}"
                       class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
                       aria-label="پشتیبانی">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </a>
                @endif
                <a href="{{ route('tech.profile') }}"
                   class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
                   aria-label="پروفایل">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- Greeting --}}
        <div class="px-6 pt-6 text-right">
            <div class="text-white/75 text-sm">سلام،</div>
            <div class="flex items-center justify-start gap-2 mt-1">
                <h1 class="text-white text-[26px] font-bold leading-tight">{{ $name }}</h1>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1
                             {{ $isActive ? 'bg-emerald-400/20 text-emerald-100 border border-emerald-300/40' : 'bg-rose-400/20 text-rose-100 border border-rose-300/40' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-300' : 'bg-rose-300' }}"></span>
                    {{ $statusLabel }}
                </span>
            </div>
            <p class="text-white/70 text-xs mt-2">امروز چه کارهایی برای انجام داری؟</p>
        </div>
    </div>

    {{-- ─────── Calendar shortcut card (separate sheet) ─────── --}}
    @php
        $weekDayMap = [
            'Saturday'=>'شنبه','Sunday'=>'یکشنبه','Monday'=>'دوشنبه',
            'Tuesday'=>'سه‌شنبه','Wednesday'=>'چهارشنبه','Thursday'=>'پنج‌شنبه','Friday'=>'جمعه',
        ];
        $latin = ['0','1','2','3','4','5','6','7','8','9'];
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $faNum = fn($s) => str_replace($latin, $persian, (string) $s);
    @endphp
    @if(isset($calendarDays) && count($calendarDays))
    <div class="relative z-10 -mt-28 mx-3 bg-white rounded-[28px] shadow-lg p-4"
         x-data="{ selectedDay: '{{ $calendarDays[0]['date']->toDateString() }}' }">
        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>

        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2 text-gray-800">
                <svg class="w-5 h-5 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-bold">تقویم کاری شما</span>
            </div>
            <a href="{{ route('tech.calendar') }}" class="text-xs text-brand-700 font-medium">مشاهده کامل ←</a>
        </div>

        {{-- ─── انتخاب روز (۷ روز پیشِ‌رو، اسکرول افقی) ─── --}}
        <div class="flex gap-1.5 overflow-x-auto no-scrollbar pb-1 -mx-1 px-1">
            @foreach($calendarDays as $cd)
                @php
                    $j = \Morilog\Jalali\Jalalian::fromCarbon($cd['date']);
                    $dayName = $weekDayMap[$cd['date']->format('l')] ?? '';
                    $dayKey = $cd['date']->toDateString();
                    $hasOrders = $cd['count'] > 0;
                @endphp
                <button type="button"
                        @click="selectedDay = '{{ $dayKey }}'"
                        :class="selectedDay === '{{ $dayKey }}'
                                ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200'
                                : '{{ $hasOrders ? 'border-brand-200 bg-brand-50/40' : 'border-gray-200 bg-gray-50' }}'"
                        class="flex-shrink-0 w-[72px] py-2 px-1 rounded-xl border-2 text-center transition">
                    <div class="text-[10px] text-gray-500">{{ $dayName }}</div>
                    <div class="text-base font-bold text-gray-900 my-0.5">{{ $faNum($j->format('d')) }}</div>
                    <div class="text-[10px] text-gray-500">{{ $j->format('F') }}</div>
                    @if($hasOrders)
                        <div class="mt-1 text-[10px] font-bold text-brand-700">
                            {{ $faNum($cd['count']) }} سفارش
                        </div>
                    @else
                        <div class="mt-1 text-[10px] text-gray-300">—</div>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ─── سفارش‌های روز انتخاب‌شده، تفکیک‌شده در ۴ بازه ─── --}}
        @foreach($calendarDays as $cd)
            @php $dayKey = $cd['date']->toDateString(); @endphp
            <div x-show="selectedDay === '{{ $dayKey }}'" x-cloak class="mt-4 space-y-3">
                @php $totalForDay = $cd['count']; @endphp

                @if($totalForDay === 0)
                    <div class="rounded-xl border-2 border-dashed border-gray-200 py-8 text-center">
                        <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <div class="text-xs text-gray-500">هیچ سفارشی برای این روز ثبت نشده است.</div>
                    </div>
                @else
                    @foreach($cd['slots'] as $slot)
                        <div class="rounded-2xl overflow-hidden border {{ $slot['orders']->count() ? 'border-emerald-200' : 'border-gray-100' }}">
                            {{-- هدر بازه --}}
                            <div class="flex items-center justify-between px-3 py-2 {{ $slot['orders']->count() ? 'bg-emerald-50' : 'bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 {{ $slot['orders']->count() ? 'text-emerald-700' : 'text-gray-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-xs font-bold {{ $slot['orders']->count() ? 'text-emerald-800' : 'text-gray-600' }}">
                                        {{ $slot['label'] }}
                                    </span>
                                </div>
                                @if($slot['orders']->count())
                                    <span class="text-[10px] font-bold text-emerald-700">
                                        {{ $faNum($slot['orders']->count()) }} مورد
                                    </span>
                                @else
                                    <span class="text-[10px] text-gray-400">—</span>
                                @endif
                            </div>

                            {{-- لیست سفارش‌های آن بازه --}}
                            @if($slot['orders']->count())
                                <div class="divide-y divide-gray-100">
                                    @foreach($slot['orders'] as $o)
                                        <a href="{{ route('tech.orders.show', $o) }}"
                                           class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-gray-50 transition">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-medium text-gray-900 truncate">
                                                    {{ $o->customer_name ?: ($o->customer?->display_name ?? '—') }}
                                                </div>
                                                <div class="text-[10px] text-gray-400 mt-0.5" dir="ltr">
                                                    {{ $o->order_code }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="text-[10px] font-bold" dir="ltr">
                                                    {{ $faNum($o->visit_scheduled_at->format('H:i')) }}
                                                </span>
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if($cd['unscheduled']->count())
                        <div class="rounded-2xl overflow-hidden border border-amber-200">
                            <div class="px-3 py-2 bg-amber-50 text-xs font-bold text-amber-800">
                                خارج از بازه‌های استاندارد
                            </div>
                            <div class="divide-y divide-amber-100">
                                @foreach($cd['unscheduled'] as $o)
                                    <a href="{{ route('tech.orders.show', $o) }}"
                                       class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-amber-50/40">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                {{ $o->customer_name ?: ($o->customer?->display_name ?? '—') }}
                                            </div>
                                            <div class="text-[10px] text-gray-400 mt-0.5" dir="ltr">
                                                {{ $o->order_code }}
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold text-amber-700" dir="ltr">
                                            {{ $faNum($o->visit_scheduled_at->format('H:i')) }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
    @endif

    {{-- ─────── White sheet for service cards ─────── --}}
    <div class="{{ isset($calendarDays) && count($calendarDays) ? 'mt-3' : 'relative z-10 -mt-28' }} mx-3 bg-white rounded-[28px] shadow-lg pt-4 pb-5 px-4">
        @if(! (isset($calendarDays) && count($calendarDays)))
            <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>
        @endif

        <div class="grid grid-cols-3 gap-3">
            {{-- Wallet --}}
            <a href="{{ route('tech.wallet') }}"
               class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center text-center hover:border-gray-200 transition">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm12 8h.01"/>
                    </svg>
                </div>
                <div class="text-[13px] font-bold text-gray-900">کیف‌پول</div>
                <div class="text-[10px] text-gray-400 mt-1">مانده و تراکنش</div>
            </a>

            {{-- Invoices --}}
            <a href="{{ route('tech.invoices') }}"
               class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center text-center hover:border-gray-200 transition">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="text-[13px] font-bold text-gray-900">فاکتورها</div>
                <div class="text-[10px] text-gray-400 mt-1">صورت‌حساب‌ها</div>
            </a>

            {{-- Orders (highlighted) --}}
            <a href="{{ route('tech.orders') }}"
               class="rounded-2xl p-4 flex flex-col items-center text-center text-white shadow-md"
               style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div class="text-[13px] font-bold">سفارش‌ها</div>
                <div class="text-[10px] text-white/80 mt-1">مدیریت کار</div>
            </a>
        </div>
    </div>

    {{-- ─────── Quick stats — اعتبار پنل + وضعیت مالی ─────── --}}
    <div class="px-4 mt-4 grid grid-cols-2 gap-3">
        {{-- باکس ۱: اعتبار پنل (true_balance با علامت +/-) --}}
        <a href="{{ route('tech.wallet') }}" class="bg-white rounded-2xl p-4 shadow-sm block active:bg-gray-50">
            <div class="text-xs text-gray-400">اعتبار پنل</div>
            <div class="text-xl font-bold mt-1 leading-tight {{ $balanceIsPositive ? 'text-emerald-600' : ($balanceIsNegative ? 'text-rose-600' : 'text-gray-700') }}">
                @if($balanceIsZero)
                    0
                @elseif($balanceIsPositive)
                    +{{ number_format($balance) }}
                @else
                    −{{ number_format(abs($balance)) }}
                @endif
                <span class="text-[10px] font-normal text-gray-400 mr-1">تومان</span>
            </div>
        </a>

        {{-- باکس ۲: وضعیت مالی (بستانکار/بدهکار/تسویه) --}}
        <a href="{{ route('tech.wallet') }}" class="bg-white rounded-2xl p-4 shadow-sm block active:bg-gray-50">
            <div class="text-xs text-gray-400">وضعیت مالی</div>
            <div class="text-sm font-bold mt-2 flex items-center gap-1.5 {{ $financialColor }}">
                <span class="w-2 h-2 rounded-full {{ $financialDot }}"></span>
                {{ $financialLabel }}
            </div>
            @if(! $balanceIsZero)
                <div class="text-[10px] text-gray-400 mt-1">
                    {{ $balanceIsPositive ? 'شرکت به شما بدهکار است' : 'شما به شرکت بدهکار هستید' }}
                </div>
            @else
                <div class="text-[10px] text-gray-400 mt-1">
                    حساب با شرکت تسویه است
                </div>
            @endif
        </a>
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.dashboard'])
@endsection
