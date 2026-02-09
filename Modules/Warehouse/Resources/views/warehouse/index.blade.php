@extends('layouts.admin')
@section('page-title', 'لیست سفارشات')
@section('main')
@php
    $statusLabels = \Modules\Warehouse\Models\WarehouseOrder::statusLabels();
    $statusColors = \Modules\Warehouse\Models\WarehouseOrder::statusColors();
    $statusIcons = \Modules\Warehouse\Models\WarehouseOrder::statusIcons();
    $allStatuses = \Modules\Warehouse\Models\WarehouseOrder::$statuses;
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">لیست سفارشات</h1>
        </div>
        @canany(['manage-warehouse', 'manage-permissions'])
        <a href="{{ route('warehouse.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            + سفارش جدید
        </a>
        @endcanany
    </div>

    <!-- Status Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="flex overflow-x-auto no-scrollbar border-b border-gray-200">
            @foreach($allStatuses as $statusKey)
                @php
                    $tab = [
                        'label' => $statusLabels[$statusKey],
                        'color' => $statusColors[$statusKey],
                        'icon' => $statusIcons[$statusKey],
                    ];
                @endphp
                <a href="{{ route('warehouse.index', ['status' => $statusKey]) }}"
                   class="relative flex items-center gap-2 px-5 py-4 text-sm font-medium whitespace-nowrap transition-colors
                   {{ $currentStatus === $statusKey
                       ? 'text-' . $tab['color'] . '-600 border-b-2 border-' . $tab['color'] . '-600'
                       : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                    <svg class="w-5 h-5 {{ $currentStatus === $statusKey ? 'text-' . $tab['color'] . '-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $tab['icon'] !!}</svg>
                    {{ $tab['label'] }}
                    <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 text-xs font-bold rounded-full
                        {{ $currentStatus === $statusKey
                            ? 'bg-' . $tab['color'] . '-100 text-' . $tab['color'] . '-700'
                            : 'bg-gray-100 text-gray-600' }}">
                        {{ $statusCounts[$statusKey] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>

        <!-- Search Bar -->
        <div class="p-4 border-b border-gray-100">
            <form action="{{ route('warehouse.index') }}" method="GET" class="flex items-center gap-3">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <div class="relative flex-1">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجو شماره سفارش، نام مشتری، موبایل، کد رهگیری..."
                           class="w-full pr-10 pl-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                </div>
                <button type="submit" class="px-4 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-medium">جستجو</button>
                @if($search)
                <a href="{{ route('warehouse.index', ['status' => $currentStatus]) }}" class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">پاک کردن</a>
                @endif
            </form>
        </div>

        {{-- PENDING STATUS: Single Column Layout --}}
        @if($currentStatus === 'pending')
        <div class="p-5 space-y-4">
            @forelse($orders as $order)
            @php
                $shippingTypeModel = $order->shipping_type ? $shippingTypes->firstWhere('slug', $order->shipping_type) : null;
                $shippingLabel = $shippingTypeModel ? $shippingTypeModel->name : ($order->shipping_type ?: '—');
                $isPeyk = $order->shipping_type && (str_contains(mb_strtolower($order->shipping_type), 'courier') || str_contains($shippingLabel, 'پیک'));
                $isExpired = $order->is_timer_expired;
                $remaining = $order->timer_remaining_seconds;
                $totalSeconds = ($order->timer_deadline && $shippingTypeModel) ? $shippingTypeModel->timer_minutes * 60 : 0;
                $timerPercent = $totalSeconds > 0 ? max(0, min(100, ($remaining / $totalSeconds) * 100)) : 0;
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden" data-order-id="{{ $order->id }}">
                <div class="flex flex-col lg:flex-row">
                    {{-- Right Side: Order Info --}}
                    <div class="lg:w-5/12 p-5 lg:border-l border-b lg:border-b-0 border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-bold text-gray-800" dir="ltr">{{ $order->order_number }}</span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg border {{ $isPeyk ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-sky-50 text-sky-700 border-sky-200' }}">
                                @if($isPeyk)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                @endif
                                {{ $shippingLabel }}
                            </span>
                        </div>
                        <p class="text-base font-semibold text-gray-900">{{ $order->customer_name }}</p>
                        @if($order->customer_mobile)
                        <div class="flex items-center gap-2 mt-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span class="text-sm text-gray-500" dir="ltr">{{ $order->customer_mobile }}</span>
                        </div>
                        @endif
                        <div class="flex items-center gap-2 mt-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</span>
                        </div>
                    </div>

                    {{-- Left Side: Products --}}
                    <div class="lg:w-7/12 p-5">
                        @if($order->items->count() > 0)
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="text-right py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200">#</th>
                                        <th class="text-right py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200">نام محصول</th>
                                        <th class="text-center py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200 w-24">تعداد</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($order->items as $index => $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2.5 px-4 text-gray-400 text-xs">{{ $index + 1 }}</td>
                                        <td class="py-2.5 px-4 text-gray-800">{{ $item->product_name }}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 bg-brand-50 text-brand-700 rounded-lg text-xs font-bold">{{ $item->quantity }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-sm text-gray-400">محصولی ثبت نشده</p>
                        @endif
                    </div>
                </div>

                {{-- Bottom: Timer + Actions --}}
                <div class="border-t border-gray-100 px-5 py-3">
                    <div class="flex items-center justify-between gap-4">
                        {{-- Countdown Timer --}}
                        <div class="flex items-center gap-3 flex-1">
                            <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-red-50 border border-red-200">
                                <svg class="w-5 h-5 text-red-500 {{ !$isExpired && $remaining > 0 ? 'animate-pulse' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="timer-display text-lg font-bold tabular-nums text-red-600" dir="ltr"
                                      data-remaining="{{ $remaining }}"
                                      data-expired="{{ $isExpired ? 'true' : 'false' }}"
                                      data-total="{{ $totalSeconds }}">
                                    @if($remaining > 0)
                                        {{ sprintf('%02d:%02d:%02d', intdiv($remaining, 3600), intdiv($remaining % 3600, 60), $remaining % 60) }}
                                    @elseif($order->timer_deadline)
                                        منقضی!
                                    @else
                                        --:--
                                    @endif
                                </span>
                            </div>
                            <div class="flex-1 hidden sm:block">
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="timer-bar h-2 rounded-full transition-all duration-1000 bg-red-500"
                                         style="width: {{ $isExpired ? '100' : $timerPercent }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        @canany(['manage-warehouse', 'manage-permissions'])
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="openSupplyModal({{ $order->id }}, {{ json_encode($order->items->map(fn($i) => ['id' => $i->id, 'name' => $i->product_name, 'qty' => $i->quantity])) }})"
                               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl hover:bg-amber-100 transition-colors text-sm font-medium whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                محدودیت تامین
                            </button>
                            <a href="{{ route('warehouse.print.invoice', $order) }}" target="_blank"
                               class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-colors text-sm font-medium whitespace-nowrap">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                پرینت و آماده‌سازی
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        </div>
                        @endcanany
                    </div>
                </div>
            </div>
            @empty
            <div class="py-16 text-center text-gray-400">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="font-medium text-gray-500">سفارشی در صف وجود ندارد</p>
                <p class="text-sm mt-1">سفارشات جدید از بخش «سفارش جدید» قابل ثبت هستند</p>
            </div>
            @endforelse
        </div>

        {{-- SUPPLY WAIT STATUS: Single Column with supply countdown --}}
        @elseif($currentStatus === 'supply_wait')
        <div class="p-5 space-y-4">
            @forelse($orders as $order)
            @php
                $supplyRemaining = $order->supply_deadline ? max(0, (int) now()->diffInSeconds($order->supply_deadline, false)) : 0;
                $supplyExpired = $order->supply_deadline && $order->supply_deadline->isPast();
                $unavailableItems = $order->items->where('is_unavailable', true);
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden" data-order-id="{{ $order->id }}">
                <div class="flex flex-col lg:flex-row">
                    {{-- Right Side: Order Info --}}
                    <div class="lg:w-5/12 p-5 lg:border-l border-b lg:border-b-0 border-gray-100">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-bold text-gray-800" dir="ltr">{{ $order->order_number }}</span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-50 text-amber-700 border border-amber-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                در انتظار تامین
                            </span>
                        </div>
                        <p class="text-base font-semibold text-gray-900">{{ $order->customer_name }}</p>
                        @if($order->customer_mobile)
                        <div class="flex items-center gap-2 mt-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span class="text-sm text-gray-500" dir="ltr">{{ $order->customer_mobile }}</span>
                        </div>
                        @endif
                        <div class="flex items-center gap-2 mt-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</span>
                        </div>
                    </div>

                    {{-- Left Side: Products (highlight unavailable) --}}
                    <div class="lg:w-7/12 p-5">
                        @if($order->items->count() > 0)
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="text-right py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200">#</th>
                                        <th class="text-right py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200">نام محصول</th>
                                        <th class="text-center py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200 w-24">تعداد</th>
                                        <th class="text-center py-2.5 px-4 text-xs font-semibold text-gray-600 border-b border-gray-200 w-20">وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($order->items as $index => $item)
                                    <tr class="{{ $item->is_unavailable ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                                        <td class="py-2.5 px-4 text-gray-400 text-xs">{{ $index + 1 }}</td>
                                        <td class="py-2.5 px-4 {{ $item->is_unavailable ? 'text-red-700 font-medium' : 'text-gray-800' }}">{{ $item->product_name }}</td>
                                        <td class="py-2.5 px-4 text-center">
                                            <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 {{ $item->is_unavailable ? 'bg-red-100 text-red-700' : 'bg-brand-50 text-brand-700' }} rounded-lg text-xs font-bold">{{ $item->quantity }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-center">
                                            @if($item->is_unavailable)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-600 rounded text-[10px] font-bold">ناموجود</span>
                                            @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-600 rounded text-[10px] font-bold">موجود</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Bottom: Supply Countdown + Action --}}
                <div class="border-t border-gray-100 px-5 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="flex items-center gap-2 px-4 py-2 rounded-xl {{ $supplyExpired ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200' }}">
                                <svg class="w-5 h-5 {{ $supplyExpired ? 'text-green-500' : 'text-amber-500 animate-pulse' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="timer-display text-lg font-bold tabular-nums {{ $supplyExpired ? 'text-green-600' : 'text-amber-600' }}" dir="ltr"
                                      data-remaining="{{ $supplyRemaining }}"
                                      data-expired="{{ $supplyExpired ? 'true' : 'false' }}"
                                      data-total="{{ $supplyRemaining }}">
                                    @if($supplyRemaining > 0)
                                        {{ sprintf('%02d:%02d:%02d', intdiv($supplyRemaining, 3600), intdiv($supplyRemaining % 3600, 60), $supplyRemaining % 60) }}
                                    @elseif($order->supply_deadline)
                                        زمان تامین رسیده!
                                    @else
                                        --:--
                                    @endif
                                </span>
                            </div>
                            @if($supplyExpired)
                            <span class="text-sm text-green-600 font-medium">آماده بازگشت به صف</span>
                            @else
                            <span class="text-sm text-amber-600">تامین تا: {{ $order->supply_deadline ? \Morilog\Jalali\Jalalian::fromCarbon($order->supply_deadline)->format('Y/m/d H:i') : '--' }}</span>
                            @endif
                        </div>

                        @canany(['manage-warehouse', 'manage-permissions'])
                        <form action="{{ route('warehouse.status', $order) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-colors text-sm font-medium whitespace-nowrap"
                                    onclick="return confirm('بازگشت سفارش به صف پردازش؟')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                بازگشت به صف
                            </button>
                        </form>
                        @endcanany
                    </div>
                </div>
            </div>
            @empty
            <div class="py-16 text-center text-gray-400">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <p class="font-medium text-gray-500">سفارشی در انتظار تامین وجود ندارد</p>
            </div>
            @endforelse
        </div>

        {{-- OTHER STATUSES: Table Layout --}}
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شماره سفارش</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع ارسال</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کد رهگیری</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ ثبت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-medium text-brand-600 text-sm" dir="ltr">{{ $order->order_number }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <div class="font-medium text-gray-900 text-sm">{{ $order->customer_name }}</div>
                                @if($order->customer_mobile)
                                <div class="text-xs text-gray-500 mt-0.5" dir="ltr">{{ $order->customer_mobile }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($order->shipping_type)
                                @php
                                    $shippingTypeModel = $shippingTypes->firstWhere('slug', $order->shipping_type);
                                    $shippingLabel = $shippingTypeModel ? $shippingTypeModel->name : $order->shipping_type;
                                    $isPeyk = str_contains(mb_strtolower($order->shipping_type), 'peyk') || str_contains($shippingLabel, 'پیک');
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full {{ $isPeyk ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $shippingLabel }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400">--</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600" dir="ltr">
                            {{ $order->tracking_code ?? '--' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('warehouse.show', $order) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="مشاهده">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @canany(['manage-warehouse', 'manage-permissions'])
                                <a href="{{ route('warehouse.edit', $order) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="ویرایش">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @php
                                    $nextStatus = \Modules\Warehouse\Models\WarehouseOrder::nextStatus($order->status);
                                    $nextLabel = $nextStatus ? $statusLabels[$nextStatus] : null;
                                    $nextColor = $nextStatus ? $statusColors[$nextStatus] : null;
                                @endphp
                                @if($nextStatus)
                                <form action="{{ route('warehouse.status', $order) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <button type="submit" class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg" title="انتقال به: {{ $nextLabel }}" onclick="return confirm('انتقال به وضعیت «{{ $nextLabel }}»؟')">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </button>
                                </form>
                                @endif
                                @endcanany
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p class="font-medium">سفارشی در این وضعیت وجود ندارد</p>
                            <p class="text-sm mt-1">سفارشات جدید از بخش «سفارش جدید» قابل ثبت هستند</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @if($orders->hasPages())
    <div class="flex justify-center">{{ $orders->links() }}</div>
    @endif
</div>

{{-- Supply Limitation Modal --}}
@canany(['manage-warehouse', 'manage-permissions'])
<div id="supplyModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="closeSupplyModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg relative" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">محدودیت در تامین سفارش</h3>
                <p class="text-sm text-gray-500 mt-1">محصولات ناموجود و زمان تامین را مشخص کنید</p>
            </div>
            <form id="supplyForm" method="POST">
                @csrf
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">محصولات ناموجود را انتخاب کنید:</label>
                        <div id="supplyItemsList" class="space-y-2 max-h-48 overflow-y-auto"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ و ساعت تامین:</label>
                        <input type="datetime-local" name="supply_deadline" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm" dir="ltr">
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex items-center gap-3 justify-end">
                    <button type="button" onclick="closeSupplyModal()" class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">انصراف</button>
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium">ثبت محدودیت تامین</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcanany

@push('scripts')
<script>
function openSupplyModal(orderId, items) {
    var modal = document.getElementById('supplyModal');
    var form = document.getElementById('supplyForm');
    var list = document.getElementById('supplyItemsList');

    form.action = '/warehouse/' + orderId + '/supply-wait';
    list.innerHTML = '';

    items.forEach(function(item) {
        var div = document.createElement('div');
        div.className = 'flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg';
        div.innerHTML = '<input type="checkbox" name="unavailable_items[]" value="' + item.id + '" class="w-4 h-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500">' +
            '<span class="text-sm text-gray-800 flex-1">' + item.name + '</span>' +
            '<span class="text-xs text-gray-500">تعداد: ' + item.qty + '</span>';
        list.appendChild(div);
    });

    modal.classList.remove('hidden');
}

function closeSupplyModal() {
    document.getElementById('supplyModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    var timerElements = document.querySelectorAll('.timer-display');

    timerElements.forEach(function(el) {
        var remaining = parseInt(el.dataset.remaining, 10);
        if (isNaN(remaining) || remaining <= 0) return;

        var card = el.closest('[data-order-id]');
        var totalSeconds = parseInt(el.dataset.total, 10) || 1;

        var interval = setInterval(function() {
            remaining--;

            if (remaining <= 0) {
                clearInterval(interval);
                el.textContent = 'منقضی!';
                return;
            }

            var hours = Math.floor(remaining / 3600);
            var minutes = Math.floor((remaining % 3600) / 60);
            var seconds = remaining % 60;
            var pad = function(n) { return n.toString().padStart(2, '0'); };
            el.textContent = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);

            if (card) {
                var bar = card.querySelector('.timer-bar');
                if (bar) {
                    var percent = Math.max(0, Math.min(100, (remaining / totalSeconds) * 100));
                    bar.style.width = percent + '%';
                }
            }
        }, 1000);
    });
});
</script>
@endpush
@endsection
