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

    <!-- Order Barcode -->
    @if($order->barcode)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">بارکد سفارش</h2>
            <span class="text-sm text-gray-500" dir="ltr">{{ $order->barcode }}</span>
        </div>
        <div class="flex justify-center">
            <svg id="order-barcode"></svg>
        </div>
    </div>
    @endif

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
                @php
                    $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                    $shippingLines = $wcData['shipping_lines'] ?? [];
                    $wcShippingAddr = $wcData['shipping'] ?? [];
                    $wcBillingAddr = $wcData['billing'] ?? [];
                @endphp
                @if(!empty($shippingLines))
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <dt class="text-xs text-gray-400 mb-1">اطلاعات ارسال ووکامرس:</dt>
                    @foreach($shippingLines as $sl)
                    <dd class="text-xs text-gray-600 bg-gray-50 rounded p-2 mb-1" dir="ltr">
                        method_id: <strong>{{ $sl['method_id'] ?? '-' }}</strong><br>
                        method_title: <strong>{{ $sl['method_title'] ?? '-' }}</strong><br>
                        total: {{ $sl['total'] ?? '0' }}
                    </dd>
                    @endforeach
                </div>
                @endif
                @php
                    $addrState = ($wcShippingAddr['state'] ?? '') ?: ($wcBillingAddr['state'] ?? '');
                    $addrCity = ($wcShippingAddr['city'] ?? '') ?: ($wcBillingAddr['city'] ?? '');
                    $addrAddress = ($wcShippingAddr['address_1'] ?? '') ?: ($wcBillingAddr['address_1'] ?? '');
                    $fullAddr = implode('، ', array_filter([$addrState, $addrCity, $addrAddress]));
                    $currentPostcode = ($wcShippingAddr['postcode'] ?? '') ?: ($wcBillingAddr['postcode'] ?? '');
                @endphp
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">آدرس</dt>
                        <dd class="text-sm text-gray-900 text-left {{ $fullAddr ? '' : 'text-red-500' }}" style="max-width: 65%">{{ $fullAddr ?: 'ثبت نشده' }}</dd>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">کد پستی</dt>
                        <dd class="text-sm font-medium {{ $currentPostcode ? 'text-gray-900' : 'text-red-500' }}" dir="ltr">{{ $currentPostcode ?: 'ثبت نشده' }}</dd>
                    </div>
                </div>
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
                    <dd class="text-sm font-medium text-gray-900">{{ number_format($order->total_weight_grams) }}g</dd>
                </div>
                @endif
                @if($order->actual_weight)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">وزن واقعی</dt>
                    <dd class="text-sm font-medium {{ $order->weight_verified ? 'text-green-600' : 'text-red-600' }}">{{ number_format($order->actual_weight_grams) }}g</dd>
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
                @php
                    $selectedBox = $order->boxSize;
                @endphp
                @if($selectedBox)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">کارتن انتخابی</dt>
                    <dd class="text-sm font-medium text-gray-900">
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 bg-amber-50 border border-amber-200 rounded-lg">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            سایز {{ $selectedBox->name }} — {{ $selectedBox->dimensions_label }}cm — {{ $selectedBox->weight_label }}
                        </span>
                    </dd>
                </div>
                @if($order->total_weight_grams)
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">وزن کل + کارتن</dt>
                    <dd class="text-sm font-bold text-gray-900">{{ number_format($order->total_weight_grams + $selectedBox->weight) }}g</dd>
                </div>
                @endif
                @else
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">کارتن</dt>
                    <dd class="text-sm text-gray-400">انتخاب نشده</dd>
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

    <!-- Tapin Province/City Selection (for post orders) -->
    @if($order->shipping_type === 'post')
    @canany(['manage-warehouse', 'manage-permissions'])
    @php
        $tapinData = is_array($order->wc_order_data) ? ($order->wc_order_data['tapin'] ?? []) : [];
        $wcShipping = is_array($order->wc_order_data) ? ($order->wc_order_data['shipping'] ?? []) : [];
        $wcBilling = is_array($order->wc_order_data) ? ($order->wc_order_data['billing'] ?? []) : [];
        $wcState = ($wcShipping['state'] ?? '') ?: ($wcBilling['state'] ?? '');
        $wcCity = ($wcShipping['city'] ?? '') ?: ($wcBilling['city'] ?? '');
    @endphp
    <div class="bg-white rounded-xl shadow-sm p-6" x-data="tapinLocation()" x-init="init()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">استان و شهر تاپین</h2>
            @if($wcState || $wcCity)
            <span class="text-xs text-gray-400">ووکامرس: {{ $wcState }} — {{ $wcCity }}</span>
            @endif
        </div>

        @if(!empty($tapinData['province_name']))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><span class="font-medium">ذخیره شده:</span> {{ $tapinData['province_name'] }} — {{ $tapinData['city_name'] ?? '' }} <span class="text-green-500">(کد: {{ $tapinData['province_code'] ?? '?' }}/{{ $tapinData['city_code'] ?? '?' }})</span></span>
        </div>
        @endif

        <div class="space-y-4">
            <div x-show="loading" class="flex items-center gap-2 text-sm text-gray-500">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                در حال دریافت لیست استان‌ها...
            </div>

            <div x-show="error" x-cloak class="p-3 bg-red-50 text-red-700 rounded-lg text-sm" x-text="error"></div>

            <div x-show="!loading && provinces.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">استان</label>
                    <select x-model="selectedProvince" @change="onProvinceChange()"
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                        <option value="">انتخاب استان...</option>
                        <template x-for="p in provinces" :key="p.code">
                            <option :value="p.code" x-text="p.title" :selected="p.code == selectedProvince"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شهر</label>
                    <select x-model="selectedCity"
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm"
                            :disabled="!selectedProvince || cities.length === 0">
                        <option value="">انتخاب شهر...</option>
                        <template x-for="c in cities" :key="c.code">
                            <option :value="c.code" x-text="c.title" :selected="c.code == selectedCity"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div x-show="selectedProvince && selectedCity" x-cloak class="flex items-center gap-3">
                <button @click="save()" :disabled="saving"
                        class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 font-medium text-sm disabled:opacity-50 transition-colors">
                    <span x-show="!saving">ذخیره استان و شهر</span>
                    <span x-show="saving">در حال ذخیره...</span>
                </button>
                <span x-show="successMessage" x-cloak class="text-sm text-green-600 font-medium" x-text="successMessage"></span>
            </div>
        </div>
    </div>
    @endcanany
    @endif

    <!-- Address Edit Card -->
    @canany(['manage-warehouse', 'manage-permissions'])
    <div class="bg-white rounded-xl shadow-sm p-6" x-data="{
        addrState: '{{ addslashes($addrState) }}',
        addrCity: '{{ addslashes($addrCity) }}',
        addrAddress: '{{ addslashes($addrAddress) }}',
        postcode: '{{ $currentPostcode }}',
        savingAddr: false,
        savedAddr: false,
        addrError: '',
        savingPostcode: false,
        savedPostcode: false,
        postcodeError: ''
    }">
        <h2 class="text-lg font-bold text-gray-900 mb-4">ویرایش آدرس</h2>

        {{-- نمایش آدرس فعلی --}}
        <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600 mb-4">
            <span class="{{ $fullAddr ? '' : 'text-red-500' }}">{{ $fullAddr ?: 'آدرس ثبت نشده' }}</span>
            &nbsp;—&nbsp;
            <span class="text-gray-400">کد پستی:</span> <span dir="ltr" class="{{ $currentPostcode ? '' : 'text-red-500' }}">{{ $currentPostcode ?: 'ثبت نشده' }}</span>
        </div>

        <div class="space-y-4">
            {{-- استان و شهر --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">استان</label>
                    <input type="text" x-model="addrState" placeholder="استان"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">شهر</label>
                    <input type="text" x-model="addrCity" placeholder="شهر"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            {{-- آدرس کامل --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">آدرس کامل</label>
                <textarea x-model="addrAddress" placeholder="آدرس کامل" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>

            {{-- دکمه ذخیره آدرس --}}
            <div class="flex items-center gap-3">
                <button @click="
                    if (!addrAddress.trim()) { addrError = 'آدرس نمی‌تواند خالی باشد'; return; }
                    savingAddr = true; addrError = '';
                    fetch('/warehouse/{{ $order->id }}/save-address', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                        body: JSON.stringify({state: addrState, city: addrCity, address: addrAddress})
                    }).then(r => r.json()).then(d => {
                        savingAddr = false;
                        if (d.success) { savedAddr = true; setTimeout(() => location.reload(), 800); }
                        else { addrError = d.message || 'خطا'; }
                    }).catch(() => { savingAddr = false; addrError = 'خطا در ارتباط'; })
                " :disabled="savingAddr" type="button"
                   class="px-5 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50">
                    <span x-text="savingAddr ? 'در حال ذخیره...' : 'ذخیره آدرس'"></span>
                </button>
                <span x-show="savedAddr" x-cloak class="text-sm text-green-600 font-medium">ذخیره شد</span>
                <span x-show="addrError" x-cloak class="text-sm text-red-500" x-text="addrError"></span>
            </div>

            {{-- کد پستی --}}
            <div class="pt-4 border-t border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-1">کد پستی</label>
                <div class="flex items-center gap-3">
                    <input type="text" x-model="postcode" maxlength="10" dir="ltr" placeholder="کد پستی ۱۰ رقمی"
                           class="w-44 px-3 py-2 border border-gray-300 rounded-lg text-sm text-center font-medium focus:border-brand-500 focus:ring-brand-500">
                    <button @click="
                        if (!postcode || postcode.length < 10) { postcodeError = 'کد پستی باید ۱۰ رقمی باشد'; return; }
                        savingPostcode = true; postcodeError = '';
                        fetch('/warehouse/{{ $order->id }}/save-postal-code', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                            body: JSON.stringify({postal_code: postcode})
                        }).then(r => r.json()).then(d => {
                            savingPostcode = false;
                            if (d.success) { savedPostcode = true; setTimeout(() => location.reload(), 800); }
                            else { postcodeError = d.message || 'خطا'; }
                        }).catch(() => { savingPostcode = false; postcodeError = 'خطا در ارتباط'; })
                    " :disabled="savingPostcode" type="button"
                       class="px-5 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 disabled:opacity-50">
                        <span x-text="savingPostcode ? '...' : 'ذخیره کد پستی'"></span>
                    </button>
                    <span x-show="savedPostcode" x-cloak class="text-sm text-green-600 font-medium">ذخیره شد</span>
                    <span x-show="postcodeError" x-cloak class="text-sm text-red-500" x-text="postcodeError"></span>
                </div>
            </div>
        </div>
    </div>
    @endcanany

    <!-- Order Items -->
    @if($order->items->count() > 0)
    @php
        // پیدا کردن محصولات پکیج/باندل از بین آیتم‌های سفارش
        $itemProductIds = $order->items->pluck('wc_product_id')->filter()->unique()->toArray();
        $bundleProducts = [];
        if (!empty($itemProductIds)) {
            $bundleTypes = ['bundle', 'yith_bundle', 'woosb', 'grouped'];
            $bundles = \Modules\Warehouse\Models\WarehouseProduct::whereIn('wc_product_id', $itemProductIds)
                ->whereIn('type', $bundleTypes)
                ->with(['bundleItems.childProduct'])
                ->get()
                ->keyBy('wc_product_id');
            $bundleProducts = $bundles;
        }
    @endphp
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
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ابعاد (cm)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">قیمت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسکن</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->items as $index => $item)
                    @php
                        $isBundle = $item->wc_product_id && isset($bundleProducts[$item->wc_product_id]);
                        $bundleProduct = $isBundle ? $bundleProducts[$item->wc_product_id] : null;
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $item->scanned ? 'bg-green-50' : '' }}">
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">
                            {{ $item->product_name }}
                            @if($isBundle)
                                <span class="inline-flex items-center gap-1 mr-2 px-1.5 py-0.5 text-[10px] font-medium bg-purple-100 text-purple-700 rounded">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    پکیج
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-600" dir="ltr">{{ $item->product_sku ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600" dir="ltr">{{ $item->product_barcode ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-900 font-medium">{{ $item->quantity }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $item->weight ? number_format($item->weight_grams) . 'g' : '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600" dir="ltr">{{ ($item->length && $item->width && $item->height) ? "{$item->length}×{$item->width}×{$item->height}" : '—' }}</td>
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
                    {{-- نمایش زیرمجموعه‌های پکیج --}}
                    @if($isBundle && $bundleProduct->bundleItems->count() > 0)
                        @php
                            $bundleChildTotalWeight = 0;
                        @endphp
                        @foreach($bundleProduct->bundleItems as $bundleItem)
                            @if($bundleItem->childProduct && !$bundleItem->optional)
                                @php
                                    $childWeight = (float) $bundleItem->childProduct->weight;
                                    // هندل محصولات variable
                                    if ($childWeight == 0 && $bundleItem->childProduct->type === 'variable') {
                                        $firstVar = \Modules\Warehouse\Models\WarehouseProduct::where('parent_id', $bundleItem->childProduct->wc_product_id)
                                            ->where('type', 'variation')->where('weight', '>', 0)->first();
                                        if ($firstVar) $childWeight = (float) $firstVar->weight;
                                    }
                                    $childWeightGrams = \Modules\Warehouse\Models\WarehouseOrder::toGrams($childWeight);
                                    $childTotalWeight = $childWeightGrams * $bundleItem->default_quantity;
                                    $bundleChildTotalWeight += $childTotalWeight;
                                @endphp
                                <tr class="bg-purple-50/50">
                                    <td class="px-6 py-2 text-sm text-gray-400"></td>
                                    <td class="px-6 py-2 text-sm text-gray-600 pr-12">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                                            {{ $bundleItem->childProduct->name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2 text-sm text-gray-400" dir="ltr">{{ $bundleItem->childProduct->sku ?? '—' }}</td>
                                    <td class="px-6 py-2 text-sm text-gray-400" dir="ltr">—</td>
                                    <td class="px-6 py-2 text-sm text-gray-500">{{ $bundleItem->default_quantity }}</td>
                                    <td class="px-6 py-2 text-sm text-gray-500">
                                        @if($childWeightGrams > 0)
                                            {{ number_format($childWeightGrams) }}g
                                            @if($bundleItem->default_quantity > 1)
                                                <span class="text-gray-400 text-xs">({{ number_format($childTotalWeight) }}g)</span>
                                            @endif
                                        @else
                                            <span class="text-red-400">0g</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-2 text-sm text-gray-400" dir="ltr">
                                        @php
                                            $cl = (float) $bundleItem->childProduct->length;
                                            $cw = (float) $bundleItem->childProduct->width;
                                            $ch = (float) $bundleItem->childProduct->height;
                                        @endphp
                                        {{ ($cl && $cw && $ch) ? "{$cl}×{$cw}×{$ch}" : '—' }}
                                    </td>
                                    <td class="px-6 py-2 text-sm text-gray-400">—</td>
                                    <td class="px-6 py-2"></td>
                                </tr>
                            @endif
                        @endforeach
                        {{-- ردیف جمع وزن پکیج --}}
                        <tr class="bg-purple-50 border-b-2 border-purple-200">
                            <td class="px-6 py-2"></td>
                            <td class="px-6 py-2 text-xs font-bold text-purple-700 pr-12">
                                جمع وزن پکیج ({{ $bundleProduct->bundleItems->filter(fn($bi) => $bi->childProduct && !$bi->optional)->count() }} محصول)
                            </td>
                            <td class="px-6 py-2" colspan="3"></td>
                            <td class="px-6 py-2 text-xs font-bold text-purple-700">
                                {{ number_format($bundleChildTotalWeight) }}g
                                @if($item->weight_grams > 0 && $item->weight_grams != $bundleChildTotalWeight)
                                    <span class="text-red-500 mr-1">(ثبت شده: {{ number_format($item->weight_grams) }}g)</span>
                                @endif
                            </td>
                            <td class="px-6 py-2" colspan="3"></td>
                        </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Activity Log -->
    @if($order->logs->count() > 0)
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">تاریخچه فعالیت</h2>
        </div>
        <div class="p-6">
            <div class="relative">
                <div class="absolute top-0 bottom-0 right-4 w-0.5 bg-gray-200"></div>
                <div class="space-y-4">
                    @foreach($order->logs as $log)
                    <div class="relative flex gap-4 pr-2">
                        <div class="relative z-10 flex items-center justify-center w-5 h-5 mt-0.5 rounded-full bg-white ring-2 {{ str_replace('text-', 'ring-', $log->action_color) }}">
                            <div class="w-2 h-2 rounded-full {{ str_replace('text-', 'bg-', $log->action_color) }}"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium {{ $log->action_color }}">{{ $log->action_label }}</span>
                                <span class="text-xs text-gray-400">{{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i') }}</span>
                                @if($log->user)
                                <span class="text-xs text-gray-400">— {{ $log->user->name }}</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 mt-0.5">{{ $log->message }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
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

    @if($order->status === 'packed')
    <!-- Packed Stage: Exit Scan -->
    <div class="bg-white rounded-xl shadow-sm" x-data="exitScanStation()" x-init="init()">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">اسکن خروج سفارش</h2>
            <p class="text-sm text-gray-500 mt-1">بارکد سفارش را اسکن کنید تا تایید خروج ثبت شود.</p>
        </div>

        <div class="p-6 space-y-6">
            <!-- Barcode Scan -->
            <div>
                @if($order->exit_scanned_at)
                <!-- Already scanned - show verified badge -->
                <div class="flex items-center gap-2 p-4 bg-green-50 border border-green-200 rounded-xl">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-medium text-green-700">
                        اسکن خروج تایید شد
                        <span class="text-green-500 text-xs mr-2">({{ \Morilog\Jalali\Jalalian::fromCarbon($order->exit_scanned_at)->format('Y/m/d H:i') }})</span>
                    </span>
                </div>
                @else
                <!-- Scan Input + Camera Button -->
                <template x-if="!barcodeVerified">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="relative flex-1">
                                <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                <input type="text"
                                       x-ref="orderScanInput"
                                       x-model="orderBarcode"
                                       @keydown.enter.prevent="verifyBarcode()"
                                       :disabled="scanning"
                                       placeholder="بارکد سفارش را اسکن کنید..."
                                       class="w-full pr-12 pl-4 py-4 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-lg font-medium text-center transition-colors"
                                       :class="scanning ? 'bg-gray-100 cursor-not-allowed' : ''">
                            </div>
                            <button @click="startCamera()" type="button"
                                    class="flex items-center justify-center w-14 h-14 bg-orange-600 text-white rounded-xl hover:bg-orange-700 transition-colors shrink-0"
                                    title="اسکن با دوربین">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Camera Scanner -->
                        <div x-show="cameraActive" x-cloak class="mb-3">
                            <div id="barcode-reader" class="rounded-xl overflow-hidden border-2 border-orange-300"></div>
                            <button @click="stopCamera()" type="button" class="mt-2 w-full py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">
                                بستن دوربین
                            </button>
                        </div>

                        <!-- Scan Message -->
                        <div x-show="scanMessage" x-cloak class="p-3 rounded-lg text-sm" :class="scanError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'">
                            <span x-text="scanMessage"></span>
                        </div>
                    </div>
                </template>

                <!-- Just verified via JS (before page reload) -->
                <template x-if="barcodeVerified">
                    <div class="flex items-center gap-2 p-4 bg-green-50 border border-green-200 rounded-xl">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-medium text-green-700">اسکن خروج تایید شد</span>
                    </div>
                </template>
                @endif
            </div>

            <!-- Items List (read-only) -->
            <div class="space-y-1.5">
                @foreach($order->items as $item)
                <div class="flex items-center justify-between p-2.5 rounded-lg border border-gray-200 text-sm bg-white">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 flex items-center justify-center rounded text-xs font-bold bg-gray-100 text-gray-500">{{ $item->quantity }}</span>
                        <span class="text-gray-900">{{ $item->product_name }}</span>
                    </div>
                    <span class="text-xs text-gray-400" dir="ltr">{{ $item->product_sku ?? $item->product_barcode ?? '' }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Status Change Section -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">تغییر وضعیت</h2>
        <form action="{{ route('warehouse.status', $order) }}" method="POST" class="flex flex-wrap items-center gap-3">
            @csrf
            @method('PATCH')
            @foreach($allStatuses as $status)
                @if($status !== $order->status)
                    @php $sColor = $statusColors[$status]; @endphp
                    <button type="submit" name="status" value="{{ $status }}"
                            class="inline-flex items-center gap-2 px-4 py-2 border-2 border-{{ $sColor }}-200 bg-{{ $sColor }}-50 text-{{ $sColor }}-700 rounded-lg hover:bg-{{ $sColor }}-100 hover:border-{{ $sColor }}-300 font-medium text-sm transition-colors"
                            onclick="return confirm('تغییر وضعیت به «{{ $statusLabels[$status] }}»؟')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! \Modules\Warehouse\Models\WarehouseOrder::statusIcons()[$status] ?? '' !!}</svg>
                        {{ $statusLabels[$status] }}
                    </button>
                @endif
            @endforeach
        </form>
    </div>
    @endcanany
</div>

@if($order->status === 'packed' && !$order->exit_scanned_at)
@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function exitScanStation() {
    return {
        orderId: {{ $order->id }},
        orderBarcode: '',
        barcodeVerified: false,
        scanning: false,
        scanMessage: '',
        scanError: false,
        cameraActive: false,
        html5QrCode: null,

        init() {
            this.$nextTick(() => {
                if (this.$refs.orderScanInput) this.$refs.orderScanInput.focus();
            });
        },

        async verifyBarcode() {
            if (!this.orderBarcode.trim() || this.scanning) return;
            this.scanning = true;
            this.scanMessage = '';
            this.scanError = false;

            try {
                const res = await fetch('{{ route("warehouse.packing.verify-order-barcode") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: this.orderId, barcode: this.orderBarcode.trim() })
                });
                const data = await res.json();

                if (data.success) {
                    this.barcodeVerified = true;
                    this.scanMessage = '';
                    this.stopCamera();
                    this.playBeep(true);
                    // رفرش صفحه بعد از ثبت موفق
                    setTimeout(() => { location.reload(); }, 1000);
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

            this.orderBarcode = '';
            this.scanning = false;
        },

        async startCamera() {
            this.cameraActive = true;
            await this.$nextTick();

            try {
                const formatsToSupport = [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.ITF,
                    Html5QrcodeSupportedFormats.CODABAR,
                ];
                this.html5QrCode = new Html5Qrcode("barcode-reader", { formatsToSupport });
                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 15, qrbox: { width: 300, height: 120 }, aspectRatio: 1.0 },
                    (decodedText) => {
                        this.orderBarcode = decodedText;
                        this.stopCamera();
                        this.verifyBarcode();
                    },
                    () => {}
                );
            } catch (err) {
                this.scanMessage = 'دسترسی به دوربین ممکن نیست. لطفا دسترسی دوربین را فعال کنید.';
                this.scanError = true;
                this.cameraActive = false;
            }
        },

        async stopCamera() {
            if (this.html5QrCode) {
                try {
                    await this.html5QrCode.stop();
                    this.html5QrCode.clear();
                } catch (e) {}
                this.html5QrCode = null;
            }
            this.cameraActive = false;
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

@if($order->barcode)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('order-barcode');
    if (el) {
        JsBarcode(el, '{{ $order->barcode }}', {
            format: 'CODE128',
            width: 2,
            height: 80,
            displayValue: true,
            fontSize: 16,
            margin: 10,
        });
    }
});
</script>
@endpush
@endif

@if($order->shipping_type === 'post')
@push('scripts')
<script>
function tapinLocation() {
    return {
        loading: false,
        error: '',
        provinces: [],
        cities: [],
        selectedProvince: '{{ $tapinData["province_code"] ?? "" }}',
        selectedCity: '{{ $tapinData["city_code"] ?? "" }}',
        saving: false,
        successMessage: '',

        async init() {
            this.loading = true;
            try {
                const res = await fetch('{{ route("warehouse.tapin.wc-states") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success && data.tapin_state_tree) {
                    this.provinces = data.tapin_state_tree.map(p => ({
                        code: p.code,
                        title: (p.title || '').trim(),
                        cities: (p.cities || []).map(c => ({
                            code: c.code,
                            title: (c.title || '').replace(/[\r\n]/g, '').trim()
                        }))
                    }));
                    if (this.selectedProvince) {
                        this.loadCities();
                    }
                } else {
                    this.error = data.message || 'خطا در دریافت لیست استان‌ها';
                }
            } catch (e) {
                this.error = 'خطا در ارتباط با سرور';
            }
            this.loading = false;
        },

        loadCities() {
            const province = this.provinces.find(p => p.code == this.selectedProvince);
            this.cities = province ? province.cities : [];
        },

        onProvinceChange() {
            this.selectedCity = '';
            this.successMessage = '';
            this.loadCities();
        },

        async save() {
            if (!this.selectedProvince || !this.selectedCity) return;
            this.saving = true;
            this.successMessage = '';
            this.error = '';

            const province = this.provinces.find(p => p.code == this.selectedProvince);
            const city = this.cities.find(c => c.code == this.selectedCity);

            try {
                const res = await fetch('{{ route("warehouse.save-tapin-location", $order) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        province_code: parseInt(this.selectedProvince),
                        city_code: parseInt(this.selectedCity),
                        province_name: province ? province.title : '',
                        city_name: city ? city.title : ''
                    })
                });
                const data = await res.json();

                if (data.success) {
                    this.successMessage = data.message || 'ذخیره شد!';
                } else {
                    this.error = data.message || 'خطا در ذخیره';
                }
            } catch (e) {
                this.error = 'خطا در ارتباط با سرور';
            }
            this.saving = false;
        }
    };
}
</script>
@endpush
@endif
@endsection
