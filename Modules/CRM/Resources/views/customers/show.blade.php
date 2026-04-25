@extends('layouts.admin')

@section('page-title', 'جزئیات مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->display_name }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1" dir="ltr">{{ $customer->mobile }}</p>
            <p class="text-xs text-gray-500 mt-1">شماره اشتراک: <span dir="ltr" class="font-medium">{{ $customer->subscription }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            @can('edit-crm-customer')
            <a href="{{ route('crm.customers.edit', $customer) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.customers.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">شماره اشتراک</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->subscription }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">نام</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $customer->first_name ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">موبایل</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->mobile }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">تلفن ثابت</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->phone ?: '—' }}</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">یادداشت‌ها</div>
            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $customer->notes ?: '—' }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سفارش‌های مشتری</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">در فازهای بعدی، لیست سفارش‌های تعمیر این مشتری اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endsection
