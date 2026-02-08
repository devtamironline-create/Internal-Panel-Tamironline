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

    @if($order->status === 'preparing')
    <!-- Preparing Stage: Scan Products + Verify Weight -->
    <div class="bg-white rounded-xl shadow-sm" x-data="preparingStation()" x-init="init()">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">اسکن محصولات و تایید وزن</h2>
            <p class="text-sm text-gray-500 mt-1">ابتدا بارکد تمام محصولات را اسکن کنید، سپس وزن بسته را وارد نمایید.</p>
        </div>

        <div class="p-6 space-y-6">
            <!-- Step 1: Product Scanning -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-700">مرحله ۱: اسکن محصولات</h3>
                    <span class="text-sm font-medium" :class="allScanned ? 'text-green-600' : 'text-orange-600'">
                        <span x-text="scannedCount"></span> / <span x-text="totalCount"></span> اسکن شده
                    </span>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-green-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + (totalCount > 0 ? (scannedCount / totalCount * 100) : 0) + '%'"></div>
                </div>

                <!-- Scan Input -->
                <div class="relative mb-4" x-show="!allScanned">
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    <input type="text"
                           x-ref="productScanInput"
                           x-model="productBarcode"
                           @keydown.enter.prevent="scanProduct()"
                           :disabled="scanning"
                           placeholder="بارکد محصول را اسکن کنید..."
                           class="w-full pr-12 pl-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-base font-medium text-center transition-colors"
                           :class="scanning ? 'bg-gray-100 cursor-not-allowed' : ''">
                </div>

                <!-- All Scanned Badge -->
                <div x-show="allScanned" x-cloak class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-xl mb-4">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-medium text-green-700">تمام محصولات اسکن شدند</span>
                </div>

                <!-- Scan Message -->
                <div x-show="scanMessage" x-cloak class="p-3 rounded-lg text-sm mb-3" :class="scanError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'">
                    <span x-text="scanMessage"></span>
                </div>

                <!-- Items List with Scan Status -->
                <div class="space-y-1.5">
                    <template x-for="item in items" :key="item.id">
                        <div class="flex items-center justify-between p-2.5 rounded-lg border text-sm transition-colors"
                             :class="item.scanned ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 flex items-center justify-center rounded text-xs font-bold"
                                      :class="item.scanned ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      x-text="item.quantity"></span>
                                <span class="text-gray-900" x-text="item.product_name"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400" dir="ltr" x-text="item.product_sku || item.product_barcode || ''"></span>
                                <template x-if="item.scanned">
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="!item.scanned">
                                    <span class="text-xs text-gray-400">در انتظار</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Step 2: Weight Verification -->
            <div class="border-t pt-6" :class="allScanned ? '' : 'opacity-50 pointer-events-none'">
                <h3 class="text-sm font-bold text-gray-700 mb-3">مرحله ۲: تایید وزن</h3>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">وزن واقعی بسته (کیلوگرم)</label>
                        <input type="number" x-model="actualWeight" step="0.01" min="0" dir="ltr" placeholder="0.00"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 text-lg font-medium text-center">
                    </div>
                    <button @click="verifyWeight()" :disabled="!actualWeight || verifying"
                            class="px-6 py-3 bg-cyan-600 text-white rounded-xl hover:bg-cyan-700 font-medium text-sm disabled:opacity-50 transition-colors">
                        <span x-show="!verifying">تایید وزن</span>
                        <span x-show="verifying">در حال بررسی...</span>
                    </button>
                </div>
                @if($order->total_weight)
                <p class="text-xs text-gray-400 mt-2">وزن سیستمی: {{ $order->total_weight }} kg</p>
                @endif

                <!-- Weight Result -->
                <div x-show="weightMessage" x-cloak class="mt-3 p-4 rounded-xl text-sm" :class="weightVerified ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                    <div class="flex items-center gap-2">
                        <template x-if="weightVerified">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        <template x-if="!weightVerified && weightMessage">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </template>
                        <span :class="weightVerified ? 'text-green-700 font-medium' : 'text-red-700 font-medium'" x-text="weightMessage"></span>
                    </div>
                    <!-- Force verify option on weight mismatch -->
                    <template x-if="!weightVerified && weightMessage && !weightForced">
                        <div class="mt-3 flex items-center gap-3">
                            <p class="text-xs text-red-600">اختلاف وزن: <span x-text="weightDiff"></span>%</p>
                            <button @click="forceVerify()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700 font-medium">
                                تایید دستی و ادامه
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    @elseif($nextStatus)
    <!-- Other statuses: simple status change button -->
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

