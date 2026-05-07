@extends('crm::tech-panel.layout')

@section('title', 'سفارش‌ها')

@php
    use Modules\CRM\Enums\OrderStatus;

    $filterChips = [
        ['value' => null,                                'label' => 'همه'],
        ['value' => OrderStatus::New->value,             'label' => 'جدید'],
        ['value' => OrderStatus::Coordinated->value,     'label' => 'هماهنگ‌شده'],
        ['value' => OrderStatus::Open->value,            'label' => 'باز شده'],
        ['value' => OrderStatus::Completed->value,       'label' => 'انجام شده'],
        ['value' => OrderStatus::Suspended->value,       'label' => 'معلق'],
        ['value' => OrderStatus::Cancelled->value,       'label' => 'کنسل شده'],
        ['value' => OrderStatus::Returned->value,        'label' => 'برگشتی'],
    ];

    // helper برای ماسک کردن موبایل/تلفن — نمایش ۴ رقم اول + *** + ۴ رقم آخر.
    // برای سفارش‌های نهایی استفاده می‌شود تا اطلاعات تماس قابل‌استفاده نباشد.
    $maskContact = function (?string $number): ?string {
        if (! $number) return null;
        $clean = preg_replace('/\s+/', '', $number);
        $len = strlen($clean);
        if ($len <= 7) return str_repeat('*', $len);
        return substr($clean, 0, 4) . str_repeat('*', $len - 8) . substr($clean, -4);
    };
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero header ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-28"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">سفارش‌ها</div>
            <div class="w-10"></div>
        </div>

        {{-- Quick stats inside hero --}}
        <div class="px-5 mt-7 grid grid-cols-4 gap-2.5 text-center">
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-1 border border-white/20 shadow-sm">
                <div class="text-white text-xl font-bold leading-none">{{ number_format($stats['total']) }}</div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">کل</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-1 border border-white/20 shadow-sm">
                <div class="text-white text-xl font-bold leading-none">{{ number_format($stats['coordinated']) }}</div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">هماهنگ‌شده</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-1 border border-white/20 shadow-sm">
                <div class="text-white text-xl font-bold leading-none">{{ number_format($stats['open']) }}</div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">باز شده</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-1 border border-white/20 shadow-sm">
                <div class="text-white text-xl font-bold leading-none">{{ number_format($stats['completed']) }}</div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">انجام کار</div>
            </div>
        </div>
    </div>

    {{-- ─────── White sheet ─────── --}}
    <div class="relative z-10 -mt-16 mx-3 bg-white rounded-[28px] shadow-lg pt-4 pb-5 px-4">
        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>

        {{-- Search --}}
        <form method="GET" action="{{ route('tech.orders') }}" class="mb-3">
            @if($statusFilter)
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif
            <div class="relative">
                <span class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="search" name="q" value="{{ $search }}"
                       placeholder="جستجو در کد سفارش، نام یا موبایل مشتری..."
                       class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 pr-10 pl-3 text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-400 focus:outline-none">
            </div>
        </form>

        {{-- Filter chips --}}
        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
            @foreach($filterChips as $chip)
                @php
                    $isActive = (string) $chip['value'] === (string) $statusFilter;
                    $params = array_filter([
                        'q' => $search,
                        'status' => $chip['value'],
                    ], fn($v) => $v !== null && $v !== '');
                    $url = route('tech.orders') . ($params ? '?' . http_build_query($params) : '');
                @endphp
                <a href="{{ $url }}"
                   class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium border transition
                          {{ $isActive ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-gray-600 border-gray-200' }}">
                    {{ $chip['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ─────── Orders list ─────── --}}
    <div class="px-3 mt-4 space-y-3">
        @forelse($orders as $order)
            <a href="{{ route('tech.orders.show', $order) }}"
               class="block bg-white rounded-2xl p-4 shadow-sm active:bg-gray-50 transition">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-bold text-gray-900 text-sm" dir="ltr">{{ $order->order_code }}</span>
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ $order->status->badgeClass() }}">
                            {{ $order->status->label() }}
                        </span>
                    </div>
                    @if($order->visit_scheduled_at)
                        <div class="text-[11px] text-gray-500 whitespace-nowrap" dir="ltr">
                            @jdatetime($order->visit_scheduled_at)
                        </div>
                    @endif
                </div>

                <div class="mt-2.5 text-sm text-gray-800">
                    {{ $order->customer_name ?: ($order->customer->display_name ?? '—') }}
                    @if($order->customer_mobile)
                        <span class="text-gray-400 mx-1">·</span>
                        @if($order->status->isFinal())
                            <span dir="ltr" class="text-gray-400">{{ $maskContact($order->customer_mobile) }}</span>
                        @else
                            <span dir="ltr" class="text-gray-600">{{ $order->customer_mobile }}</span>
                        @endif
                    @endif
                </div>

                @if($order->brand || $order->device)
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $order->brand?->name }}
                        @if($order->brand && $order->device) / @endif
                        {{ $order->device?->name }}
                    </div>
                @endif

                @if($order->problem_title)
                    <div class="mt-2 text-xs text-gray-600 line-clamp-2">
                        {{ $order->problem_title }}
                    </div>
                @endif
            </a>
        @empty
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">سفارشی یافت نشد</div>
                <p class="text-xs text-gray-500 leading-7">
                    @if($search || $statusFilter)
                        با فیلترهای انتخاب‌شده سفارشی پیدا نشد.
                    @else
                        هنوز سفارشی به شما تخصیص داده نشده است.
                    @endif
                </p>
            </div>
        @endforelse

        @if($orders->hasPages())
            <div class="pt-2 pb-1">
                @include('crm::tech-panel._partials.pagination', ['paginator' => $orders])
            </div>
        @endif
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.orders'])
@endsection
