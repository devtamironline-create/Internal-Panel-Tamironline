@extends('layouts.admin')

@section('page-title', 'پروفایل من')

@section('main')
@php
    $typeLabels = ['regular' => 'عادی', 'senior' => 'ارشد', 'expert' => 'متخصص', 'freelance' => 'فریلنسر'];
@endphp
<div class="p-6 space-y-6">
    <div class="flex items-center gap-4">
        @if($technician->personal_image)
        <img src="{{ $technician->personal_image }}" class="w-16 h-16 rounded-full object-cover bg-gray-100">
        @endif
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ trim($technician->first_name . ' ' . ($technician->last_name ?? '')) }}</h1>
            <div class="text-sm text-gray-500" dir="ltr">{{ $technician->mobile }}</div>
            @if($technician->tech_code)
            <div class="text-xs text-gray-500 mt-0.5" dir="ltr">کد: {{ $technician->tech_code }}</div>
            @endif
        </div>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800">
        ویرایش پروفایل فقط از طریق ادمین انجام می‌شود. در صورت نیاز به تغییر اطلاعات، با پشتیبانی تماس بگیرید.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">مشخصات تماس</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500">موبایل</dt>
                <dd dir="ltr">{{ $technician->mobile }}</dd>

                <dt class="text-gray-500">تلفن ثابت</dt>
                <dd dir="ltr">{{ $technician->phone ?: '—' }}</dd>

                <dt class="text-gray-500">ایمیل</dt>
                <dd dir="ltr">{{ $technician->email ?: '—' }}</dd>

                <dt class="text-gray-500">کد ملی</dt>
                <dd dir="ltr">{{ $technician->national_code ?: '—' }}</dd>

                <dt class="text-gray-500">استان / شهر</dt>
                <dd>{{ $technician->province?->name ?: '—' }}{{ $technician->city ? ' / ' . $technician->city->name : '' }}</dd>

                <dt class="text-gray-500 col-span-2">آدرس</dt>
                <dd class="col-span-2 whitespace-pre-wrap">{{ $technician->address ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تخصص و قوانین کاری</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500">تخصص</dt>
                <dd>{{ $technician->specialty ?: '—' }}</dd>

                <dt class="text-gray-500">سطح</dt>
                <dd>{{ $typeLabels[$technician->tech_type] ?? $technician->tech_type }}</dd>

                <dt class="text-gray-500">درصد کمیسیون</dt>
                <dd>{{ $technician->commission_percent }}%</dd>

                <dt class="text-gray-500">سقف سفارش همزمان</dt>
                <dd>{{ $technician->max_concurrent_orders ?? 'نامحدود' }}</dd>

                <dt class="text-gray-500">سقف مبلغ سفارش</dt>
                <dd>{{ $technician->max_order_price ? number_format($technician->max_order_price) . ' تومان' : 'نامحدود' }}</dd>

                <dt class="text-gray-500">آماده دریافت سفارش</dt>
                <dd>{{ $technician->ready_for_delivery ? 'بله' : 'خیر' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
