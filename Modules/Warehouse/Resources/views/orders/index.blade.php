@extends('layouts.admin')

@section('title', 'سفارشات')

@section('main')
<div class="p-6" x-data="ordersPage()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">سفارشات</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">مدیریت و پیگیری سفارشات ووکامرس</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="syncRecent()"
                    :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white rounded-lg transition">
                <svg class="w-5 h-5" :class="syncing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="syncing ? 'در حال همگام‌سازی...' : 'بروزرسانی'"></span>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-6 shadow-sm">
        <form method="GET" action="{{ route('warehouse.orders.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">جستجو</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="شماره سفارش، نام، تلفن..."
                       class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- WooCommerce Status -->
            <div>
                <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">وضعیت ووکامرس</label>
                <select name="status" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">همه</option>
                    @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Internal Status -->
            <div>
                <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">وضعیت داخلی</label>
                <select name="internal_status" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">همه</option>
                    @foreach($internalStatuses as $value => $label)
                    <option value="{{ $value }}" {{ request('internal_status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Shipped Status -->
            <div>
                <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">وضعیت ارسال</label>
                <select name="is_shipped" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">همه</option>
                    <option value="no" {{ request('is_shipped') == 'no' ? 'selected' : '' }}>ارسال نشده</option>
                    <option value="yes" {{ request('is_shipped') == 'yes' ? 'selected' : '' }}>ارسال شده</option>
                </select>
            </div>

            <!-- Assigned To -->
            <div>
                <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">مسئول</label>
                <select name="assigned_to" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">همه</option>
                    @foreach($staff as $user)
                    <option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                    فیلتر
                </button>
                <a href="{{ route('warehouse.orders.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white rounded-lg transition">
                    پاک
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedOrders.length > 0"
         x-transition
         class="bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4 flex items-center justify-between">
        <span class="text-blue-700 dark:text-blue-300">
            <span x-text="selectedOrders.length"></span> سفارش انتخاب شده
        </span>
        <div class="flex items-center gap-2">
            <select x-model="bulkAction" class="bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm">
                <option value="">انتخاب عملیات</option>
                <option value="mark_packed">علامت‌گذاری بسته‌بندی شده</option>
                <option value="mark_printed">علامت‌گذاری چاپ شده</option>
                <option value="update_internal_status">تغییر وضعیت داخلی</option>
            </select>
            <select x-show="bulkAction === 'update_internal_status'" x-model="bulkValue" class="bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-sm">
                @foreach($internalStatuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button @click="executeBulkAction()" :disabled="!bulkAction"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 text-white rounded-lg transition">
                اجرا
            </button>
        </div>
    </div>

    <!-- Main Content: New Orders Sidebar + Orders Table -->
    <div class="flex gap-6">
        <!-- New Orders Sidebar (Left) -->
        @if($newOrders->count() > 0)
        <div class="w-80 flex-shrink-0">
            <div class="sticky top-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        سفارشات جدید
                    </h2>
                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $newOrders->count() }}</span>
                </div>

                <div class="space-y-3 max-h-[calc(100vh-280px)] overflow-y-auto custom-scrollbar pl-2">
                    @foreach($newOrders as $newOrder)
                    <div x-data="orderCard({{ json_encode([
                        'id' => $newOrder->id,
                        'order_number' => $newOrder->order_number,
                        'customer_name' => $newOrder->customer_full_name,
                        'phone' => $newOrder->billing_phone,
                        'city' => $newOrder->shipping_city ?? $newOrder->billing_city,
                        'items_count' => $newOrder->items_count,
                        'total' => $newOrder->formatted_total,
                        'status' => $newOrder->status,
                        'status_label' => $newOrder->status_label,
                        'internal_status' => $newOrder->internal_status,
                        'date_created' => $newOrder->date_created?->toISOString(),
                        'elapsed' => $newOrder->elapsed_time,
                        'url' => route('warehouse.orders.show', $newOrder),
                    ]) }})"
                         @click="goToOrder()"
                         class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 cursor-pointer hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 transition-all group">

                        <!-- Timer Badge -->
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-blue-600 dark:text-blue-400 font-bold text-lg">#{{ $newOrder->order_number }}</span>
                            <div class="flex items-center gap-1 text-xs font-mono px-2 py-1 rounded-full"
                                 :class="timerClass">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-text="timerDisplay"></span>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="mb-3">
                            <div class="flex items-center gap-2 text-gray-900 dark:text-white font-medium">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                {{ $newOrder->customer_full_name }}
                            </div>
                            @if($newOrder->shipping_city || $newOrder->billing_city)
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mt-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $newOrder->shipping_city ?? $newOrder->billing_city }}
                            </div>
                            @endif
                        </div>

                        <!-- Order Details -->
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    {{ $newOrder->items_count }} عدد
                                </span>
                            </div>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $newOrder->formatted_total }}</span>
                        </div>

                        <!-- Status Badge -->
                        <div class="mt-3 flex items-center justify-between">
                            <span class="inline-flex px-2 py-1 text-xs rounded-full bg-{{ $newOrder->status_color }}-100 dark:bg-{{ $newOrder->status_color }}-900/50 text-{{ $newOrder->status_color }}-700 dark:text-{{ $newOrder->status_color }}-400">
                                {{ $newOrder->status_label }}
                            </span>
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Orders Table (Right / Full width if no new orders) -->
        <div class="flex-1 min-w-0">
            <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-right">
                                    <input type="checkbox" @change="toggleAll($event.target.checked)"
                                           class="rounded bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-500 text-blue-500 focus:ring-blue-500">
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">شماره سفارش</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">مشتری</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">محصولات</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">مبلغ</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">وضعیت داخلی</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">ارسال</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($orders as $order)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" value="{{ $order->id }}" x-model="selectedOrders"
                                           class="rounded bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-500 text-blue-500 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('warehouse.orders.show', $order) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                                        #{{ $order->order_number }}
                                    </a>
                                    @if($order->is_printed)
                                    <span class="inline-block mr-1" title="چاپ شده">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                    </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-700 dark:text-gray-300">{{ $order->customer_full_name }}</div>
                                    @if($order->billing_phone)
                                    <div class="text-xs text-gray-500 dark:text-gray-500">{{ $order->billing_phone }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $order->items_count }} عدد
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-medium">
                                    {{ $order->formatted_total }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-{{ $order->status_color }}-100 dark:bg-{{ $order->status_color }}-900/50 text-{{ $order->status_color }}-700 dark:text-{{ $order->status_color }}-400">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-{{ $order->internal_status_color }}-100 dark:bg-{{ $order->internal_status_color }}-900/50 text-{{ $order->internal_status_color }}-700 dark:text-{{ $order->internal_status_color }}-400">
                                        {{ $order->internal_status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($order->is_shipped)
                                    <span class="text-green-600 dark:text-green-400" title="{{ $order->tracking_code }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </span>
                                    @else
                                    <span class="text-gray-400 dark:text-gray-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-sm">
                                    {{ $order->date_created?->format('Y/m/d') }}
                                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ $order->date_created?->format('H:i') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('warehouse.orders.show', $order) }}"
                                           class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition"
                                           title="مشاهده">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('warehouse.orders.print', $order) }}"
                                           target="_blank"
                                           class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition"
                                           title="چاپ">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p>سفارشی یافت نشد</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($orders->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $orders->withQueryString()->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div x-show="showOrderModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" @click="showOrderModal = false"></div>

            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full mx-auto shadow-xl transform transition-all">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        جزئیات سفارش <span x-text="'#' + selectedOrder?.order_number"></span>
                    </h3>
                    <button @click="showOrderModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6" x-show="selectedOrder">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">مشتری:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="selectedOrder?.customer_name"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">تلفن:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="selectedOrder?.phone"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">شهر:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="selectedOrder?.city"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">تعداد اقلام:</span>
                            <span class="font-medium text-gray-900 dark:text-white" x-text="selectedOrder?.items_count + ' عدد'"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">مبلغ کل:</span>
                            <span class="font-bold text-lg text-gray-900 dark:text-white" x-text="selectedOrder?.total"></span>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <a :href="selectedOrder?.url"
                           class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-center rounded-lg transition">
                            مشاهده کامل
                        </a>
                        <button @click="showOrderModal = false"
                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white rounded-lg transition">
                            بستن
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="showToast"
         x-transition
         class="fixed bottom-4 left-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-4 max-w-sm z-50">
        <div class="flex items-start gap-3">
            <div :class="toastSuccess ? 'text-green-500' : 'text-red-500'">
                <svg x-show="toastSuccess" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="!toastSuccess" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300" x-text="toastMessage"></div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #4b5563;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>

<script>
function ordersPage() {
    return {
        selectedOrders: [],
        bulkAction: '',
        bulkValue: '',
        syncing: false,
        showToast: false,
        toastMessage: '',
        toastSuccess: true,
        showOrderModal: false,
        selectedOrder: null,

        toggleAll(checked) {
            if (checked) {
                this.selectedOrders = [...document.querySelectorAll('tbody input[type="checkbox"]')].map(cb => cb.value);
            } else {
                this.selectedOrders = [];
            }
        },

        async syncRecent() {
            this.syncing = true;
            try {
                const response = await fetch('{{ route('warehouse.orders.sync-recent') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                this.showNotification(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.showNotification('خطا در برقراری ارتباط', false);
            } finally {
                this.syncing = false;
            }
        },

        async executeBulkAction() {
            if (!this.bulkAction || this.selectedOrders.length === 0) return;

            try {
                const response = await fetch('{{ route('warehouse.orders.bulk-update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        order_ids: this.selectedOrders,
                        action: this.bulkAction,
                        value: this.bulkValue
                    })
                });
                const data = await response.json();
                this.showNotification(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.showNotification('خطا در اجرای عملیات', false);
            }
        },

        openOrderModal(order) {
            this.selectedOrder = order;
            this.showOrderModal = true;
        },

        showNotification(message, success) {
            this.toastMessage = message;
            this.toastSuccess = success;
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);
        }
    }
}

