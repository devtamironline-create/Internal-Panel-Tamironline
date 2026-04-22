@extends('layouts.admin')

@section('page-title', 'جزئیات مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->full_name }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1" dir="ltr">{{ $customer->mobile }}</p>
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
            <div class="text-xs text-gray-500 dark:text-gray-400">موبایل</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->mobile }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">تلفن ثابت</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->phone ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">ایمیل</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->email ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">کد ملی</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->national_code ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">استان / شهر</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $customer->province?->name ?: '—' }}{{ $customer->city ? ' / ' . $customer->city->name : '' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">کد پستی</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->postal_code ?: '—' }}</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">آدرس</div>
            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $customer->address ?: '—' }}</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">یادداشت‌ها</div>
            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $customer->notes ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">منبع ثبت</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">
                @switch($customer->registered_via)
                    @case('app') اپلیکیشن @break
                    @case('import') Import از CRM قدیمی @break
                    @default پنل
                @endswitch
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">وضعیت</div>
            <div class="text-sm">
                @if($customer->is_active)
                <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                @else
                <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سفارش‌های مشتری</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">در فازهای بعدی، لیست سفارش‌های تعمیر این مشتری اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endsection
