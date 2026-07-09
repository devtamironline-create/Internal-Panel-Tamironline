@extends('crm::tech-panel.layout')

@section('title', 'پیش‌فاکتور ' . $proforma->proforma_code)

@section('body')
<div class="min-h-screen pb-nav" style="background:#eef0f4;">
    <div class="px-5 pt-6 pb-4 flex items-center justify-between">
        <a href="{{ route('tech.proformas.index') }}" class="w-9 h-9 rounded-full bg-white flex items-center justify-center shadow-sm" aria-label="بازگشت">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>
        </a>
        <h1 class="font-bold text-gray-800" dir="ltr">{{ $proforma->proforma_code }}</h1>
        <span class="w-9"></span>
    </div>

    @if(session('success'))<div class="mx-4 mb-3 bg-green-50 border border-green-200 text-green-800 rounded-xl p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mx-4 mb-3 bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 text-sm">{{ session('error') }}</div>@endif

    <div class="px-4 space-y-3">
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] px-2 py-0.5 rounded-full {{ $proforma->statusBadge() }}">{{ $proforma->statusLabel() }}</span>
                <span class="text-xs text-gray-400">{{ \Morilog\Jalali\Jalalian::fromCarbon($proforma->created_at)->format('Y/m/d') }}</span>
            </div>
            <div class="text-sm text-gray-700">{{ $proforma->customer_name ?: '—' }} <span class="text-gray-400 text-xs" dir="ltr">{{ $proforma->customer_mobile }}</span></div>
            <div class="text-xs text-gray-400 mt-0.5">{{ $proforma->device_name }} {{ $proforma->brand_name }}</div>
        </div>

        <div class="bg-white rounded-2xl p-4 shadow-sm">
            @foreach($proforma->items as $item)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0 text-sm">
                    <div class="text-gray-700">{{ $item['title'] ?? '' }} <span class="text-gray-400 text-xs" dir="ltr">×{{ (int) ($item['quantity'] ?? 1) }}</span></div>
                    <div class="font-medium" dir="ltr">{{ number_format((int) ($item['total'] ?? 0)) }}</div>
                </div>
            @endforeach
            <div class="mt-2 pt-2 border-t border-gray-100 space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">جمع اقلام</span><span dir="ltr">{{ number_format($proforma->subtotal) }}</span></div>
                @if($proforma->discount > 0)<div class="flex justify-between"><span class="text-gray-500">تخفیف</span><span dir="ltr" class="text-rose-600">{{ number_format($proforma->discount) }}−</span></div>@endif
                <div class="flex justify-between font-bold text-base"><span>مبلغ نهایی</span><span dir="ltr" class="text-brand-700">{{ number_format($proforma->total) }}</span></div>
            </div>
        </div>

        @if($proforma->description)
            <div class="bg-white rounded-2xl p-4 shadow-sm text-sm text-gray-600 whitespace-pre-line">{{ $proforma->description }}</div>
        @endif

        @if($proforma->status === 'converted')
            <div class="bg-purple-50 border border-purple-200 text-purple-800 rounded-2xl p-4 text-sm text-center">
                این پیش‌فاکتور به <b>فاکتور نهایی</b> تبدیل شده است.
                @if($proforma->order)<a href="{{ route('tech.orders.show', $proforma->order) }}" class="block mt-2 text-brand-600 font-bold">مشاهدهٔ سفارش ←</a>@endif
            </div>
        @else
            <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-2xl p-3 text-xs text-center leading-6">
                این پیش‌فاکتور صادر شد و در اپِ مشتری (داخلِ جزئیاتِ سفارش) نمایش داده می‌شود.
            </div>

            @if($proforma->order_id)
                {{-- نهایی کردن: تأیید مشتری + رفتن به تکمیلِ سفارش با مبلغِ همین پیش‌فاکتور --}}
                <form method="POST" action="{{ route('tech.proformas.finalize', $proforma) }}"
                      onsubmit="return confirm('پیش‌فاکتور تأیید و برای تکمیلِ نهاییِ سفارش با همین مبلغ آماده شود؟');">
                    @csrf
                    <button type="submit" class="w-full py-3.5 bg-brand-600 text-white rounded-2xl font-bold active:scale-95 transition">نهایی کردن (تکمیل سفارش)</button>
                </form>
                <p class="text-[11px] text-gray-400 text-center px-4">با «نهایی کردن»، مبلغِ پیش‌فاکتور روی فرمِ تکمیلِ سفارش پیش‌پر می‌شود و پس از ثبت، فاکتور نهایی صادر می‌گردد.</p>
            @endif
        @endif
        <a href="{{ route('crm.proforma.public', $proforma->public_token) }}" target="_blank" class="block text-center py-3 text-brand-600 text-sm font-medium">مشاهدهٔ رسید مشتری ←</a>
        <a href="{{ route('crm.proforma.pdf', $proforma->public_token) }}" target="_blank" class="block text-center pb-3 text-indigo-600 text-sm font-medium">دانلود PDF (بدونِ مهر و هولوگرام)</a>
    </div>
</div>
@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.proformas.index'])
@endsection
