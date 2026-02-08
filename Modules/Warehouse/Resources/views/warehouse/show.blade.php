@extends('layouts.admin')
@section('page-title', 'جزئیات سفارش')
@section('main')
@php
    $statusLabels = \Modules\Warehouse\Models\WarehouseOrder::statusLabels();
    $statusColors = \Modules\Warehouse\Models\WarehouseOrder::statusColors();
    $allStatuses = \Modules\Warehouse\Models\WarehouseOrder::$statuses;
    $currentIndex = array_search($order->status, $allStatuses);
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index', ['status' => $order->status]) }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">جزئیات سفارش</h1>
                <p class="text-gray-600 mt-1">شماره سفارش: <span dir="ltr" class="text-brand-600 font-medium">{{ $order->order_number }}</span></p>
            </div>
        </div>
        @canany(['manage-warehouse', 'manage-permissions'])
        <div class="flex gap-2">
            <a href="{{ route('warehouse.edit', $order) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
            <form action="{{ route('warehouse.destroy', $order) }}" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این سفارش اطمینان دارید؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    حذف
                </button>
            </form>
        </div>
        @endcanany
    </div>

    <!-- Progress Steps -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            @foreach($allStatuses as $index => $status)
                @php
                    $isDone = $index <= $currentIndex;
                    $isCurrent = $index === $currentIndex;
                    $color = $statusColors[$status];
                @endphp
                <div class="flex flex-col items-center flex-1 {{ !$loop->last ? 'relative' : '' }}">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold
                        {{ $isDone ? 'bg-' . $color . '-100 text-' . $color . '-700 ring-2 ring-' . $color . '-400' : 'bg-gray-100 text-gray-400' }}
                        {{ $isCurrent ? 'ring-4 ring-' . $color . '-200' : '' }}">
                        @if($isDone && !$isCurrent)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <span class="text-xs mt-2 font-medium {{ $isDone ? 'text-' . $color . '-700' : 'text-gray-400' }}">{{ $statusLabels[$status] }}</span>
                    @if(!$loop->last)
                        <div class="absolute top-5 right-1/2 w-full h-0.5 -z-0 {{ $index < $currentIndex ? 'bg-green-300' : 'bg-gray-200' }}" style="right: 50%; width: 100%;"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Order Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">اطلاعات سفارش</h2>
            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">وضعیت</dt>
                    <dd><span class="px-2.5 py-1 text-xs font-medium bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800 rounded-full">{{ $order->status_label }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">نام مشتری</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->customer_name }}</dd>
                </div>
                @if($order->customer_mobile)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">موبایل</dt>
                    <dd class="text-sm text-gray-900" dir="ltr">{{ $order->customer_mobile }}</dd>
                </div>
                @endif
                @if($order->tracking_code)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">کد رهگیری</dt>
                    <dd class="text-sm font-medium text-brand-600" dir="ltr">{{ $order->tracking_code }}</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ ثبت</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @if($order->shipped_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ ارسال</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->shipped_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @endif
                @if($order->delivered_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ تحویل</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->delivered_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">اطلاعات تکمیلی</h2>
            <dl class="space-y-4">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">ثبت‌کننده</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->creator?->full_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">مسئول انبار</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->assignee?->full_name ?? '—' }}</dd>
                </div>
                @if($order->description)
                <div>
                    <dt class="text-sm text-gray-500 mb-1">توضیحات</dt>
                    <dd class="text-sm text-gray-900 bg-gray-50 rounded-lg p-3">{{ $order->description }}</dd>
                </div>
                @endif
                @if($order->notes)
                <div>
                    <dt class="text-sm text-gray-500 mb-1">یادداشت داخلی</dt>
                    <dd class="text-sm text-gray-900 bg-yellow-50 rounded-lg p-3 border border-yellow-100">{{ $order->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Status Change Actions -->
    @canany(['manage-warehouse', 'manage-permissions'])
    @php
        $nextStatuses = [
            'processing' => 'preparing',
            'preparing' => 'ready_to_ship',
            'ready_to_ship' => 'shipped',
            'shipped' => 'delivered',
        ];
        $nextStatus = $nextStatuses[$order->status] ?? null;
        $nextLabel = $nextStatus ? $statusLabels[$nextStatus] : null;
        $nextColor = $nextStatus ? $statusColors[$nextStatus] : null;
    @endphp
    @if($nextStatus)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">تغییر وضعیت</h2>
        <form action="{{ route('warehouse.status', $order) }}" method="POST" class="flex items-center gap-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextStatus }}">
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-{{ $nextColor }}-600 text-white rounded-lg hover:bg-{{ $nextColor }}-700 font-medium" onclick="return confirm('انتقال به وضعیت «{{ $nextLabel }}»؟')">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                انتقال به «{{ $nextLabel }}»
            </button>
        </form>
    </div>
    @endif
    @endcanany
</div>
@endsection
