@extends('layouts.admin')

@section('page-title', 'پیش‌فاکتور ' . $proforma->proforma_code)

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100" dir="ltr">{{ $proforma->proforma_code }}</h1>
            <span class="text-[11px] px-2 py-0.5 rounded-full {{ $proforma->statusBadge() }}">{{ $proforma->statusLabel() }}</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.proforma.public', $proforma->public_token) }}" target="_blank"
               class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">مشاهدهٔ رسید</a>
            <a href="{{ route('crm.proforma.pdf', $proforma->public_token) }}" target="_blank"
               class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">دانلود PDF</a>
            <form method="POST" action="{{ route('crm.proformas.send-sms', $proforma) }}" onsubmit="return confirm('ارسال پیش‌فاکتور با پیامک به مشتری؟');">
                @csrf
                <button type="submit" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">ارسال پیامک به مشتری</button>
            </form>
            <a href="{{ route('crm.proformas.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">بازگشت</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs text-gray-500">مشتری</div>{{ $proforma->customer_name ?: '—' }}</div>
        <div><div class="text-xs text-gray-500">موبایل</div><span dir="ltr">{{ $proforma->customer_mobile ?: '—' }}</span></div>
        <div><div class="text-xs text-gray-500">دستگاه</div>{{ $proforma->device_name ?: '—' }} {{ $proforma->brand_name }}</div>
        <div><div class="text-xs text-gray-500">سفارش</div>{{ $proforma->order?->order_code ?: '—' }}</div>
    </div>

    @include('crm::proformas._items', ['proforma' => $proforma])

    @if($proforma->description)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 text-sm">
            <div class="text-xs text-gray-500 mb-1">توضیحات</div>
            <p class="whitespace-pre-line">{{ $proforma->description }}</p>
        </div>
    @endif

    <div class="text-xs text-gray-400">
        ساخته‌شده:
        {{ $proforma->creatorUser?->full_name ?? ($proforma->creatorTech?->display_name ?? '—') }}
        · {{ \Morilog\Jalali\Jalalian::fromCarbon($proforma->created_at)->format('Y/m/d H:i') }}
        @if($proforma->valid_until) · اعتبار تا {{ \Morilog\Jalali\Jalalian::fromCarbon($proforma->valid_until)->format('Y/m/d') }}@endif
    </div>
</div>
@endsection
