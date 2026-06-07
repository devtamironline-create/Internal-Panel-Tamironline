@extends('layouts.admin')

@section('page-title', 'جزئیات مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->display_name }}</h1>
            <p class="mt-1">@tel($customer->mobile)</p>
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
            <div class="text-sm">@tel($customer->mobile)</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">تلفن ثابت</div>
            <div class="text-sm">@tel($customer->phone)</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">یادداشت‌ها</div>
            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $customer->notes ?: '—' }}</div>
        </div>
    </div>

    {{-- آدرس‌های اپ موبایل (multi-address) — read-only؛ ویرایش/حذف از سمت کاربر اپ --}}
    @php
        $appAddresses = $customer->addresses()->with(['province:id,name', 'city:id,name'])->get();
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">آدرس‌های ثبت‌شده در اپ موبایل</h2>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $appAddresses->count() }} آدرس</span>
        </div>

        @if ($appAddresses->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">این مشتری از طریق اپ موبایل آدرسی ثبت نکرده است.</p>
        @else
            <div class="space-y-3">
                @foreach ($appAddresses as $addr)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $addr->label ?: 'بدون عنوان' }}</span>
                                @if ($addr->is_default)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">پیش‌فرض</span>
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400">#{{ $addr->id }}</span>
                        </div>
                        <div class="text-gray-700 dark:text-gray-300">
                            {{ optional($addr->province)->name }}{{ $addr->province && $addr->city ? ' — ' : '' }}{{ optional($addr->city)->name }}
                        </div>
                        <div class="text-gray-700 dark:text-gray-300 mt-1">{{ $addr->full_address }}</div>
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            @if ($addr->postal_code)
                                <span dir="ltr">کدپستی: {{ $addr->postal_code }}</span>
                            @endif
                            @if ($addr->phone)
                                <span>تلفن: @tel($addr->phone)</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-[11px] text-gray-400 mt-4">
            ادمین این آدرس‌ها را فقط مشاهده می‌کند؛ ویرایش/حذف فقط از سمت خود کاربر در اپ ممکن است.
            آدرس اصلی (province/city/address روی پروفایل مشتری) جداست و در همین صفحه‌ی ویرایش مشتری قابل تغییر است.
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سفارش‌های مشتری</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">در فازهای بعدی، لیست سفارش‌های تعمیر این مشتری اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endsection
