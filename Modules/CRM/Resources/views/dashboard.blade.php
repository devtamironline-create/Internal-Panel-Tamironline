@extends('layouts.admin')

@section('page-title', 'داشبورد CRM')

@section('main')
@php
    use Modules\CRM\Enums\OrderStatus;
    $statusCases = OrderStatus::cases();
@endphp

<div class="p-6 space-y-6">
    {{-- هدر --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">داشبورد خدمات تعمیرات</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">نمای کلی از مشتری‌ها، سفارش‌ها، تکنسین‌ها و وضعیت مالی</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @can('create-crm-order')
            <a href="{{ route('crm.orders.create') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                سفارش جدید
            </a>
            @endcan
            @can('create-crm-customer')
            <a href="{{ route('crm.customers.create') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                مشتری جدید
            </a>
            @endcan
        </div>
    </div>

    {{-- کارت‌های آمار --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- مشتری‌ها --}}
        <a href="{{ route('crm.customers.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">مشتری‌ها</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($customersTotal) }}</div>
                    <div class="text-xs text-gray-500 mt-2">تعداد کل</div>
                </div>
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
        </a>

        {{-- تکنسین‌ها --}}
        <a href="{{ route('crm.technicians.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">تکنسین‌های فعال</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($techsActive) }}</div>
                    <div class="text-xs text-green-600 mt-2">{{ number_format($techsReady) }} آماده دریافت سفارش</div>
                </div>
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-300 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/></svg>
                </div>
            </div>
        </a>

        {{-- سفارش‌های امروز --}}
        <a href="{{ route('crm.orders.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">سفارش‌های امروز</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($ordersToday) }}</div>
                    <div class="text-xs text-amber-600 mt-2">{{ number_format($ordersOpen) }} باز / در حال انجام</div>
                </div>
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-300 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
            </div>
        </a>

        {{-- مالی ماه --}}
        <a href="{{ route('crm.invoices.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">مالی ماه جاری</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($invoicesMonthTotal) }} <span class="text-sm font-normal">تومان</span></div>
                    <div class="text-xs mt-2 space-x-2 rtl:space-x-reverse">
                        <span class="text-green-600">پرداخت‌شده: {{ number_format($invoicesMonthPaid) }}</span>
                        <span class="text-red-600">معوقه: {{ number_format($invoicesPending) }}</span>
                    </div>
                </div>
                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </a>
    </div>

    {{-- توزیع سفارش‌ها بر اساس وضعیت --}}
    @if(count($ordersByStatus) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">توزیع سفارش‌ها بر اساس وضعیت</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($statusCases as $case)
                @php $count = $ordersByStatus[$case->value] ?? 0; @endphp
                @if($count > 0)
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs {{ $case->badgeClass() }}">
                    <span>{{ $case->label() }}</span>
                    <span class="font-bold">{{ number_format($count) }}</span>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- آخرین سفارش‌ها + تکنسین‌های پرکار --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- آخرین سفارش‌ها --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">آخرین سفارش‌ها</h2>
                <a href="{{ route('crm.orders.index') }}" class="text-xs text-brand-600 hover:underline">همه</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">کد</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">مشتری</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">تکنسین</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">وضعیت</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">زمان</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($recentOrders as $order)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('crm.orders.show', $order) }}" class="font-medium text-brand-600 hover:underline" dir="ltr">{{ $order->order_code }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $order->customer?->first_name ?: $order->customer_name ?: '—' }}
                                <div class="text-xs text-gray-500" dir="ltr">{{ $order->customer_mobile }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $order->technician?->full_name ?: '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if($order->status instanceof OrderStatus)
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                                @else
                                <span class="text-xs text-gray-500">{{ $order->status }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-500" dir="ltr">{{ $order->created_at?->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">هنوز سفارشی ثبت نشده.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- تکنسین‌های پرکار --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">تکنسین‌های پرکار ماه</h2>
                <a href="{{ route('crm.technicians.index') }}" class="text-xs text-brand-600 hover:underline">همه</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($topTechnicians as $tech)
                <a href="{{ route('crm.technicians.show', $tech) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tech->full_name }}</div>
                        <div class="text-xs text-gray-500" dir="ltr">{{ $tech->mobile }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-brand-600">{{ number_format($tech->orders_this_month_count) }}</div>
                        <div class="text-xs text-gray-500">سفارش</div>
                    </div>
                </a>
                @empty
                <div class="px-5 py-8 text-center text-gray-500 text-sm">داده‌ای موجود نیست.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- لینک‌های سریع --}}
    <div class="bg-gradient-to-br from-brand-50 to-blue-50 dark:from-gray-800 dark:to-gray-900 border border-brand-100 dark:border-gray-700 rounded-xl p-5">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">دسترسی سریع</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @can('view-crm-orders')
            <a href="{{ route('crm.orders.index') }}" class="flex flex-col items-center gap-2 px-4 py-3 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition text-sm text-gray-700 dark:text-gray-200">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                سفارش‌ها
            </a>
            @endcan
            @can('view-crm-customers')
            <a href="{{ route('crm.customers.index') }}" class="flex flex-col items-center gap-2 px-4 py-3 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition text-sm text-gray-700 dark:text-gray-200">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/></svg>
                مشتری‌ها
            </a>
            @endcan
            @can('view-crm-invoices')
            <a href="{{ route('crm.invoices.index') }}" class="flex flex-col items-center gap-2 px-4 py-3 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition text-sm text-gray-700 dark:text-gray-200">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                فاکتورها
            </a>
            @endcan
            @can('manage-crm-sync')
            <a href="{{ route('crm.sync.settings') }}" class="flex flex-col items-center gap-2 px-4 py-3 bg-white dark:bg-gray-800 rounded-lg hover:shadow-md transition text-sm text-gray-700 dark:text-gray-200">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                سینک وردپرس
            </a>
            @endcan
        </div>
    </div>
</div>
@endsection
