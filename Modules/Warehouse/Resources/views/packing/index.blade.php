@extends('layouts.admin')
@section('page-title', 'ایستگاه بسته‌بندی')
@section('main')
<div x-data="packingStation()" x-init="initStation()" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">ایستگاه بسته‌بندی</h1>
                <p class="text-gray-600 mt-1">اسکن بارکد و کنترل وزن سفارشات</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span x-show="order" x-cloak class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full"
                  :class="allScanned ? 'bg-success-50 text-success-600' : 'bg-warning-50 text-warning-500'">
                <span class="w-2 h-2 rounded-full" :class="allScanned ? 'bg-success-500' : 'bg-warning-500'"></span>
                <span x-text="allScanned ? 'همه اسکن شده' : 'در حال اسکن'"></span>
            </span>
        </div>
    </div>

    <!-- Main 2-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- LEFT SIDE: Scanner Inputs -->
        <div class="space-y-6">

            <!-- Order Barcode Scanner -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-brand-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">اسکن سفارش</h2>
                        <p class="text-sm text-gray-500">بارکد یا شماره سفارش را اسکن کنید</p>
                    </div>
                </div>
                <div class="relative">
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           x-ref="orderInput"
                           x-model="orderBarcode"
                           @keydown.enter.prevent="scanOrder()"
                           @change="scanOrder()"
                           :disabled="loading"
                           autofocus
                           placeholder="بارکد سفارش را اسکن کنید"
                           class="w-full pr-12 pl-4 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-lg font-medium transition-colors"
                           :class="loading ? 'bg-gray-100 cursor-not-allowed' : ''">
                </div>
                <!-- Status Messages -->
                <div x-show="orderMessage" x-cloak x-transition class="mt-3 p-3 rounded-lg text-sm font-medium"
                     :class="orderError ? 'bg-error-50 text-error-600' : 'bg-success-50 text-success-600'">
                    <span x-text="orderMessage"></span>
                </div>
            </div>

            <!-- Product Barcode Scanner (visible after order loads) -->
            <div x-show="order" x-cloak x-transition class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">اسکن محصول</h2>
                        <p class="text-sm text-gray-500">بارکد محصولات سفارش را اسکن کنید</p>
                    </div>
                </div>
                <div class="relative">
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           x-ref="productInput"
                           x-model="productBarcode"
                           @keydown.enter.prevent="scanProduct()"
                           @change="scanProduct()"
                           :disabled="loading || allScanned"
                           placeholder="بارکد محصول را اسکن کنید"
                           class="w-full pr-12 pl-4 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium transition-colors"
                           :class="(loading || allScanned) ? 'bg-gray-100 cursor-not-allowed' : ''">
                </div>
                <!-- Product Scan Message -->
                <div x-show="productMessage" x-cloak x-transition class="mt-3 p-3 rounded-lg text-sm font-medium"
                     :class="productError ? 'bg-error-50 text-error-600' : 'bg-success-50 text-success-600'">
                    <span x-text="productMessage"></span>
                </div>
            </div>

            <!-- Weight Verification (visible after all items scanned) -->
            <div x-show="order && allScanned" x-cloak x-transition class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">تایید وزن</h2>
                        <p class="text-sm text-gray-500">وزن بسته را وارد کنید (کیلوگرم)</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative flex-1">
                        <input type="number"
                               x-model="actualWeight"
                               step="0.01"
                               min="0"
                               placeholder="وزن (kg)"
                               :disabled="weightVerified"
                               class="w-full px-4 py-3.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-lg font-medium"
                               :class="weightVerified ? 'bg-gray-100 cursor-not-allowed' : ''"
                               dir="ltr">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">kg</span>
                    </div>
                    <button @click="verifyWeight()"
                            :disabled="!actualWeight || loading || weightVerified"
                            class="px-6 py-3.5 bg-orange-600 text-white rounded-xl hover:bg-orange-700 font-medium text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                        تایید وزن
                    </button>
                </div>

                <!-- Weight Result -->
                <div x-show="weightMessage" x-cloak x-transition class="mt-4">
                    <!-- Weight Verified OK -->
                    <div x-show="weightVerified" class="p-4 bg-success-50 rounded-xl border border-green-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success-500 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="font-bold text-success-600" x-text="weightMessage"></p>
                                <p class="text-sm text-success-600 mt-0.5">اختلاف وزن: <span x-text="weightDifference"></span>%</p>
                            </div>
                        </div>
                    </div>

                    <!-- Weight Failed -->
                    <div x-show="weightFailed" class="p-4 bg-error-50 rounded-xl border border-red-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-error-500 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <div>
                                <p class="font-bold text-error-600">محصولی جا مانده!</p>
                                <p class="text-sm text-error-600 mt-0.5" x-text="weightMessage"></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-red-100 mb-3">
                            <div class="flex-1 text-center">
                                <p class="text-xs text-gray-500">وزن مورد انتظار</p>
                                <p class="text-lg font-bold text-gray-900" x-text="expectedWeight + ' kg'"></p>
                            </div>
                            <div class="w-px h-10 bg-gray-200"></div>
                            <div class="flex-1 text-center">
                                <p class="text-xs text-gray-500">وزن واقعی</p>
                                <p class="text-lg font-bold text-error-600" x-text="actualWeight + ' kg'"></p>
                            </div>
                            <div class="w-px h-10 bg-gray-200"></div>
                            <div class="flex-1 text-center">
                                <p class="text-xs text-gray-500">اختلاف</p>
                                <p class="text-lg font-bold text-error-600" x-text="weightDifference + '%'"></p>
                            </div>
                        </div>
                        <button @click="forceVerify()"
                                :disabled="loading"
                                class="w-full px-4 py-2.5 bg-error-500 text-white rounded-lg hover:bg-error-600 font-medium text-sm transition-colors disabled:opacity-50">
                            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            تایید اجباری (با وجود اختلاف وزن)
                        </button>
                    </div>
                </div>
            </div>

            <!-- New Order Button (after completion) -->
            <div x-show="weightVerified" x-cloak x-transition>
                <button @click="resetStation()"
                        class="w-full px-6 py-4 bg-brand-600 text-white rounded-xl hover:bg-brand-700 font-bold text-base transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    سفارش بعدی
                </button>
            </div>
        </div>

        <!-- RIGHT SIDE: Order Details -->
        <div class="space-y-6">

            <!-- Empty State -->
            <div x-show="!order" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-700 mb-2">بارکد سفارش را اسکن کنید</h3>
                <p class="text-sm text-gray-500">با اسکن بارکد سفارش، اطلاعات سفارش و لیست محصولات نمایش داده می‌شود.</p>
            </div>

            <!-- Order Info Card -->
            <div x-show="order" x-cloak x-transition class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900">اطلاعات سفارش</h2>
                    </div>
                    <button @click="resetStation()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="بستن سفارش">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-1">شماره سفارش</p>
                        <p class="text-sm font-bold text-gray-900" x-text="order?.order_number" dir="ltr"></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-1">نام مشتری</p>
                        <p class="text-sm font-bold text-gray-900" x-text="order?.customer_name"></p>
                    </div>
                </div>

                <!-- Barcode Display -->
                <div class="bg-gray-50 rounded-lg p-4 text-center" x-show="order?.barcode">
                    <svg x-ref="barcodeDisplay" class="mx-auto"></svg>
                    <p class="text-xs text-gray-500 mt-1" x-text="order?.barcode" dir="ltr"></p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div x-show="order" x-cloak x-transition class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">پیشرفت اسکن</h3>
                    <span class="text-sm font-bold" :class="allScanned ? 'text-success-600' : 'text-brand-600'"
                          x-text="scannedCount + ' / ' + totalCount"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full transition-all duration-500 ease-out"
                         :class="allScanned ? 'bg-success-500' : 'bg-brand-500'"
                         :style="'width: ' + progressPercent + '%'"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2" x-text="progressPercent + '% تکمیل شده'"></p>
            </div>

            <!-- Products Checklist -->
            <div x-show="order" x-cloak x-transition class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">لیست محصولات</h3>
                </div>
                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    <template x-for="item in items" :key="item.id">
                        <div class="flex items-center gap-3 p-4 transition-colors"
                             :class="item.scanned ? 'bg-success-50' : ''">
                            <!-- Scan Status Icon -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :class="item.scanned ? 'bg-success-500' : 'bg-gray-200'">
                                <svg x-show="item.scanned" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span x-show="!item.scanned" class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            </div>
                            <!-- Product Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="item.product_name"></p>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span class="text-xs text-gray-500">
                                        تعداد: <span class="font-bold" x-text="item.quantity"></span>
                                    </span>
                                    <span class="text-xs text-gray-500" x-show="item.weight">
                                        وزن: <span class="font-bold" x-text="item.weight"></span> kg
                                    </span>
                                    <span class="text-xs text-gray-400" x-show="item.product_barcode" x-text="item.product_barcode" dir="ltr"></span>
                                </div>
                            </div>
                            <!-- Status Badge -->
                            <span class="text-xs font-medium px-2 py-1 rounded-full flex-shrink-0"
                                  :class="item.scanned ? 'bg-success-500 text-white' : 'bg-gray-100 text-gray-500'"
                                  x-text="item.scanned ? 'اسکن شده' : 'در انتظار'"></span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Weight Summary -->
            <div x-show="order" x-cloak x-transition class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-900">خلاصه وزن</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <p class="text-xs text-gray-500 mb-1">وزن مورد انتظار</p>
                        <p class="text-xl font-bold text-gray-900"><span x-text="expectedWeight"></span> <span class="text-sm font-normal text-gray-500">kg</span></p>
                    </div>
                    <div class="rounded-lg p-4 text-center"
                         :class="weightVerified ? 'bg-success-50' : (weightFailed ? 'bg-error-50' : 'bg-gray-50')">
                        <p class="text-xs mb-1" :class="weightVerified ? 'text-success-600' : (weightFailed ? 'text-error-600' : 'text-gray-500')">وزن واقعی</p>
                        <p class="text-xl font-bold" :class="weightVerified ? 'text-success-600' : (weightFailed ? 'text-error-600' : 'text-gray-900')">
                            <template x-if="actualWeight">
                                <span><span x-text="actualWeight"></span> <span class="text-sm font-normal" :class="weightVerified ? 'text-success-500' : (weightFailed ? 'text-error-500' : 'text-gray-500')">kg</span></span>
                            </template>
                            <template x-if="!actualWeight">
                                <span class="text-gray-400">--</span>
                            </template>
                        </p>
                    </div>
                </div>
                <!-- Weight Difference Indicator -->
                <div x-show="weightDifference !== null" x-cloak class="mt-3 flex items-center justify-center gap-2 p-2 rounded-lg text-sm font-medium"
                     :class="weightVerified ? 'bg-success-50 text-success-600' : (weightFailed ? 'bg-error-50 text-error-600' : 'bg-gray-50 text-gray-600')">
                    <svg x-show="weightVerified" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="weightFailed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span x-text="'اختلاف وزن: ' + weightDifference + '%'"></span>
                </div>
            </div>

        </div>
    </div>

    <!-- Hidden audio element for beep feedback -->
    <audio x-ref="beepSuccess" preload="auto">
        <source src="data:audio/wav;base64,UklGRl9vT19teleUEFWRWZtdCAQAAAAAQABAESEAACIiQAAAgAQAGRhdGE=" type="audio/wav">
    </audio>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
