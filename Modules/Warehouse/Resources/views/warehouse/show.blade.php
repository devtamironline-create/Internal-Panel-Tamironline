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
            <a href="{{ route('warehouse.print.invoice', $order) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                پرینت
            </a>
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
    <div class="bg-white rounded-xl shadow-sm p-6 overflow-x-auto">
        <div class="flex items-center justify-between min-w-[600px]">
            @foreach($allStatuses as $index => $status)
                @php
                    $isDone = $index <= $currentIndex;
                    $isCurrent = $index === $currentIndex;
                    $color = $statusColors[$status];
                @endphp
                <div class="flex flex-col items-center flex-1 {{ !$loop->last ? 'relative' : '' }}">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold z-10
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
                        <div class="absolute top-5 h-0.5 {{ $index < $currentIndex ? 'bg-green-300' : 'bg-gray-200' }}" style="right: 50%; left: -50%; z-index: 0;"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Order Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Order Info -->
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
                @if($order->shipping_type)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">نوع ارسال</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->shipping_type }}</dd>
                </div>
                @endif
                @if($order->tracking_code)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">کد رهگیری</dt>
                    <dd class="text-sm font-medium text-brand-600" dir="ltr">{{ $order->tracking_code }}</dd>
                </div>
                @endif
                @if($order->barcode)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">بارکد</dt>
                    <dd class="text-sm font-medium text-gray-900" dir="ltr">{{ $order->barcode }}</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ ثبت</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->created_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @if($order->printed_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ پرینت</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->printed_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @endif
                @if($order->packed_at)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تاریخ بسته‌بندی</dt>
                    <dd class="text-sm text-gray-900">{{ \Morilog\Jalali\Jalalian::fromCarbon($order->packed_at)->format('Y/m/d H:i') }}</dd>
                </div>
                @endif
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

        <!-- Supplementary Info -->
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
                @if($order->total_weight)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">وزن کل (سیستمی)</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->total_weight }} kg</dd>
                </div>
                @endif
                @if($order->actual_weight)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">وزن واقعی</dt>
                    <dd class="text-sm font-medium {{ $order->weight_verified ? 'text-green-600' : 'text-red-600' }}">{{ $order->actual_weight }} kg</dd>
                </div>
                @endif
                @if($order->weight_verified !== null)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تایید وزن</dt>
                    <dd>
                        @if($order->weight_verified)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                تایید شده
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                تایید نشده
                            </span>
                        @endif
                    </dd>
                </div>
                @endif
                @if($order->driver_name)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">نام پیک</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $order->driver_name }}</dd>
                </div>
                @endif
                @if($order->driver_phone)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">تلفن پیک</dt>
                    <dd class="text-sm text-gray-900" dir="ltr">{{ $order->driver_phone }}</dd>
                </div>
                @endif
                @if($order->description)
                <div>
                    <dt class="text-sm text-gray-500 mb-1">توضیحات</dt>
                    <dd class="text-sm text-gray-900 bg-gray-50 rounded-lg p-3 whitespace-pre-line">{{ $order->description }}</dd>
                </div>
                @endif
                @if($order->notes)
                <div>
                    <dt class="text-sm text-gray-500 mb-1">یادداشت داخلی</dt>
                    <dd class="text-sm text-gray-900 bg-yellow-50 rounded-lg p-3 border border-yellow-100 whitespace-pre-line">{{ $order->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Order Items -->
    @if($order->items->count() > 0)
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">محصولات سفارش ({{ $order->items->count() }} قلم)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ردیف</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام محصول</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">بارکد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تعداد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وزن</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">قیمت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسکن</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->items as $index => $item)
                    <tr class="hover:bg-gray-50 {{ $item->scanned ? 'bg-green-50' : '' }}">
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $item->product_name }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600" dir="ltr">{{ $item->product_sku ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600" dir="ltr">{{ $item->product_barcode ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-900 font-medium">{{ $item->quantity }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $item->weight ? $item->weight . ' kg' : '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $item->price ? number_format($item->price) . ' تومان' : '—' }}</td>
                        <td class="px-6 py-3">
                            @if($item->scanned)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    اسکن شده
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 rounded-full">در انتظار</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Status Change Actions -->
    @canany(['manage-warehouse', 'manage-permissions'])
    @php
        $nextStatus = \Modules\Warehouse\Models\WarehouseOrder::nextStatus($order->status);
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
