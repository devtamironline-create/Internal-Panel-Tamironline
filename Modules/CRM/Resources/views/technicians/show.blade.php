@extends('layouts.admin')

@section('page-title', 'جزئیات تکنسین')

@section('main')
@php
    $typeLabels = ['regular' => 'عادی', 'senior' => 'ارشد', 'expert' => 'متخصص', 'freelance' => 'فریلنسر'];
    $calcLabels = ['percent_of_customer' => 'درصد از مبلغ دریافتی مشتری', 'percent_of_total' => 'درصد از کل فاکتور'];
@endphp
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            @if($technician->personal_image)
            <img src="{{ $technician->personal_image }}" alt="{{ $technician->full_name }}" class="w-16 h-16 rounded-full object-cover bg-gray-100">
            @endif
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $technician->full_name }}</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1" dir="ltr">{{ $technician->mobile }}</p>
                @if($technician->tech_code)
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" dir="ltr">کد: {{ $technician->tech_code }}</p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('edit-crm-technician')
            <a href="{{ route('crm.technicians.edit', $technician) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.technicians.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">مشخصات و آدرس</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">تلفن ثابت</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->phone ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">تلفن اضطراری</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->phone_force ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">ایمیل</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->email ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">کد ملی</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->national_code ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">جنسیت</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->gender === 'male' ? 'مرد' : ($technician->gender === 'female' ? 'زن' : '—') }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">استان / شهر</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->province?->name ?: '—' }}{{ $technician->city ? ' / ' . $technician->city->name : '' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400 col-span-2">آدرس</dt>
                <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $technician->address ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تخصص و قوانین کاری</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">تخصص</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->specialty ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سطح</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $typeLabels[$technician->tech_type] ?? $technician->tech_type }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">درصد کمیسیون</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->commission_percent }}%</dd>

                <dt class="text-gray-500 dark:text-gray-400">روش محاسبه</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $calcLabels[$technician->calc_type] ?? $technician->calc_type }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سقف سفارش همزمان</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->max_concurrent_orders ?? 'نامحدود' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">سقف مبلغ سفارش</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $technician->max_order_price ? number_format($technician->max_order_price) . ' تومان' : 'نامحدود' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400 col-span-2">توضیحات</dt>
                <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $technician->description ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اطلاعات بانکی</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">شماره شبا</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->bank_sheba ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">شماره کارت</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->bank_card ?: '—' }}</dd>

                <dt class="text-gray-500 dark:text-gray-400">شماره حساب</dt>
                <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $technician->bank_account ?: '—' }}</dd>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">وضعیت</h2>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @if($technician->is_active)
                <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                @else
                <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                @endif
                @if($technician->ready_for_delivery)
                <span class="px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">آماده دریافت سفارش</span>
                @endif
            </div>
            <div class="text-sm">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">یادداشت‌های داخلی</div>
                <p class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $technician->notes ?: '—' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سفارش‌های تکنسین</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">در فازهای بعدی، لیست سفارش‌های تخصیص‌یافته به این تکنسین اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endsection