function packingStation() {
    return {
        // State
        orderBarcode: '',
        productBarcode: '',
        actualWeight: '',
        order: null,
        items: [],
        loading: false,

        // Messages
        orderMessage: '',
        orderError: false,
        productMessage: '',
        productError: false,
        weightMessage: '',
        weightFailed: false,
        weightVerified: false,
        weightDifference: null,

        // Computed-like
        get expectedWeight() {
            return this.order?.total_weight || 0;
        },
        get scannedCount() {
            return this.items.filter(i => i.scanned).length;
        },
        get totalCount() {
            return this.items.length;
        },
        get allScanned() {
            return this.totalCount > 0 && this.scannedCount === this.totalCount;
        },
        get progressPercent() {
            if (this.totalCount === 0) return 0;
            return Math.round((this.scannedCount / this.totalCount) * 100);
        },

        // Audio context for beep
        audioCtx: null,

        initStation() {
            this.$nextTick(() => {
                if (this.$refs.orderInput) {
                    this.$refs.orderInput.focus();
                }
            });
        },

        playBeep(success = true) {
            try {
                if (!this.audioCtx) {
                    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                const ctx = this.audioCtx;
                const oscillator = ctx.createOscillator();
                const gainNode = ctx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(ctx.destination);
                oscillator.frequency.value = success ? 800 : 300;
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (success ? 0.15 : 0.4));
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + (success ? 0.15 : 0.4));
            } catch (e) {
                // Audio not supported, silently ignore
            }
        },

        async scanOrder() {
            if (!this.orderBarcode.trim()) return;
            this.loading = true;
            this.orderMessage = '';
            this.orderError = false;

            try {
                const response = await fetch('{{ route("warehouse.packing.scan-order") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ barcode: this.orderBarcode.trim() })
                });

                const data = await response.json();

                if (data.success) {
                    this.order = data.order;
                    this.items = data.order.items || [];
                    this.orderMessage = 'سفارش ' + data.order.order_number + ' بارگذاری شد.';
                    this.orderError = false;
                    this.playBeep(true);

                    // Render barcode
                    this.$nextTick(() => {
                        if (this.$refs.barcodeDisplay && this.order.barcode) {
                            try {
                                JsBarcode(this.$refs.barcodeDisplay, this.order.barcode, {
                                    format: 'CODE128',
                                    width: 2,
                                    height: 50,
                                    displayValue: false,
                                    margin: 5
                                });
                            } catch (e) {}
                        }
                        if (this.$refs.productInput) {
                            this.$refs.productInput.focus();
                        }
                    });
                } else {
                    this.orderMessage = data.message;
                    this.orderError = true;
                    this.playBeep(false);
                }
            } catch (e) {
                this.orderMessage = 'خطا در ارتباط با سرور';
                this.orderError = true;
                this.playBeep(false);
            }

            this.orderBarcode = '';
            this.loading = false;
        },

        async scanProduct() {
            if (!this.productBarcode.trim() || !this.order) return;
            this.loading = true;
            this.productMessage = '';
            this.productError = false;

            try {
                const response = await fetch('{{ route("warehouse.packing.scan-product") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        order_id: this.order.id,
                        barcode: this.productBarcode.trim()
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Mark the scanned item
                    const itemIndex = this.items.findIndex(i => i.id === data.item_id);
                    if (itemIndex !== -1) {
                        this.items[itemIndex].scanned = true;
                    }
                    this.productMessage = data.message;
                    this.productError = false;
                    this.playBeep(true);

                    // If all scanned, focus weight input
                    if (data.all_scanned) {
                        this.$nextTick(() => {
                            const weightInput = this.$el.querySelector('input[type="number"]');
                            if (weightInput) weightInput.focus();
                        });
                    }
                } else {
                    this.productMessage = data.message;
                    this.productError = true;
                    this.playBeep(false);
                }
            } catch (e) {
                this.productMessage = 'خطا در ارتباط با سرور';
                this.productError = true;
                this.playBeep(false);
            }

            this.productBarcode = '';
            this.loading = false;

            // Re-focus product input if not all scanned
            if (!this.allScanned && this.$refs.productInput) {
                this.$refs.productInput.focus();
            }
        },

        async verifyWeight() {
            if (!this.actualWeight || !this.order) return;
            this.loading = true;
            this.weightMessage = '';
            this.weightFailed = false;

            try {
                const response = await fetch('{{ route("warehouse.packing.verify-weight") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        order_id: this.order.id,
                        actual_weight: parseFloat(this.actualWeight)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.weightMessage = data.message;
                    this.weightDifference = data.difference;

                    if (data.verified) {
                        this.weightVerified = true;
                        this.weightFailed = false;
                        this.playBeep(true);
                    } else {
                        this.weightFailed = true;
                        this.weightVerified = false;
                        this.playBeep(false);
                    }
                }
            } catch (e) {
                this.weightMessage = 'خطا در ارتباط با سرور';
                this.weightFailed = true;
                this.playBeep(false);
            }

            this.loading = false;
        },

        async forceVerify() {
            if (!this.order) return;
            this.loading = true;

            try {
                const response = await fetch('{{ route("warehouse.packing.force-verify") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order_id: this.order.id })
                });

                const data = await response.json();

                if (data.success) {
                    this.weightMessage = data.message;
                    this.weightVerified = true;
                    this.weightFailed = false;
                    this.playBeep(true);
                }
            } catch (e) {
                this.weightMessage = 'خطا در تایید اجباری';
                this.playBeep(false);
            }

            this.loading = false;
        },

        resetStation() {
            this.orderBarcode = '';
            this.productBarcode = '';
            this.actualWeight = '';
            this.order = null;
            this.items = [];
            this.orderMessage = '';
            this.orderError = false;
            this.productMessage = '';
            this.productError = false;
            this.weightMessage = '';
            this.weightFailed = false;
            this.weightVerified = false;
            this.weightDifference = null;

            this.$nextTick(() => {
                if (this.$refs.orderInput) {
                    this.$refs.orderInput.focus();
                }
            });
        }
    };
}
</script>
@endpush
@endsection
