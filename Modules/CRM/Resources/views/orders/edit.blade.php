@extends('layouts.admin')

@section('page-title', 'ویرایش سفارش')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش سفارش <span dir="ltr">{{ $order->order_code }}</span></h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.orders.update', $order) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- اطلاعات مشتری (قابل ویرایش — روی پروفایل مشتری هم اعمال می‌شود) --}}
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">اطلاعات مشتری</h3>
                    <span class="text-[11px] text-amber-700 dark:text-amber-300">⚠ تغییر این فیلدها روی پروفایل مشتری اعمال می‌شود</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">نام مشتری</label>
                        <input type="text" name="customer_name" maxlength="255"
                               value="{{ old('customer_name', $order->customer?->first_name ?: $order->customer_name) }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        @error('customer_name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">شماره موبایل</label>
                        <input type="text" name="customer_mobile" maxlength="20" dir="ltr"
                               value="{{ old('customer_mobile', $order->customer?->mobile ?: $order->customer_mobile) }}"
                               placeholder="09xxxxxxxxx"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        @error('customer_mobile')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            @include('crm::orders._order_fields', [
                'brands' => $brands,
                'devices' => $devices,
                'provinces' => $provinces,
                'cities' => $cities,
                'showFinalPrice' => true,
            ])

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره تغییرات</button>
                <a href="{{ route('crm.orders.show', $order) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
