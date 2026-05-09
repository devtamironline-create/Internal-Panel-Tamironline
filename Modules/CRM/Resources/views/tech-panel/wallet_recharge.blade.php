@extends('crm::tech-panel.layout')

@section('title', 'شارژ کیف‌پول')

@php
    $balance = (int) $technician->true_balance;
    $isDebtor = $balance < 0;
    $absBalance = abs($balance);

    // تبدیل عدد به برچسب فارسی خوانا (۵۰۰ هزار تومان / ۱.۵ میلیون تومان)
    $persianAmountLabel = function (int $n): string {
        $latin = ['0','1','2','3','4','5','6','7','8','9'];
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $toFa = fn($s) => str_replace($latin, $persian, (string) $s);
        if ($n < 1_000_000) {
            $k = intdiv($n, 1000);
            return $toFa($k) . ' هزار تومان';
        }
        $millions = $n / 1_000_000;
        $whole = (int) $millions;
        $frac = $millions - $whole;
        $label = $frac > 0
            ? $toFa(rtrim(rtrim(number_format($millions, 1, '.', ''), '0'), '.'))
            : $toFa($whole);
        return $label . ' میلیون تومان';
    };

    // پلهٔ ۵۰۰ هزار از ۵۰۰ هزار تا ۱۰ میلیون
    $presets = [];
    for ($i = 500_000; $i <= 10_000_000; $i += 500_000) {
        $presets[] = ['value' => $i, 'label' => $persianAmountLabel($i)];
    }
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-24"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.wallet') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">شارژ کیف‌پول</div>
            <div class="w-10"></div>
        </div>
    </div>

    {{-- ─────── Debt-aware balance card ─────── --}}
    <div class="relative z-10 -mt-16 mx-3 bg-white rounded-[28px] shadow-lg p-5 text-center">
        <div class="text-[11px] text-gray-400 mb-1">مانده فعلی شما</div>
        <div class="text-2xl font-bold {{ $isDebtor ? 'text-rose-600' : 'text-emerald-700' }}">
            {{ $isDebtor ? '−' : '' }}{{ number_format($absBalance) }}
            <span class="text-sm font-normal text-gray-400 mr-1">تومان</span>
        </div>
        <div class="text-xs text-gray-500 mt-2">
            {{ $isDebtor ? 'برای تسویه می‌توانید همین مبلغ را شارژ کنید.' : 'موجودی شما مثبت است.' }}
        </div>
    </div>

    @if(! $gatewayConfigured)
        <div class="mx-3 mt-4 bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900 leading-7">
            درگاه پرداخت توسط ادمین هنوز پیکربندی نشده است. لطفاً برای شارژ
            کیف‌پول با مدیر سیستم تماس بگیرید.
        </div>
    @endif

    @if(session('error'))
        <div class="mx-3 mt-4 bg-rose-50 border border-rose-200 rounded-2xl p-3 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- ─────── Recharge form ─────── --}}
    <form method="POST" action="{{ route('tech.wallet.recharge.initiate') }}"
          class="mx-3 mt-4 bg-white rounded-[24px] shadow-sm p-5"
          x-data="{
            amount: {{ (int) old('amount', $isDebtor ? $absBalance : 0) }},
            min: 1000,
            max: 50000000,
            setAmount(v) { this.amount = Math.max(this.min, Math.min(this.max, Number(v) || 0)); },
            fmt(n) { return Number(n||0).toLocaleString('fa-IR'); },
          }">
        @csrf

        <div class="text-[11px] text-gray-400 mb-2">مبلغ شارژ (تومان)</div>

        {{-- مبلغ آزاد قابل تایپ --}}
        <input type="number" name="amount" min="1000" max="50000000" step="1000" required
               x-model.number="amount"
               inputmode="numeric"
               placeholder="مثلاً 495000"
               class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 px-3 text-center text-2xl font-bold focus:bg-white focus:border-brand-400 focus:outline-none"
               dir="ltr">

        {{-- مبلغ به حروف --}}
        <div class="mt-1.5 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-900 text-center">
            <span x-text="amount > 0 ? (numberToPersianWords(amount) + ' تومان') : 'مبلغ مورد نظر را وارد کنید'"></span>
        </div>

        @error('amount')
            <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
        @enderror

        {{-- ─────── دکمهٔ تسویه بدهی فعلی ─────── --}}
        @if($isDebtor && $absBalance > 0)
            <button type="button" @click="setAmount({{ $absBalance }})"
                    class="mt-3 w-full py-2.5 rounded-xl border-2 border-dashed border-emerald-400 bg-emerald-50 text-emerald-800 text-xs font-bold">
                تسویه دقیق بدهی: {{ number_format($absBalance) }} تومان
            </button>
        @endif

        {{-- ─────── Quick-pick buttons (500K → 10M, step 500K) ─────── --}}
        <div class="mt-4">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-[11px] text-gray-500 font-bold">انتخاب سریع مبلغ</div>
                <div class="text-[10px] text-gray-400">پلهٔ ۵۰۰ هزار</div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach($presets as $p)
                    <button type="button" @click="setAmount({{ $p['value'] }})"
                            :class="amount === {{ $p['value'] }}
                                ? 'bg-gradient-to-l from-brand-700 to-brand-600 text-white border-brand-700 shadow-md scale-[1.02]'
                                : 'bg-white text-gray-700 border-gray-200 hover:border-brand-300 hover:bg-brand-50/40'"
                            class="py-2.5 rounded-xl border text-[12px] font-bold transition-all duration-150 active:scale-95">
                        {{ $p['label'] }}
                    </button>
                @endforeach
            </div>
            <p class="text-[10px] text-gray-400 mt-3 leading-6 text-center">
                از ۵۰۰ هزار تا ۱۰ میلیون تومان. برای مبلغ سفارشی، مستقیم در فیلد بالا تایپ کنید.
            </p>
        </div>

        {{-- ─────── Submit ─────── --}}
        <button type="submit"
                @if(! $gatewayConfigured) disabled @endif
                :disabled="!amount || amount < min"
                class="mt-5 w-full py-3.5 rounded-xl text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
            <span x-show="amount >= min" x-cloak>پرداخت <span x-text="fmt(amount)"></span> تومان</span>
            <span x-show="amount < min">مبلغ را وارد کنید</span>
        </button>

        <p class="text-[10px] text-gray-400 mt-3 leading-6 text-center">
            با کلیک بر دکمهٔ پرداخت به درگاه امن منتقل می‌شوید.
            بعد از پرداخت موفق، مبلغ به‌صورت خودکار به کیف‌پول شما اضافه می‌شود.
        </p>
    </form>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.wallet'])

{{-- تابع تبدیل عدد به حروف فارسی — همان helper استفاده‌شده در order_show --}}
<script>
window.numberToPersianWords = window.numberToPersianWords || (function () {
    const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    const tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    const hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    const scales = ['', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد'];

    function under1000(n) {
        if (n === 0) return '';
        const parts = [];
        const h = Math.floor(n / 100);
        const rest = n % 100;
        if (h) parts.push(hundreds[h]);
        if (rest >= 10 && rest <= 19) parts.push(teens[rest - 10]);
        else {
            const t = Math.floor(rest / 10);
            const o = rest % 10;
            if (t) parts.push(tens[t]);
            if (o) parts.push(ones[o]);
        }
        return parts.join(' و ');
    }

    return function (n) {
        n = Math.floor(Number(n) || 0);
        if (n === 0) return 'صفر';
        if (n < 0) return 'منفی ' + window.numberToPersianWords(-n);
        const groups = [];
        let i = 0;
        while (n > 0 && i < scales.length) {
            const part = n % 1000;
            if (part) groups.unshift(under1000(part) + scales[i]);
            n = Math.floor(n / 1000);
            i++;
        }
        return groups.join(' و ');
    };
})();
</script>
@endsection
