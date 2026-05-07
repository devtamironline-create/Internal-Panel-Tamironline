@extends('crm::tech-panel.layout')

@section('title', 'کیف‌پول')

@php
    use Modules\CRM\Enums\WalletTxType;

    $balance = (int) $technician->true_balance;
    $isPositive = $balance >= 0;
    $balanceLabel = $isPositive ? 'شرکت به شما بدهکار است' : 'شما به شرکت بدهکار هستید';

    $filterChips = [
        ['value' => null,                            'label' => 'همه'],
        ['value' => WalletTxType::Commission->value,  'label' => 'کمیسیون'],
        ['value' => WalletTxType::Reward->value,      'label' => 'پاداش'],
        ['value' => WalletTxType::Penalty->value,     'label' => 'جریمه'],
        ['value' => WalletTxType::WalletCharge->value,'label' => 'شارژ'],
    ];
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-32"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">کیف‌پول</div>
            <div class="w-10"></div>
        </div>
    </div>

    {{-- ─────── Balance card (overlap) ─────── --}}
    <div class="relative z-10 -mt-24 mx-3 bg-white rounded-[28px] shadow-lg p-5 text-center">
        <div class="text-[11px] text-gray-400 mb-1">مانده فعلی</div>
        <div class="text-3xl font-bold {{ $isPositive ? 'text-emerald-700' : 'text-rose-600' }}">
            {{ $isPositive ? '' : '−' }}{{ number_format(abs($balance)) }}
            <span class="text-sm font-normal text-gray-400 mr-1">تومان</span>
        </div>
        <div class="text-xs text-gray-500 mt-2">{{ $balanceLabel }}</div>
    </div>

    {{-- ─────── Stats grid ─────── --}}
    <div class="px-3 mt-4 grid grid-cols-2 gap-3">
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="text-[11px] text-gray-400">مجموع کمیسیون</div>
            <div class="text-base font-bold text-emerald-700 mt-1">
                +{{ number_format($stats['commission_sum']) }}
                <span class="text-[10px] font-normal text-gray-400">تومان</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="text-[11px] text-gray-400">مجموع پاداش</div>
            <div class="text-base font-bold text-emerald-600 mt-1">
                +{{ number_format($stats['reward_sum']) }}
                <span class="text-[10px] font-normal text-gray-400">تومان</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="text-[11px] text-gray-400">مجموع جریمه</div>
            <div class="text-base font-bold text-rose-600 mt-1">
                −{{ number_format($stats['penalty_sum']) }}
                <span class="text-[10px] font-normal text-gray-400">تومان</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="text-[11px] text-gray-400">مجموع شارژ</div>
            <div class="text-base font-bold text-teal-700 mt-1">
                +{{ number_format($stats['charge_sum']) }}
                <span class="text-[10px] font-normal text-gray-400">تومان</span>
            </div>
        </div>
    </div>

    {{-- ─────── Filter chips ─────── --}}
    <div class="px-3 mt-4">
        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
            @foreach($filterChips as $chip)
                @php
                    $isActive = (string) $chip['value'] === (string) $typeFilter;
                    $params = array_filter(['type' => $chip['value']], fn($v) => $v !== null && $v !== '');
                    $url = route('tech.wallet') . ($params ? '?' . http_build_query($params) : '');
                @endphp
                <a href="{{ $url }}"
                   class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium border transition
                          {{ $isActive ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-gray-600 border-gray-200' }}">
                    {{ $chip['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ─────── Transactions list ─────── --}}
    <div class="px-3 mt-3 space-y-3">
        @forelse($transactions as $tx)
            @php
                $sign = $tx->type->sign();
                $signLabel = $sign > 0 ? '+' : ($sign < 0 ? '−' : '');
                $signClass = $sign > 0 ? 'text-emerald-700' : ($sign < 0 ? 'text-rose-600' : 'text-gray-700');
            @endphp
            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ $tx->type->badgeClass() }}">
                        {{ $tx->type->label() }}
                    </span>
                    <span class="font-bold text-base {{ $signClass }}">
                        {{ $signLabel }}{{ number_format(abs((int) $tx->amount)) }}
                        <span class="text-[10px] font-normal text-gray-400">تومان</span>
                    </span>
                </div>

                @php
                    // refid فقط برای ادمین/گزارش لازم است؛ برای تکنسین حذف می‌شود.
                    // قالب note از syncFinancial: "<description> — refid: <id>"
                    // فقط بخش refid را پاک می‌کنیم، descriptions را نگه می‌داریم.
                    $cleanNote = $tx->note ? trim(preg_replace('/(?:\s*—\s*)?refid:\s*\S+/u', '', $tx->note)) : null;
                @endphp
                @if($cleanNote)
                    <div class="mt-2 text-xs text-gray-700 leading-7">{{ $cleanNote }}</div>
                @endif

                <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                    <div class="flex items-center gap-2">
                        @if($tx->order)
                            <a href="{{ route('tech.orders.show', $tx->order) }}"
                               class="text-brand-700 font-medium" dir="ltr">
                                {{ $tx->order->order_code }}
                            </a>
                        @endif
                        @if($tx->balance_after !== null)
                            <span class="text-gray-300">·</span>
                            <span>مانده پس از تراکنش: {{ number_format((int) $tx->balance_after) }}</span>
                        @endif
                    </div>
                    <span dir="ltr">@jdatetime($tx->created_at)</span>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm12 8h.01"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">تراکنشی یافت نشد</div>
                <p class="text-xs text-gray-500 leading-7">
                    @if($typeFilter)
                        با فیلتر انتخاب‌شده تراکنشی پیدا نشد.
                    @else
                        هنوز تراکنشی برای کیف‌پول شما ثبت نشده است.
                    @endif
                </p>
            </div>
        @endforelse

        @if($transactions->hasPages())
            <div class="pt-2 pb-1">
                @include('crm::tech-panel._partials.pagination', ['paginator' => $transactions])
            </div>
        @endif
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.wallet'])
@endsection
