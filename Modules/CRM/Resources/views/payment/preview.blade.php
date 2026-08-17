<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرداخت فاکتور {{ $invoice->invoice_code }}</title>
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="/vendor/js/tailwind.min.js"></script>
    <style>
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-100 to-slate-200 min-h-screen py-8 px-3">
<div class="max-w-xl mx-auto">

    {{-- ─── کارت اصلی فاکتور ─── --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">

        {{-- هدر با لوگو/گرادیان --}}
        <div class="px-6 py-5 text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs opacity-80">کد فاکتور</div>
                    <div class="text-lg font-bold mt-0.5" dir="ltr">{{ $invoice->invoice_code }}</div>
                </div>
                <div>
                    @if($invoice->status === 'paid')
                        <span class="px-3 py-1 rounded-full bg-emerald-500/30 border border-emerald-200/40 text-emerald-50 text-xs font-bold">پرداخت شده</span>
                    @elseif($invoice->status === 'cancelled')
                        <span class="px-3 py-1 rounded-full bg-rose-500/30 border border-rose-200/40 text-rose-50 text-xs font-bold">لغو شده</span>
                    @else
                        <span class="px-3 py-1 rounded-full bg-amber-500/30 border border-amber-200/40 text-amber-50 text-xs font-bold">آماده پرداخت</span>
                    @endif
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-white/20">
                <div class="text-xs opacity-80">مبلغ قابل پرداخت</div>
                <div class="text-3xl font-bold mt-1">
                    {{ number_format((int) $invoice->total_amount) }}
                    <span class="text-sm font-normal opacity-90">تومان</span>
                </div>
            </div>
        </div>

        {{-- اطلاعات مشتری --}}
        @if($invoice->customer)
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="text-xs text-gray-400 mb-2">مشتری</div>
            <div class="text-sm font-bold text-gray-900">{{ $invoice->customer->display_name ?? '—' }}</div>
            @if($invoice->customer->mobile)
                <div class="text-xs text-gray-500 mt-1" dir="ltr">{{ $invoice->customer->mobile }}</div>
            @endif
        </div>
        @endif

        {{-- اطلاعات سفارش --}}
        @if($invoice->order)
        <div class="px-6 py-4 border-b border-gray-100 space-y-2">
            <div class="text-xs text-gray-400 mb-1">جزئیات سفارش</div>

            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500">کد سفارش</span>
                <span class="text-gray-900 font-bold" dir="ltr">{{ $invoice->order->order_code }}</span>
            </div>

            @if($invoice->order->technician)
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">تکنسین</span>
                    <span class="text-gray-700">{{ $invoice->order->technician->full_name ?? trim(($invoice->order->technician->first_name ?? '') . ' ' . ($invoice->order->technician->last_name ?? '')) }}</span>
                </div>
            @endif

            @if($invoice->order->brand || $invoice->order->device)
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">دستگاه</span>
                    <span class="text-gray-700">
                        {{ $invoice->order->brand?->name }}
                        @if($invoice->order->brand && $invoice->order->device) / @endif
                        {{ $invoice->order->device?->name }}
                    </span>
                </div>
            @endif

            @if($invoice->order->problem_title)
                <div class="flex items-start justify-between gap-2 text-xs">
                    <span class="text-gray-500 flex-shrink-0">عیب</span>
                    <span class="text-gray-700 text-left leading-6">{{ $invoice->order->problem_title }}</span>
                </div>
            @endif
        </div>
        @endif

        {{-- اقلام --}}
        @if($invoice->order && $invoice->order->items->isNotEmpty())
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="text-xs text-gray-400 mb-3">اقلام</div>
            <div class="space-y-2">
                @foreach($invoice->order->items as $item)
                    <div class="flex items-start justify-between text-xs">
                        <div class="text-gray-800">
                            {{ $item->title }}
                            @if($item->quantity > 1)
                                <span class="text-gray-400">× {{ $item->quantity }}</span>
                            @endif
                        </div>
                        <div class="text-gray-700 whitespace-nowrap">
                            {{ number_format((int) $item->total_price) }} <span class="text-gray-400">تومان</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- خلاصه مبلغ + دکمه پرداخت --}}
        <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm font-bold text-gray-900">جمع کل</span>
                <span class="text-xl font-bold text-emerald-700">
                    {{ number_format((int) $invoice->total_amount) }}
                    <span class="text-xs font-normal text-gray-400">تومان</span>
                </span>
            </div>

            @if($invoice->status === 'paid')
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm text-center font-bold">
                    ✓ این فاکتور قبلاً پرداخت شده است.
                </div>
            @elseif($invoice->isCashCollected())
                <div class="bg-sky-50 border border-sky-200 text-sky-800 rounded-xl px-4 py-3 text-sm text-center">
                    این فاکتور به‌صورت <strong>نقدی در محل</strong> تسویه می‌شود و نیازی به پرداخت آنلاین ندارد.
                </div>
            @elseif($invoice->status === 'cancelled')
                <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm text-center">
                    این فاکتور لغو شده و قابل پرداخت نیست.
                </div>
            @elseif(! $gatewayConfigured)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm text-center">
                    درگاه پرداخت در حال حاضر فعال نیست. لطفاً با پشتیبانی تماس بگیرید.
                </div>
            @else
                <form method="POST" action="{{ route('crm.payment.initiate', $invoice->public_token) }}">
                    @csrf
                    {{-- لینکِ برگشت به اپ — سمتِ سرور از allowlist عبور می‌کند --}}
                    <input type="hidden" name="return" value="{{ request('return') }}">
                    <button type="submit"
                            class="w-full py-4 rounded-xl text-white font-bold text-base transition flex items-center justify-center gap-2 shadow-lg active:scale-[.99]"
                            style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                        </svg>
                        پرداخت آنلاین
                    </button>
                </form>
                <p class="text-[11px] text-gray-400 text-center mt-3 leading-6">
                    با کلیک روی دکمه بالا به درگاه امن بانکی هدایت می‌شوید.
                </p>
            @endif
        </div>
    </div>

    {{-- پاورقی --}}
    <p class="text-center text-xs text-gray-400 mt-6">
        تمامی تراکنش‌ها از طریق درگاه امن انجام می‌شود.
    </p>
</div>
</body>
</html>
