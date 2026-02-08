@extends('layouts.admin')

@section('title', 'سفارش #' . $order->order_number)

@section('main')
<div class="p-6" x-data="orderDetail()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.orders.index') }}" class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-700 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-100">سفارش #{{ $order->order_number }}</h1>
                <p class="text-slate-400 mt-1">{{ $order->date_created?->format('Y/m/d H:i') }}</p>
            </div>
            <!-- Timer -->
            <div class="flex items-center gap-2 bg-slate-700/50 rounded-lg px-3 py-2" x-data="orderTimer({{ $order->date_created?->timestamp ?? 0 }})" x-init="startTimer()">
                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-slate-200 font-mono text-sm" x-text="formattedTime"></span>
                <span class="text-slate-500 text-xs">زمان سپری شده</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button @click="syncOrder()"
                    :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                <svg class="w-4 h-4" :class="syncing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                بروزرسانی
            </button>
            <button @click="checkAndPrint('invoice')"
                    class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                چاپ فاکتور
                @if($order->print_count > 0)
                <span class="bg-yellow-500 text-yellow-900 text-xs px-1.5 py-0.5 rounded">{{ $order->print_count }}</span>
                @endif
            </button>
            <button @click="checkAndPrint('amadast')"
                    class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                </svg>
                فاکتور آمادست
            </button>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Items -->
            <div class="bg-slate-800 rounded-lg">
                <div class="p-4 border-b border-slate-700">
                    <h2 class="font-semibold text-slate-100">محصولات ({{ $order->items->count() }})</h2>
                </div>
                <div class="divide-y divide-slate-700">
                    @foreach($order->items as $item)
                    <div class="p-4 flex items-start gap-4">
                        @if($item->image_url)
                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="w-16 h-16 rounded-lg object-cover bg-slate-700">
                        @else
                        <div class="w-16 h-16 rounded-lg bg-slate-700 flex items-center justify-center">
                            <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        @endif
                        <div class="flex-1">
                            <h3 class="font-medium text-slate-200">{{ $item->name }}</h3>
                            @if($item->sku)
                            <p class="text-xs text-slate-500 mt-1">SKU: {{ $item->sku }}</p>
                            @endif
                            @if($item->variation_text)
                            <p class="text-sm text-slate-400 mt-1">{{ $item->variation_text }}</p>
                            @endif
                            <div class="flex items-center gap-4 mt-2 text-sm">
                                <span class="text-slate-400">تعداد: <span class="text-slate-200">{{ $item->quantity }}</span></span>
                                <span class="text-slate-400">قیمت واحد: <span class="text-slate-200">{{ $item->formatted_price }}</span></span>
                            </div>
                        </div>
                        <div class="text-left">
                            <div class="font-medium text-slate-200">{{ $item->formatted_total }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <!-- Totals -->
                <div class="p-4 bg-slate-700/50 space-y-2">
                    @if($order->subtotal)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">جمع جزء:</span>
                        <span class="text-slate-300">{{ number_format($order->subtotal) }} تومان</span>
                    </div>
                    @endif
                    @if($order->shipping_total > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">هزینه ارسال:</span>
                        <span class="text-slate-300">{{ number_format($order->shipping_total) }} تومان</span>
                    </div>
                    @endif
                    @if($order->discount_total > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">تخفیف:</span>
                        <span class="text-red-400">-{{ number_format($order->discount_total) }} تومان</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-lg font-bold pt-2 border-t border-slate-600">
                        <span class="text-slate-300">جمع کل:</span>
                        <span class="text-slate-100">{{ $order->formatted_total }}</span>
                    </div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="bg-slate-800 rounded-lg">
                <div class="p-4 border-b border-slate-700">
                    <h2 class="font-semibold text-slate-100">اطلاعات مشتری</h2>
                </div>
                <div class="p-4 grid md:grid-cols-2 gap-6">
                    <!-- Billing -->
                    <div>
                        <h3 class="text-sm font-medium text-slate-400 mb-3">آدرس صورتحساب</h3>
                        <div class="space-y-2">
                            <p class="text-slate-200">{{ $order->customer_full_name }}</p>
                            @if($order->billing_phone)
                            <p class="text-slate-300 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                {{ $order->billing_phone }}
                            </p>
                            @endif
                            @if($order->customer_email)
                            <p class="text-slate-300 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                {{ $order->customer_email }}
                            </p>
                            @endif
                            @if($order->billing_address)
                            <p class="text-slate-400 text-sm mt-2">{{ $order->billing_address }}</p>
                            @endif
                        </div>
                    </div>
                    <!-- Shipping -->
                    <div>
                        <h3 class="text-sm font-medium text-slate-400 mb-3">آدرس ارسال</h3>
                        <div class="space-y-2">
                            @if($order->shipping_first_name || $order->shipping_last_name)
                            <p class="text-slate-200">{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                            @endif
                            @if($order->shipping_address)
                            <p class="text-slate-400 text-sm">{{ $order->shipping_address }}</p>
                            @else
                            <p class="text-slate-500 text-sm">مانند آدرس صورتحساب</p>
                            @endif
                        </div>
                    </div>
                </div>
                @if($order->customer_note)
                <div class="p-4 border-t border-slate-700">
                    <h3 class="text-sm font-medium text-slate-400 mb-2">یادداشت مشتری</h3>
                    <p class="text-slate-300 text-sm bg-slate-700/50 rounded-lg p-3">{{ $order->customer_note }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">وضعیت سفارش</h3>
                <div class="space-y-4">
                    <!-- WooCommerce Status -->
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">وضعیت ووکامرس</label>
                        <select x-model="wooStatus"
                                @change="updateWooStatus()"
                                class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @foreach($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Internal Status -->
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">وضعیت داخلی</label>
                        <select x-model="internalStatus"
                                @change="updateInternalStatus()"
                                class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @foreach($internalStatuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Assigned To -->
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">مسئول</label>
                        <select x-model="assignedTo"
                                @change="updateAssignment()"
                                class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">بدون مسئول</option>
                            @foreach($staff as $user)
                            <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Shipping Card -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">اطلاعات ارسال</h3>
                <div class="space-y-4">
                    <!-- Payment Method -->
                    @if($order->payment_method_title)
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">روش پرداخت</label>
                        <p class="text-slate-200">{{ $order->payment_method_title }}</p>
                    </div>
                    @endif

                    <!-- Quick Actions -->
                    <div class="flex gap-2">
                        <button @click="markPacked()"
                                :disabled="isPacked"
                                class="flex-1 px-3 py-2 text-sm rounded-lg transition"
                                :class="isPacked ? 'bg-green-900/50 text-green-400' : 'bg-slate-700 hover:bg-slate-600 text-white'">
                            <span x-text="isPacked ? 'بسته‌بندی شده' : 'بسته‌بندی'"></span>
                        </button>
                    </div>

                    <!-- Shipping Form -->
                    <div x-show="!isShipped" class="space-y-3 pt-4 border-t border-slate-700">
                        <input type="text"
                               x-model="trackingCode"
                               placeholder="کد رهگیری مرسوله"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <input type="text"
                               x-model="shippingCompany"
                               placeholder="شرکت پستی (اختیاری)"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <button @click="markShipped()"
                                :disabled="!trackingCode"
                                class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white rounded-lg transition">
                            ثبت ارسال
                        </button>
                    </div>

                    <!-- Shipped Info -->
                    <div x-show="isShipped" class="pt-4 border-t border-slate-700">
                        <div class="flex items-center gap-2 text-green-400 mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            ارسال شده
                        </div>
                        @if($order->tracking_code)
                        <p class="text-sm text-slate-400">کد رهگیری: <span class="text-slate-200">{{ $order->tracking_code }}</span></p>
                        @endif
                        @if($order->shipping_carrier)
                        <p class="text-sm text-slate-400">شرکت پستی: <span class="text-slate-200">{{ $order->shipping_carrier }}</span></p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Weight Entry -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">وزن بسته</h3>
                <div class="space-y-3">
                    @if($order->product_weight_woo)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">وزن محصول (ووکامرس):</span>
                        <span class="text-slate-300">{{ number_format($order->product_weight_woo) }} گرم</span>
                    </div>
                    @endif
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">وزن بسته (گرم)</label>
                        <input type="number"
                               x-model="packageWeight"
                               placeholder="وزن بسته را وارد کنید"
                               min="0"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">وزن کارتن (گرم) - اختیاری</label>
                        <input type="number"
                               x-model="cartonWeight"
                               placeholder="وزن کارتن"
                               min="0"
                               class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button @click="updateWeight()"
                            :disabled="!packageWeight || savingWeight"
                            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 text-white text-sm rounded-lg transition">
                        <span x-text="savingWeight ? 'در حال ثبت...' : 'ثبت وزن'"></span>
                    </button>

                    <!-- Weight verification result -->
                    @if($order->package_weight)
                    <div class="pt-3 border-t border-slate-700 mt-3">
                        <div class="flex items-center gap-2 {{ $order->weight_verified ? 'text-green-400' : 'text-yellow-400' }}">
                            @if($order->weight_verified)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-sm">وزن تایید شده</span>
                            @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span class="text-sm">اختلاف وزن: {{ $order->weight_difference_percent }}%</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">وزن ثبت شده: {{ number_format($order->package_weight) }} گرم</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Courier Assignment -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">اطلاعات پیک</h3>
                @if($order->courier_name && $order->courier_mobile)
                    <!-- Courier info already set -->
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">نام پیک:</span>
                            <span class="text-slate-200">{{ $order->courier_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">موبایل پیک:</span>
                            <span class="text-slate-200 font-mono">{{ $order->courier_mobile }}</span>
                        </div>
                        @if($order->courier_assigned_at)
                        <div class="flex justify-between">
                            <span class="text-slate-500">تاریخ تخصیص:</span>
                            <span class="text-slate-300">{{ $order->courier_assigned_at->diffForHumans() }}</span>
                        </div>
                        @endif
                        <div class="flex items-center gap-2 pt-2 {{ $order->courier_notified_to_customer ? 'text-green-400' : 'text-yellow-400' }}">
                            @if($order->courier_notified_to_customer)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-xs">مشتری مطلع شد</span>
                            @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs">مشتری مطلع نشده</span>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Courier assignment form -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">نام و نام خانوادگی پیک</label>
                            <input type="text"
                                   x-model="courierName"
                                   placeholder="نام پیک"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">شماره موبایل پیک</label>
                            <input type="tel"
                                   x-model="courierMobile"
                                   placeholder="09123456789"
                                   maxlength="11"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" x-model="notifyCustomer" id="notifyCustomer" class="rounded bg-slate-700 border-slate-600">
                            <label for="notifyCustomer" class="text-sm text-slate-300">اطلاع‌رسانی به مشتری</label>
                        </div>
                        <button @click="assignCourier()"
                                :disabled="!courierName || !courierMobile || assigningCourier"
                                class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white text-sm rounded-lg transition">
                            <span x-text="assigningCourier ? 'در حال ثبت...' : 'ثبت و ارسال'"></span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Amadast Shipping -->
            <div class="bg-slate-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-slate-400">ارسال با آمادست</h3>
                    @if($order->amadast_order_id)
                    <span class="px-2 py-1 bg-green-900/50 text-green-400 text-xs rounded">ثبت شده</span>
                    @endif
                </div>

                @if($order->amadast_order_id)
                    <!-- Amadast tracking info -->
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">شناسه آمادست:</span>
                            <span class="text-slate-300">{{ $order->amadast_order_id }}</span>
                        </div>
                        @if($order->amadast_tracking_code)
                        <div class="flex justify-between">
                            <span class="text-slate-500">کد رهگیری آمادست:</span>
                            <span class="text-slate-200 font-medium">{{ $order->amadast_tracking_code }}</span>
                        </div>
                        @endif
                        @if($order->courier_tracking_code)
                        <div class="flex justify-between">
                            <span class="text-slate-500">کد رهگیری پست:</span>
                            <span class="text-slate-200 font-medium">{{ $order->courier_tracking_code }}</span>
                        </div>
                        @endif
                        @if($order->courier_title)
                        <div class="flex justify-between">
                            <span class="text-slate-500">شرکت حمل:</span>
                            <span class="text-slate-300">{{ $order->courier_title }}</span>
                        </div>
                        @endif
                        @if($order->sent_to_amadast_at)
                        <div class="flex justify-between">
                            <span class="text-slate-500">تاریخ ارسال:</span>
                            <span class="text-slate-300">{{ $order->sent_to_amadast_at->diffForHumans() }}</span>
                        </div>
                        @endif
                    </div>

                    <!-- Update tracking button -->
                    <button type="button" @click="updateAmadastTracking()"
                            :disabled="updatingTracking"
                            class="mt-4 w-full flex items-center justify-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg transition">
                        <svg class="w-4 h-4" :class="updatingTracking && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span x-text="updatingTracking ? 'در حال بروزرسانی...' : 'بروزرسانی اطلاعات رهگیری'"></span>
                    </button>
                @else
                    <!-- Send to Amadast button -->
                    <button type="button" @click="sendToAmadast()"
                            :disabled="sendingToAmadast"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-lg transition">
                        <svg class="w-4 h-4" :class="sendingToAmadast && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <span x-text="sendingToAmadast ? 'در حال ارسال...' : 'ارسال به آمادست'"></span>
                    </button>
                    <p class="text-xs text-slate-500 mt-2 text-center">سفارش برای حمل و نقل در آمادست ثبت می‌شود</p>
                @endif
            </div>

            <!-- Internal Note -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">یادداشت داخلی</h3>
                <textarea x-model="internalNote"
                          @blur="saveNote()"
                          rows="4"
                          placeholder="یادداشت برای تیم انبار..."
                          class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                <p class="text-xs text-slate-500 mt-2">تغییرات به صورت خودکار ذخیره می‌شود</p>
            </div>

            <!-- Print Logs -->
            <div class="bg-slate-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-slate-400">تاریخچه پرینت</h3>
                    <button @click="showPrintLogs = !showPrintLogs; if(showPrintLogs) loadPrintLogs()"
                            class="text-xs text-blue-400 hover:text-blue-300">
                        <span x-text="showPrintLogs ? 'بستن' : 'مشاهده'"></span>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-slate-500">تعداد پرینت:</span>
                    <span class="text-slate-200 font-medium">{{ $order->print_count }}</span>
                    @if($order->first_printed_at)
                    <span class="text-slate-500 text-xs">|</span>
                    <span class="text-slate-500 text-xs">اولین: {{ $order->first_printed_at->diffForHumans() }}</span>
                    @endif
                </div>
                <div x-show="showPrintLogs" x-transition class="mt-4 space-y-2">
                    <template x-if="loadingPrintLogs">
                        <div class="text-center text-slate-500 text-sm py-4">در حال بارگذاری...</div>
                    </template>
                    <template x-if="!loadingPrintLogs && printLogs.length === 0">
                        <div class="text-center text-slate-500 text-sm py-4">پرینتی ثبت نشده</div>
                    </template>
                    <template x-for="log in printLogs" :key="log.id">
                        <div class="bg-slate-700/50 rounded-lg p-3 text-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-slate-200" x-text="log.user"></span>
                                    <span class="text-slate-500 mx-1">-</span>
                                    <span class="text-slate-400" x-text="log.print_type"></span>
                                </div>
                                <span x-show="log.was_duplicate" class="text-yellow-400 text-xs">تکراری</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1" x-text="log.created_at_jalali"></div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Meta Info -->
            <div class="bg-slate-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-slate-400 mb-4">اطلاعات تکمیلی</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">شناسه ووکامرس:</span>
                        <span class="text-slate-300">{{ $order->woo_order_id }}</span>
                    </div>
                    @if($order->transaction_id)
                    <div class="flex justify-between">
                        <span class="text-slate-500">شناسه تراکنش:</span>
                        <span class="text-slate-300">{{ $order->transaction_id }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-slate-500">آخرین همگام‌سازی:</span>
                        <span class="text-slate-300">{{ $order->last_synced_at?->diffForHumans() ?? 'نامشخص' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Warning Modal -->
    <div x-show="showPrintWarning"
         x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-slate-800 rounded-lg p-6 max-w-md mx-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-yellow-500/20 rounded-full">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-100">هشدار پرینت تکراری</h3>
            </div>
            <p class="text-slate-300 mb-6" x-text="printWarningMessage"></p>
            <div class="flex gap-3 justify-end">
                <button @click="showPrintWarning = false; pendingPrintType = null"
                        class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                    انصراف
                </button>
                <button @click="confirmPrint()"
                        class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">
                    ادامه پرینت
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="showToast"
         x-transition
         class="fixed bottom-4 left-4 bg-slate-800 border border-slate-700 rounded-lg shadow-lg p-4 max-w-sm z-50">
        <div class="flex items-start gap-3">
            <div :class="toastSuccess ? 'text-green-400' : 'text-red-400'">
                <svg x-show="toastSuccess" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="!toastSuccess" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div class="text-sm text-slate-300" x-text="toastMessage"></div>
        </div>
    </div>
</div>

<script>
// Timer component
function orderTimer(orderCreatedTimestamp) {
    return {
        formattedTime: '00:00:00',
        interval: null,

        startTimer() {
            if (!orderCreatedTimestamp) {
                this.formattedTime = '--:--:--';
                return;
            }

            const updateTime = () => {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - orderCreatedTimestamp;

                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;

                this.formattedTime = [
                    hours.toString().padStart(2, '0'),
                    minutes.toString().padStart(2, '0'),
                    seconds.toString().padStart(2, '0')
                ].join(':');
            };

            updateTime();
            this.interval = setInterval(updateTime, 1000);
        },

        destroy() {
            if (this.interval) clearInterval(this.interval);
        }
    }
}

function orderDetail() {
    return {
        syncing: false,
        wooStatus: '{{ $order->status }}',
        internalStatus: '{{ $order->internal_status ?? 'new' }}',
        assignedTo: '{{ $order->assigned_to ?? '' }}',
        isPacked: {{ $order->is_packed ? 'true' : 'false' }},
        isShipped: {{ $order->is_shipped ? 'true' : 'false' }},
        trackingCode: '',
        shippingCompany: '',
        internalNote: `{{ $order->internal_note ?? '' }}`,
        showToast: false,
        toastMessage: '',
        toastSuccess: true,
        sendingToAmadast: false,
        updatingTracking: false,

        // Weight
        packageWeight: '{{ $order->package_weight ?? '' }}',
        cartonWeight: '{{ $order->carton_weight ?? '' }}',
        savingWeight: false,

        // Courier
        courierName: '',
        courierMobile: '',
        notifyCustomer: true,
        assigningCourier: false,

        // Print
        showPrintWarning: false,
        printWarningMessage: '',
        pendingPrintType: null,
        printWarningCount: 0,

        // Print logs
        showPrintLogs: false,
        printLogs: [],
        loadingPrintLogs: false,

        async syncOrder() {
            this.syncing = true;
            try {
                const response = await this.request('{{ route('warehouse.orders.sync-order', $order) }}', 'POST');
                this.showNotification(response.message, response.success);
                if (response.success) setTimeout(() => location.reload(), 1500);
            } finally {
                this.syncing = false;
            }
        },

        async updateWooStatus() {
            const response = await this.request('{{ route('warehouse.orders.update-status', $order) }}', 'PATCH', { status: this.wooStatus });
            this.showNotification(response.message, response.success);
        },

        async updateInternalStatus() {
            const response = await this.request('{{ route('warehouse.orders.update-internal-status', $order) }}', 'PATCH', { internal_status: this.internalStatus });
            this.showNotification(response.message, response.success);
        },

        async updateAssignment() {
            const response = await this.request('{{ route('warehouse.orders.assign', $order) }}', 'PATCH', { user_id: this.assignedTo || null });
            this.showNotification(response.message, response.success);
        },

        async markPacked() {
            const response = await this.request('{{ route('warehouse.orders.mark-packed', $order) }}', 'POST');
            if (response.success) this.isPacked = true;
            this.showNotification(response.message, response.success);
        },

        async markShipped() {
            const response = await this.request('{{ route('warehouse.orders.mark-shipped', $order) }}', 'POST', {
                tracking_code: this.trackingCode,
                shipping_carrier: this.shippingCompany
            });
            if (response.success) this.isShipped = true;
            this.showNotification(response.message, response.success);
        },

        async saveNote() {
            await this.request('{{ route('warehouse.orders.update-note', $order) }}', 'PATCH', { note: this.internalNote });
        },

        // Weight management
        async updateWeight() {
            this.savingWeight = true;
            try {
                const response = await this.request('{{ route('warehouse.orders.update-weight', $order) }}', 'PATCH', {
                    package_weight: parseFloat(this.packageWeight),
                    carton_weight: this.cartonWeight ? parseFloat(this.cartonWeight) : null
                });
                this.showNotification(response.message, response.success);
                if (response.success) setTimeout(() => location.reload(), 1500);
            } finally {
                this.savingWeight = false;
            }
        },

        // Courier management
        async assignCourier() {
            if (!this.courierMobile.match(/^09[0-9]{9}$/)) {
                this.showNotification('شماره موبایل نامعتبر است', false);
                return;
            }
            this.assigningCourier = true;
            try {
                const response = await this.request('{{ route('warehouse.orders.assign-courier', $order) }}', 'POST', {
                    courier_name: this.courierName,
                    courier_mobile: this.courierMobile,
                    notify_customer: this.notifyCustomer
                });
                this.showNotification(response.message, response.success);
                if (response.success) setTimeout(() => location.reload(), 1500);
            } finally {
                this.assigningCourier = false;
            }
        },

        // Print management
        async checkAndPrint(printType) {
            const response = await this.request('{{ route('warehouse.orders.check-print-status', $order) }}?print_type=' + printType, 'GET');

            if (response.has_printed_before && response.show_warning) {
                this.printWarningMessage = response.message + '\nآیا مطمئن هستید که می‌خواهید دوباره پرینت بگیرید؟';
                this.pendingPrintType = printType;
                this.printWarningCount = response.print_count;
                this.showPrintWarning = true;
            } else if (response.has_printed_before && response.print_count >= 2) {
                // More than 2 prints - show final warning
                this.printWarningMessage = `شما ${response.print_count} بار این سفارش را پرینت کرده‌اید. این عمل به مدیر گزارش می‌شود.`;
                this.pendingPrintType = printType;
                this.showPrintWarning = true;
            } else {
                this.openPrintWindow(printType);
            }
        },

        confirmPrint() {
            this.showPrintWarning = false;
            if (this.pendingPrintType) {
                this.openPrintWindow(this.pendingPrintType);
                this.pendingPrintType = null;
            }
        },

        openPrintWindow(printType) {
            const url = printType === 'amadast'
                ? '{{ route('warehouse.orders.print-amadast', $order) }}'
                : '{{ route('warehouse.orders.print', $order) }}';
            window.open(url, '_blank');
        },

        // Print logs
        async loadPrintLogs() {
            this.loadingPrintLogs = true;
            try {
                const response = await this.request('{{ route('warehouse.orders.print-logs', $order) }}', 'GET');
                if (response.success) {
                    this.printLogs = response.data;
                }
            } finally {
                this.loadingPrintLogs = false;
            }
        },

        async request(url, method, data = {}) {
            try {
                const options = {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                };
                if (method !== 'GET') {
                    options.body = JSON.stringify(data);
                }
                const response = await fetch(url, options);
                return await response.json();
            } catch (error) {
                return { success: false, message: 'خطا در برقراری ارتباط' };
            }
        },

        showNotification(message, success) {
            this.toastMessage = message;
            this.toastSuccess = success;
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);
        },

        async sendToAmadast() {
            this.sendingToAmadast = true;
            try {
                const response = await this.request('{{ route("warehouse.orders.send-to-amadast", $order) }}', 'POST');
                this.showNotification(response.message, response.success);
                if (response.success) setTimeout(() => location.reload(), 1500);
            } finally {
                this.sendingToAmadast = false;
            }
        },

        async updateAmadastTracking() {
            this.updatingTracking = true;
            try {
                const response = await this.request('{{ route("warehouse.orders.update-tracking", $order) }}', 'POST');
                this.showNotification(response.message, response.success);
                if (response.success) setTimeout(() => location.reload(), 1500);
            } finally {
                this.updatingTracking = false;
            }
        }
    }
}
</script>
@endsection
