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

            {{-- اطلاعات مشتری (فقط نمایش) --}}
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-6">
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->customer_name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $order->customer_mobile }}</div>
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
