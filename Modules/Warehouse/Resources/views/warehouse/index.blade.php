@extends('layouts.admin')
@section('page-title', 'مدیریت انبار')
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
            <h1 class="text-xl font-bold text-gray-900">مدیریت انبار</h1>
            <p class="text-gray-600 mt-1">جرنی سفارشات انبار</p>
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

        {{-- PENDING STATUS: Card Layout --}}
        @if($currentStatus === 'pending')
        <div class="p-4">
            @forelse($orders as $order)
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4 hover:shadow-md transition-shadow" data-order-id="{{ $order->id }}">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    {{-- Order Info --}}
                    <div class="flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-base font-bold text-brand-600" dir="ltr">{{ $order->order_number }}</span>
                            <span class="text-sm font-medium text-gray-900">{{ $order->customer_name }}</span>
                            @if($order->customer_mobile)
                            <span class="text-xs text-gray-500" dir="ltr">{{ $order->customer_mobile }}</span>
                            @endif
                            @if($order->shipping_type)
                                @php
                                    $shippingTypeModel = $shippingTypes->firstWhere('slug', $order->shipping_type);
                                    $shippingLabel = $shippingTypeModel ? $shippingTypeModel->name : $order->shipping_type;
                                    $isPeyk = str_contains(mb_strtolower($order->shipping_type), 'peyk') || str_contains($shippingLabel, 'پیک');
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full {{ $isPeyk ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">
                                    @if($isPeyk)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                    @else
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    @endif
                                    {{ $shippingLabel }}
                                </span>
                            @endif
                        </div>

                        {{-- Timer --}}
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 {{ $order->is_timer_expired ? 'text-red-500' : 'text-blue-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="timer-display text-sm font-bold {{ $order->is_timer_expired ? 'text-red-600' : 'text-gray-700' }}"
                                  data-remaining="{{ $order->timer_remaining_seconds }}"
                                  data-expired="{{ $order->is_timer_expired ? 'true' : 'false' }}">
                                @if($order->timer_remaining_seconds > 0)
                                    {{ sprintf('%02d:%02d:%02d', intdiv($order->timer_remaining_seconds, 3600), intdiv($order->timer_remaining_seconds % 3600, 60), $order->timer_remaining_seconds % 60) }} باقی‌مانده
                                @elseif($order->timer_deadline)
                                    منقضی شده
                                @else
                                    بدون تایمر
                                @endif
                            </span>
                        </div>

                        {{-- Products List --}}
                        @if($order->items->count() > 0)
                        <div class="mt-3">
                            <p class="text-xs font-medium text-gray-500 mb-2">محصولات ({{ $order->items->count() }} قلم):</p>
                            <div class="space-y-1.5">
                                @foreach($order->items as $item)
                                <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 flex items-center justify-center bg-gray-200 text-gray-600 rounded text-xs font-bold">{{ $item->quantity }}</span>
                                        <span class="text-sm text-gray-800">{{ $item->product_name }}</span>
                                    </div>
                                    @if($item->product_sku)
                                    <span class="text-xs text-gray-400" dir="ltr">{{ $item->product_sku }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-row lg:flex-col items-center gap-2 shrink-0">
                        <a href="{{ route('warehouse.print.invoice', $order) }}" target="_blank"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            پرینت فاکتور
                        </a>
                        <a href="{{ route('warehouse.show', $order) }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            مشاهده
                        </a>
                        @canany(['manage-warehouse', 'manage-permissions'])
                        <form action="{{ route('warehouse.status', $order) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="preparing">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium" onclick="return confirm('انتقال به وضعیت «در حال آماده‌سازی»؟')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                شروع آماده‌سازی
                            </button>
                        </form>
                        @endcanany
                    </div>
                </div>
            </div>
            @empty
            <div class="py-12 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="font-medium">سفارشی در صف وجود ندارد</p>
                <p class="text-sm mt-1">سفارشات جدید از بخش «سفارش جدید» قابل ثبت هستند</p>
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

{{-- Timer Countdown Script for Pending Status --}}
@if($currentStatus === 'pending')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const timerElements = document.querySelectorAll('.timer-display');

    timerElements.forEach(function(el) {
        let remaining = parseInt(el.dataset.remaining, 10);
        const isExpired = el.dataset.expired === 'true';

        if (isNaN(remaining) || remaining <= 0) {
            if (el.dataset.remaining === '0' && !isExpired) return;
            return;
        }

        const interval = setInterval(function() {
            remaining--;

            if (remaining <= 0) {
                clearInterval(interval);
                el.textContent = 'منقضی شده';
                el.classList.remove('text-gray-700');
                el.classList.add('text-red-600');

                const icon = el.previousElementSibling;
                if (icon) {
                    icon.classList.remove('text-blue-500');
                    icon.classList.add('text-red-500');
                }
                return;
            }

            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            const pad = function(n) { return n.toString().padStart(2, '0'); };
            el.textContent = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds) + ' باقی‌مانده';

            // Turn red when less than 5 minutes remain
            if (remaining <= 300) {
                el.classList.remove('text-gray-700');
                el.classList.add('text-red-600');
                const icon = el.previousElementSibling;
                if (icon) {
                    icon.classList.remove('text-blue-500');
                    icon.classList.add('text-red-500');
                }
            }
        }, 1000);
    });
});
</script>
@endif
@endsection
