@extends('crm::tech-panel.layout')

@section('title', 'تقویم کاری')

@php
    use Modules\CRM\Enums\OrderStatus;
    use Morilog\Jalali\Jalalian;

    $weekDayMap = [
        'Saturday'  => 'شنبه',
        'Sunday'    => 'یکشنبه',
        'Monday'    => 'دوشنبه',
        'Tuesday'   => 'سه‌شنبه',
        'Wednesday' => 'چهارشنبه',
        'Thursday'  => 'پنج‌شنبه',
        'Friday'    => 'جمعه',
    ];
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">تقویم کاری</div>
            <div class="w-10"></div>
        </div>

        <div class="px-6 mt-5 text-right">
            <div class="text-white/75 text-xs">۷ روز آینده</div>
            <div class="text-white text-base font-bold mt-1" dir="ltr">
                {{ Jalalian::fromCarbon($days[0]['date'])->format('Y/m/d') }}
                —
                {{ Jalalian::fromCarbon($days[6]['date'])->format('Y/m/d') }}
            </div>
        </div>
    </div>

    {{-- ─────── Day cards ─────── --}}
    <div class="-mt-12 mx-3 space-y-3">
        @foreach($days as $day)
            @php
                $jalali = Jalalian::fromCarbon($day['date']);
                $weekDayPersian = $weekDayMap[$day['date']->format('l')] ?? '';
                $isToday = $day['date']->isToday();
                $hasOrders = $day['orders']->count() > 0;
            @endphp

            <div class="bg-white rounded-2xl shadow-sm overflow-hidden
                        {{ $isToday ? 'ring-2 ring-brand-500' : '' }}">

                {{-- Day header --}}
                <div class="px-4 py-3 flex items-center justify-between
                            {{ $isToday ? 'bg-brand-50' : 'bg-gray-50' }}">
                    <div>
                        <div class="text-sm font-bold {{ $isToday ? 'text-brand-700' : 'text-gray-800' }}">
                            {{ $weekDayPersian }}
                            @if($isToday)
                                <span class="text-[10px] font-bold text-brand-600 mr-1">(امروز)</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-gray-500 mt-0.5" dir="ltr">
                            {{ $jalali->format('Y/m/d') }}
                        </div>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold
                                 {{ $hasOrders ? 'bg-brand-700 text-white' : 'bg-gray-200 text-gray-500' }}">
                        {{ number_format($day['orders']->count()) }} سفارش
                    </span>
                </div>

                {{-- Orders list for this day --}}
                @if($hasOrders)
                    <div class="divide-y divide-gray-100">
                        @foreach($day['orders'] as $order)
                            <a href="{{ route('tech.orders.show', $order) }}"
                               class="block px-4 py-3 active:bg-gray-50">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-bold text-gray-900 text-xs" dir="ltr">{{ $order->order_code }}</span>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $order->status->badgeClass() }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </div>
                                    @if($order->visit_scheduled_at)
                                        <span class="text-[11px] text-gray-500" dir="ltr">
                                            {{ $order->visit_scheduled_at->format('H:i') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1.5 text-xs text-gray-700">
                                    {{ $order->customer_name ?: ($order->customer->display_name ?? '—') }}
                                    @if($order->customer_mobile)
                                        <span class="text-gray-400 mx-1">·</span>
                                        <span dir="ltr" class="text-gray-500">{{ $order->customer_mobile }}</span>
                                    @endif
                                </div>

                                @if($order->brand || $order->device)
                                    <div class="mt-1 text-[11px] text-gray-500">
                                        {{ $order->brand?->name }}
                                        @if($order->brand && $order->device) / @endif
                                        {{ $order->device?->name }}
                                    </div>
                                @endif

                                @if($order->problem_title)
                                    <div class="mt-1 text-[11px] text-gray-500 line-clamp-1">
                                        {{ $order->problem_title }}
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-5 text-center text-xs text-gray-400">
                        برای این روز هیچ سفارش هماهنگ‌شده‌ای ندارید.
                    </div>
                @endif
            </div>
        @endforeach

        @php $totalCount = collect($days)->sum(fn($d) => $d['orders']->count()); @endphp
        @if($totalCount === 0)
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center mt-3">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">هیچ هماهنگی‌ای در ۷ روز آینده ندارید</div>
                <p class="text-xs text-gray-500 leading-7">
                    وقتی سفارشی با تاریخ ویزیت در یک هفته آینده ثبت یا هماهنگ شود، در این صفحه دیده می‌شود.
                </p>
            </div>
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.dashboard'])
@endsection