function orderCard(order) {
    return {
        order: order,
        timerDisplay: order.elapsed.formatted,
        timerClass: 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400',
        timerInterval: null,
        totalSeconds: 0,

        init() {
            // Calculate total seconds from elapsed time
            this.totalSeconds = (order.elapsed.total_hours * 3600) + (order.elapsed.minutes * 60) + order.elapsed.seconds;
            this.updateTimerClass();

            // Start the timer
            this.timerInterval = setInterval(() => {
                this.totalSeconds++;
                this.updateTimerDisplay();
                this.updateTimerClass();
            }, 1000);
        },

        destroy() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }
        },

        updateTimerDisplay() {
            const hours = Math.floor(this.totalSeconds / 3600);
            const minutes = Math.floor((this.totalSeconds % 3600) / 60);
            const seconds = this.totalSeconds % 60;
            this.timerDisplay = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },

        updateTimerClass() {
            const minutes = Math.floor(this.totalSeconds / 60);

            if (minutes < 30) {
                // Green: Less than 30 minutes
                this.timerClass = 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400';
            } else if (minutes < 60) {
                // Yellow: 30-60 minutes
                this.timerClass = 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-400';
            } else if (minutes < 120) {
                // Orange: 1-2 hours
                this.timerClass = 'bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-400';
            } else {
                // Red: More than 2 hours
                this.timerClass = 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400';
            }
        },

        goToOrder() {
            window.location.href = this.order.url;
        }
    }
}
</script>
@endsection
