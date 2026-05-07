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
        use Morilog\Jalali\Jalalian as DashJalalian;
        $weekDayMap = [
            'Saturday'=>'شنبه','Sunday'=>'یکشنبه','Monday'=>'دوشنبه',
            'Tuesday'=>'سه‌شنبه','Wednesday'=>'چهارشنبه','Thursday'=>'پنج‌شنبه','Friday'=>'جمعه',
        ];
    @endphp
    @if(isset($calendarDays) && count($calendarDays))
    <div class="relative z-10 -mt-28 mx-3 bg-white rounded-[28px] shadow-lg p-4">
        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>

        <a href="{{ route('tech.calendar') }}" class="block">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2 text-gray-800">
                    <svg class="w-5 h-5 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm font-bold">تقویم کاری ۷ روز آینده</span>
                </div>
                <span class="text-xs text-brand-700 font-medium">مشاهده کامل ←</span>
            </div>

            <div class="grid grid-cols-3 gap-2">
                @foreach($calendarDays as $i => $cd)
                    @php
                        $j = DashJalalian::fromCarbon($cd['date']);
                        $dayName = $weekDayMap[$cd['date']->format('l')] ?? '';
                        $isToday = $cd['date']->isToday();
                        $hasOrders = $cd['count'] > 0;
                        $isLast = $i === count($calendarDays) - 1;
                    @endphp
                    <div class="rounded-xl py-2.5 px-2 transition
                                {{ $isLast ? 'col-span-3' : '' }}
                                {{ $isToday ? 'bg-brand-700 text-white shadow-md' : ($hasOrders ? 'bg-brand-50 border border-brand-200' : 'bg-gray-50 border border-gray-100') }}">

                        @if($isLast)
                            {{-- آخرین تایل (full-width): چینش افقی --}}
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold {{ $isToday ? 'text-white' : 'text-gray-700' }}">
                                        {{ $dayName }}
                                        @if($isToday)<span class="text-[10px] mr-1">(امروز)</span>@endif
                                    </div>
                                    <div class="text-[10px] mt-0.5 {{ $isToday ? 'text-white/80' : 'text-gray-400' }}" dir="ltr">
                                        {{ $j->format('Y/m/d') }}
                                    </div>
                                </div>
                                <div class="text-left flex-shrink-0">
                                    @if($hasOrders)
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full
                                                     {{ $isToday ? 'bg-white/25 text-white' : 'bg-brand-700 text-white' }}">
                                            {{ $cd['count'] }} سفارش
                                        </span>
                                        @if($cd['preview'])
                                            <div class="text-[10px] mt-1 {{ $isToday ? 'text-white/85' : 'text-gray-500' }}">
                                                {{ $cd['preview'] }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-[10px] {{ $isToday ? 'text-white/70' : 'text-gray-400' }} italic">
                                            سفارشی نیست
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @else
                            {{-- چیدمان ۳تایی: عمودی --}}
                            <div class="text-center">
                                <div class="text-xs font-bold {{ $isToday ? 'text-white' : 'text-gray-700' }} truncate">
                                    {{ $dayName }}
                                </div>
                                <div class="text-[10px] mt-0.5 {{ $isToday ? 'text-white/80' : 'text-gray-400' }}" dir="ltr">
                                    {{ $j->format('Y/m/d') }}
                                </div>
                                @if($isToday)
                                    <div class="text-[9px] mt-0.5 text-white/85 font-bold">امروز</div>
                                @endif

                                <div class="mt-1.5">
                                    @if($hasOrders)
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full
                                                     {{ $isToday ? 'bg-white/25 text-white' : 'bg-brand-700 text-white' }}">
                                            {{ $cd['count'] }} سفارش
                                        </span>
                                    @else
                                        <span class="text-[10px] {{ $isToday ? 'text-white/70' : 'text-gray-400' }} italic">
                                            بدون سفارش
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </a>
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
