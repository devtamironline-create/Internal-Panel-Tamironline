@extends('layouts.admin')

@section('page-title', 'داشبورد تکنسین')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سلام {{ $technician->first_name }} 👋</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">خلاصه وضعیت سفارش‌ها و کیف‌پول شما</p>
    </div>

    {{-- کارت‌های آماری --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">کل سفارش‌ها</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">هماهنگ شده</div>
            <div class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stats['coordinated']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">باز شده</div>
            <div class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($stats['open']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500 dark:text-gray-400">تکمیل شده</div>
            <div class="text-2xl font-bold text-green-600 mt-1">{{ number_format($stats['completed']) }}</div>
        </div>
    </div>

    {{-- موجودی کیف‌پول --}}
    <div class="bg-gradient-to-l from-brand-500 to-brand-700 rounded-xl shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm opacity-90">موجودی کیف‌پول شما</div>
                <div class="text-3xl font-bold mt-1">
                    {{ number_format(abs($technician->wallet_balance)) }}
                    <span class="text-base font-normal">تومان</span>
                </div>
                <div class="text-xs opacity-90 mt-1">
                    {{ $technician->wallet_balance >= 0 ? 'شرکت به شما بدهکار است' : 'شما به شرکت بدهکار هستید' }}
                </div>
            </div>
            <a href="{{ route('crm.tech.wallet') }}" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm backdrop-blur">
                مشاهده جزئیات
            </a>
        </div>
    </div>

    {{-- سفارش‌های اخیر --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">سفارش‌های اخیر</h2>
            <a href="{{ route('crm.orders.my') }}" class="text-sm text-brand-600 hover:underline">همه سفارش‌ها →</a>
        </div>

        @if($recentOrders->count())
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($recentOrders as $order)
            <li>
                <a href="{{ route('crm.tech.orders.show', $order) }}" class="block px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100" dir="ltr">{{ $order->order_code }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $order->customer_name }} · <span dir="ltr">{{ $order->customer_mobile }}</span>
                                @if($order->brand) · {{ $order->brand->name }}@endif
                                @if($order->device) / {{ $order->device->name }}@endif
                            </div>
                            @if($order->problem_title)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ $order->problem_title }}</div>
                            @endif
                        </div>
                        @if($order->visit_scheduled_at)
                        <div class="text-xs text-gray-500 whitespace-nowrap" dir="ltr">{{ $order->visit_scheduled_at->format('Y-m-d H:i') }}</div>
                        @endif
                    </div>
                </a>
            </li>
            @endforeach
        </ul>
        @else
        <div class="px-6 py-12 text-center text-gray-500">سفارشی به شما تخصیص داده نشده.</div>
        @endif
    </div>
</div>
@endsection