@if($order->status === 'preparing')
@php
    $itemsJson = $order->items->map(function($item) {
        return [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'product_barcode' => $item->product_barcode,
            'product_sku' => $item->product_sku,
            'quantity' => $item->quantity,
            'weight' => $item->weight,
            'scanned' => (bool) $item->scanned,
        ];
    });
@endphp
@push('scripts')
<script>
function preparingStation() {
    return {
        orderId: {{ $order->id }},
        items: @json($itemsJson),
        productBarcode: '',
        scanning: false,
        scanMessage: '',
        scanError: false,
        actualWeight: '',
        verifying: false,
        weightMessage: '',
        weightVerified: false,
        weightForced: false,
        weightDiff: 0,

        get scannedCount() {
            return this.items.filter(i => i.scanned).length;
        },
        get totalCount() {
            return this.items.length;
        },
        get allScanned() {
            return this.totalCount > 0 && this.scannedCount >= this.totalCount;
        },

        init() {
            this.$nextTick(() => {
                if (this.$refs.productScanInput) this.$refs.productScanInput.focus();
            });
        },

        async scanProduct() {
            if (!this.productBarcode.trim() || this.scanning) return;
            this.scanning = true;
            this.scanMessage = '';
            this.scanError = false;

            try {
                const res = await fetch('{{ route("warehouse.packing.scan-product") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: this.orderId, barcode: this.productBarcode.trim() })
                });
                const data = await res.json();

                if (data.success) {
                    this.scanMessage = data.message;
                    this.scanError = false;
                    // Mark item as scanned in local state
                    if (data.item_id) {
                        const item = this.items.find(i => i.id === data.item_id);
                        if (item) item.scanned = true;
                    }
                    this.playBeep(true);
                } else {
                    this.scanMessage = data.message;
                    this.scanError = true;
                    this.playBeep(false);
                }
            } catch (e) {
                this.scanMessage = 'خطا در ارتباط با سرور';
                this.scanError = true;
                this.playBeep(false);
            }

            this.productBarcode = '';
            this.scanning = false;
            this.$nextTick(() => {
                if (this.$refs.productScanInput) this.$refs.productScanInput.focus();
            });
        },

        async verifyWeight() {
            if (!this.actualWeight || this.verifying) return;
            this.verifying = true;
            this.weightMessage = '';

            try {
                const res = await fetch('{{ route("warehouse.packing.verify-weight") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: this.orderId, actual_weight: parseFloat(this.actualWeight) })
                });
                const data = await res.json();

                this.weightMessage = data.message;
                this.weightVerified = data.verified || false;
                this.weightDiff = data.difference || 0;

                if (data.verified) {
                    this.playBeep(true);
                    setTimeout(() => { location.reload(); }, 1500);
                } else {
                    this.playBeep(false);
                }
            } catch (e) {
                this.weightMessage = 'خطا در ارتباط با سرور';
                this.weightVerified = false;
                this.playBeep(false);
            }

            this.verifying = false;
        },

        async forceVerify() {
            try {
                const res = await fetch('{{ route("warehouse.packing.force-verify") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: this.orderId })
                });
                const data = await res.json();

                if (data.success) {
                    this.weightMessage = data.message;
                    this.weightVerified = true;
                    this.weightForced = true;
                    this.playBeep(true);
                    setTimeout(() => { location.reload(); }, 1500);
                }
            } catch (e) {
                this.weightMessage = 'خطا در تایید دستی';
                this.playBeep(false);
            }
        },

        playBeep(success = true) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = success ? 800 : 300;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (success ? 0.15 : 0.4));
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + (success ? 0.15 : 0.4));
            } catch (e) {}
        }
    };
}
</script>
@endpush
@endif
@endsection
