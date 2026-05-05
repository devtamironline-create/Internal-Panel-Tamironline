@extends('crm::tech-panel.layout')

@section('title', 'فاکتورها')

@php
    $filterChips = [
        ['value' => null,         'label' => 'همه'],
        ['value' => 'draft',      'label' => 'پیش‌نویس'],
        ['value' => 'issued',     'label' => 'صادر شده'],
        ['value' => 'paid',       'label' => 'پرداخت شده'],
        ['value' => 'cancelled',  'label' => 'لغو شده'],
    ];
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
            <div class="text-white font-bold text-base">فاکتورها</div>
            <div class="w-10"></div>
        </div>

        {{-- Quick stats --}}
        <div class="px-5 mt-7 grid grid-cols-2 gap-2.5">
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-3 border border-white/20 text-center">
                <div class="text-white text-xl font-bold leading-none">{{ number_format($stats['count']) }}</div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">تعداد فاکتور</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-3 border border-white/20 text-center">
                <div class="text-white text-base font-bold leading-none">
                    {{ number_format($stats['total_sum']) }}
                </div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">مجموع مبلغ (تومان)</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-3 border border-white/20 text-center">
                <div class="text-white text-base font-bold leading-none text-emerald-200">
                    {{ number_format($stats['tech_share']) }}
                </div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">سهم تکنسین</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl py-3 px-3 border border-white/20 text-center">
                <div class="text-white text-base font-bold leading-none text-amber-200">
                    {{ number_format($stats['company_share']) }}
                </div>
                <div class="text-white/75 text-[11px] mt-1.5 font-medium">سهم شرکت</div>
            </div>
        </div>
    </div>

    {{-- ─────── Filter chips ─────── --}}
    <div class="relative z-10 -mt-16 mx-3 bg-white rounded-[28px] shadow-lg pt-4 pb-4 px-4">
        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>

        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
            @foreach($filterChips as $chip)
                @php
                    $isActive = (string) $chip['value'] === (string) $statusFilter;
                    $params = array_filter(['status' => $chip['value']], fn($v) => $v !== null && $v !== '');
                    $url = route('tech.invoices') . ($params ? '?' . http_build_query($params) : '');
                @endphp
                <a href="{{ $url }}"
                   class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium border transition
                          {{ $isActive ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-gray-600 border-gray-200' }}">
                    {{ $chip['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ─────── Invoices list ─────── --}}
    <div class="px-3 mt-3 space-y-3">
        @forelse($invoices as $invoice)
            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-bold text-gray-900 text-sm" dir="ltr">{{ $invoice->invoice_code }}</span>
                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ $invoice->statusBadge() }}">
                            {{ $invoice->statusLabel() }}
                        </span>
                    </div>
                    @if($invoice->issued_at)
                        <div class="text-[11px] text-gray-500 whitespace-nowrap" dir="ltr">
                            @jdate($invoice->issued_at)
                        </div>
                    @endif
                </div>

                @if($invoice->order)
                    <a href="{{ route('tech.orders.show', $invoice->order) }}"
                       class="mt-2 inline-flex items-center gap-1 text-xs text-brand-700 font-medium" dir="ltr">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        </svg>
                        {{ $invoice->order->order_code }}
                    </a>
                @endif

                @if($invoice->customer || $invoice->order?->customer_name)
                    <div class="mt-1 text-sm text-gray-800">
                        {{ $invoice->customer?->display_name ?? $invoice->order?->customer_name ?? '—' }}
                    </div>
                @endif

                <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-3 gap-2 text-center text-[11px]">
                    <div>
                        <div class="text-gray-400">مبلغ کل</div>
                        <div class="text-gray-900 font-bold mt-0.5">{{ number_format((int) $invoice->total_amount) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400">سهم تکنسین</div>
                        <div class="text-emerald-700 font-bold mt-0.5">{{ number_format((int) $invoice->tech_share) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400">سهم شرکت</div>
                        <div class="text-gray-700 font-bold mt-0.5">{{ number_format((int) $invoice->company_share) }}</div>
                    </div>
                </div>

                @if($invoice->commission_percent || $invoice->paid_at)
                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                        @if($invoice->commission_percent)
                            <span>کمیسیون: {{ $invoice->commission_percent }}%</span>
                        @else
                            <span></span>
                        @endif
                        @if($invoice->paid_at)
                            <span class="text-emerald-700" dir="ltr">پرداخت: @jdate($invoice->paid_at)</span>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">فاکتوری یافت نشد</div>
                <p class="text-xs text-gray-500 leading-7">
                    @if($statusFilter)
                        با فیلتر انتخاب‌شده فاکتوری پیدا نشد.
                    @else
                        هنوز فاکتوری برای شما صادر نشده است.
                    @endif
                </p>
            </div>
        @endforelse

        @if($invoices->hasPages())
            <div class="pt-2 pb-1">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.invoices'])
@endsection
