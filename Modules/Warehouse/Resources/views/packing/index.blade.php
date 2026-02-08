@extends('layouts.admin')

@section('title', 'بسته‌بندی سفارشات')

@push('styles')
<style>
    #qr-reader {
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
    }
    #qr-reader video {
        border-radius: 0.5rem;
    }
    #qr-reader__scan_region {
        background: transparent !important;
    }
    #qr-reader__dashboard {
        padding: 10px !important;
    }
    #qr-reader__dashboard_section_csr button {
        background-color: #3b82f6 !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 0.5rem !important;
        color: white !important;
        cursor: pointer !important;
    }
    .scanned-item {
        animation: flash-green 0.5s ease-out;
    }
    @keyframes flash-green {
        0% { background-color: rgba(34, 197, 94, 0.5); }
        100% { background-color: transparent; }
    }
    .scan-error {
        animation: flash-red 0.5s ease-out;
    }
    @keyframes flash-red {
        0% { background-color: rgba(239, 68, 68, 0.5); }
        100% { background-color: transparent; }
    }
</style>
@endpush

@section('main')
<div class="p-6" x-data="packingStation()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ایستگاه بسته‌بندی</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">اسکن بارکد سفارش و محصولات</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('warehouse.queue') }}"
               class="flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                صف آماده‌سازی
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Scanner Section -->
        <div class="space-y-6">
            <!-- Barcode Scanner -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900 dark:text-white">اسکنر بارکد</h2>
                    <div class="flex items-center gap-2">
                        <button @click="toggleScanner()"
                                :class="scannerActive ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                                class="flex items-center gap-2 px-4 py-2 text-white rounded-lg transition">
                            <svg x-show="!scannerActive" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="scannerActive" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span x-text="scannerActive ? 'بستن دوربین' : 'باز کردن دوربین'"></span>
                        </button>
                    </div>
                </div>

                <!-- Camera Scanner -->
                <div x-show="scannerActive" class="mb-4">
                    <div id="qr-reader"></div>
                </div>

                <!-- Manual Input -->
                <div class="flex gap-2">
                    <input type="text"
                           x-model="manualBarcode"
                           @keyup.enter="processBarcode(manualBarcode)"
                           placeholder="یا بارکد را دستی وارد کنید..."
                           class="flex-1 bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500">
                    <button @click="processBarcode(manualBarcode)"
                            :disabled="!manualBarcode"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white rounded-lg transition">
                        ثبت
                    </button>
                </div>

                <!-- Scan Mode Indicator -->
                <div class="mt-4 p-3 rounded-lg"
                     :class="currentOrder ? 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700' : 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700'">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full animate-pulse"
                             :class="currentOrder ? 'bg-emerald-500' : 'bg-blue-500'"></div>
                        <span class="text-sm" :class="currentOrder ? 'text-emerald-700 dark:text-emerald-300' : 'text-blue-700 dark:text-blue-300'"
                              x-text="currentOrder ? 'در حال اسکن محصولات سفارش #' + currentOrder.order_number : 'منتظر اسکن بارکد سفارش...'"></span>
                    </div>
                </div>
            </div>

            <!-- Last Scan Result -->
            <div x-show="lastScanResult" x-transition class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">آخرین اسکن</h3>
                <div class="p-3 rounded-lg"
                     :class="lastScanResult?.success ? 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700' : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700'">
                    <div class="flex items-center gap-3">
                        <div :class="lastScanResult?.success ? 'text-green-500' : 'text-red-500'">
                            <svg x-show="lastScanResult?.success" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg x-show="!lastScanResult?.success" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium" :class="lastScanResult?.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'"
                               x-text="lastScanResult?.message"></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="'کد: ' + lastScanResult?.code"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weight Entry -->
            <div x-show="currentOrder && allItemsScanned" x-transition class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">ثبت وزن بسته</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">همه محصولات اسکن شدند. حالا وزن بسته را وارد کنید.</p>

                <div class="space-y-4">
                    @if(isset($settings['default_carton_weight']) && $settings['default_carton_weight'] > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-500">وزن کارتن پیش‌فرض:</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $settings['default_carton_weight'] }} گرم</span>
                    </div>
                    @endif

                    <div>
                        <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">وزن بسته (گرم)</label>
                        <input type="number"
                               x-model="packageWeight"
                               placeholder="وزن نهایی بسته"
                               min="0"
                               class="w-full bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-200 text-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <button @click="completeOrder()"
                            :disabled="!packageWeight || completing"
                            class="w-full px-4 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white font-medium rounded-lg transition">
                        <span x-text="completing ? 'در حال ثبت...' : 'تکمیل بسته‌بندی'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Current Order Section -->
        <div class="space-y-6">
            <!-- Order Details -->
            <div x-show="currentOrder" x-transition class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">سفارش <span x-text="'#' + currentOrder?.order_number"></span></h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="currentOrder?.customer_name"></p>
                    </div>
                    <button @click="cancelOrder()"
                            class="px-3 py-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm transition">
                        لغو
                    </button>
                </div>

                <!-- Progress -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="text-gray-500 dark:text-gray-400">پیشرفت اسکن</span>
                        <span class="text-gray-900 dark:text-gray-200">
                            <span x-text="scannedItemsCount"></span> / <span x-text="totalItemsCount"></span>
                        </span>
                    </div>
                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all duration-300"
                             :style="'width: ' + (totalItemsCount > 0 ? (scannedItemsCount / totalItemsCount * 100) : 0) + '%'"></div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[400px] overflow-y-auto">
                    <template x-for="item in orderItems" :key="item.id">
                        <div class="p-4 flex items-center gap-4"
                             :class="item.is_scanned ? 'bg-green-50 dark:bg-green-900/20' : ''">
                            <!-- Status Icon -->
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                 :class="item.is_scanned ? 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-500'">
                                <svg x-show="item.is_scanned" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span x-show="!item.is_scanned" x-text="item.remaining_quantity" class="text-sm font-medium"></span>
                            </div>

                            <!-- Product Info -->
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-gray-200 truncate" x-text="item.name"></p>
                                <div class="flex items-center gap-3 mt-1 text-sm">
                                    <span class="text-gray-500 dark:text-gray-500">SKU: <span class="text-gray-700 dark:text-gray-400" x-text="item.sku || '-'"></span></span>
                                    <span class="text-gray-500 dark:text-gray-500">تعداد: <span class="text-gray-700 dark:text-gray-400" x-text="item.quantity"></span></span>
                                </div>
                                <div x-show="item.bin_location" class="mt-1">
                                    <span class="text-xs bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded" x-text="'قفسه: ' + item.bin_location"></span>
                                </div>
                            </div>

                            <!-- Scanned Count -->
                            <div class="text-left">
                                <div class="text-lg font-bold"
                                     :class="item.scanned_quantity >= item.quantity ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300'">
                                    <span x-text="item.scanned_quantity"></span>/<span x-text="item.quantity"></span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-500">اسکن شده</div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- No Order Selected -->
            <div x-show="!currentOrder" class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center shadow-sm">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">سفارشی انتخاب نشده</h3>
                <p class="text-gray-500 dark:text-gray-500">بارکد سفارش را اسکن کنید یا شماره سفارش را وارد کنید</p>
            </div>

            <!-- Recent Completed Orders -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">بسته‌بندی‌های اخیر</h3>
                </div>
                <div class="p-4 space-y-3">
                    @forelse($recentPackedOrders as $order)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-200">#{{ $order->order_number }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $order->customer_full_name }}</p>
                        </div>
                        <div class="text-left">
                            @if($order->weight_verified)
                            <span class="text-green-600 dark:text-green-400 text-sm">وزن تایید</span>
                            @else
                            <span class="text-yellow-600 dark:text-yellow-400 text-sm">اختلاف وزن</span>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-500">{{ $order->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-gray-500 dark:text-gray-500 py-4">بسته‌بندی ثبت نشده</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Weight Verification Modal -->
    <div x-show="showWeightWarning"
         x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md mx-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-500/20 rounded-full">
                    <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">هشدار اختلاف وزن!</h3>
            </div>
            <div class="space-y-3 mb-6">
                <p class="text-gray-700 dark:text-gray-300">وزن ثبت شده با وزن مورد انتظار مطابقت ندارد.</p>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">وزن مورد انتظار:</span>
                        <span class="text-gray-900 dark:text-gray-200 font-medium" x-text="expectedWeight + ' گرم'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">وزن ثبت شده:</span>
                        <span class="text-gray-900 dark:text-gray-200 font-medium" x-text="packageWeight + ' گرم'"></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 dark:border-gray-600 pt-2">
                        <span class="text-gray-500 dark:text-gray-400">اختلاف:</span>
                        <span class="text-yellow-600 dark:text-yellow-400 font-bold" x-text="weightDifferencePercent + '%'"></span>
                    </div>
                </div>
                <p class="text-yellow-600 dark:text-yellow-400 text-sm">احتمالاً محصولی جا مانده یا اشتباه اضافه شده است!</p>
            </div>
            <div class="flex gap-3">
                <button @click="showWeightWarning = false"
                        class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white rounded-lg transition">
                    بررسی مجدد
                </button>
                <button @click="forceCompleteOrder()"
                        class="flex-1 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">
                    ثبت با اختلاف
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div x-show="showSuccessModal"
         x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md mx-4 text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">بسته‌بندی تکمیل شد!</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6" x-text="'سفارش #' + completedOrderNumber + ' با موفقیت بسته‌بندی شد.'"></p>
            <button @click="closeSuccessModal()"
                    class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                سفارش بعدی
            </button>
        </div>
    </div>

    <!-- Audio elements for feedback -->
    <audio id="scanSuccessSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleAhWpr/F" type="audio/wav">
    </audio>
    <audio id="scanErrorSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRl9vT19teleAAWVZZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDb" type="audio/wav">
    </audio>
</div>

<!-- html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
function packingStation() {
    return {
        scannerActive: false,
        html5QrCode: null,
        manualBarcode: '',
        currentOrder: null,
        orderItems: [],
        lastScanResult: null,
        packageWeight: '',
        completing: false,

        // Modal states
        showWeightWarning: false,
        showSuccessModal: false,
        completedOrderNumber: '',
        expectedWeight: 0,
        weightDifferencePercent: 0,

        get scannedItemsCount() {
            return this.orderItems.filter(item => item.is_scanned).length;
        },

        get totalItemsCount() {
            return this.orderItems.length;
        },

        get allItemsScanned() {
            return this.orderItems.length > 0 && this.orderItems.every(item => item.is_scanned);
        },

        async toggleScanner() {
            if (this.scannerActive) {
                await this.stopScanner();
            } else {
                await this.startScanner();
            }
        },

        async startScanner() {
            try {
                this.html5QrCode = new Html5Qrcode("qr-reader");

                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 150 },
                    aspectRatio: 1.777,
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E
                    ]
                };

                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    config,
                    (decodedText) => this.onScanSuccess(decodedText),
                    (errorMessage) => {
                        // Ignore continuous scan errors
                    }
                );

                this.scannerActive = true;
            } catch (err) {
                console.error('Failed to start scanner:', err);
                alert('خطا در دسترسی به دوربین. لطفاً دسترسی دوربین را بررسی کنید.');
            }
        },

        async stopScanner() {
            if (this.html5QrCode) {
                try {
                    await this.html5QrCode.stop();
                } catch (err) {
                    console.error('Failed to stop scanner:', err);
                }
            }
            this.scannerActive = false;
        },

        onScanSuccess(decodedText) {
            // Prevent duplicate scans
            if (this.lastScanResult?.code === decodedText &&
                Date.now() - this.lastScanResult?.timestamp < 2000) {
                return;
            }

            this.processBarcode(decodedText);
        },

        async processBarcode(barcode) {
            if (!barcode || barcode.trim() === '') return;

            barcode = barcode.trim();
            this.manualBarcode = '';

            try {
                const response = await fetch('{{ route("warehouse.packing.scan") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        barcode: barcode,
                        order_id: this.currentOrder?.id || null
                    })
                });

                const result = await response.json();

                this.lastScanResult = {
                    success: result.success,
                    message: result.message,
                    code: barcode,
                    timestamp: Date.now()
                };

                if (result.success) {
                    this.playSound('success');

                    if (result.type === 'order') {
                        // New order scanned
                        this.currentOrder = result.order;
                        this.orderItems = result.items.map(item => ({
                            ...item,
                            scanned_quantity: 0,
                            is_scanned: false,
                            remaining_quantity: item.quantity
                        }));
                    } else if (result.type === 'product') {
                        // Product scanned
                        const itemIndex = this.orderItems.findIndex(i => i.id === result.item_id);
                        if (itemIndex !== -1) {
                            this.orderItems[itemIndex].scanned_quantity++;
                            this.orderItems[itemIndex].remaining_quantity--;
                            if (this.orderItems[itemIndex].scanned_quantity >= this.orderItems[itemIndex].quantity) {
                                this.orderItems[itemIndex].is_scanned = true;
                            }
                        }
                    }
                } else {
                    this.playSound('error');
                }
            } catch (error) {
                console.error('Scan error:', error);
                this.lastScanResult = {
                    success: false,
                    message: 'خطا در پردازش بارکد',
                    code: barcode,
                    timestamp: Date.now()
                };
                this.playSound('error');
            }
        },

        playSound(type) {
            const audio = document.getElementById(type === 'success' ? 'scanSuccessSound' : 'scanErrorSound');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(() => {});
            }

            // Also vibrate on mobile
            if (navigator.vibrate) {
                navigator.vibrate(type === 'success' ? 100 : [100, 50, 100]);
            }
        },

        cancelOrder() {
            this.currentOrder = null;
            this.orderItems = [];
            this.packageWeight = '';
            this.lastScanResult = null;
        },

        async completeOrder() {
            if (!this.packageWeight || !this.currentOrder) return;

            this.completing = true;

            try {
                const response = await fetch('{{ route("warehouse.packing.complete") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        order_id: this.currentOrder.id,
                        package_weight: parseFloat(this.packageWeight),
                        items: this.orderItems.map(i => ({
                            id: i.id,
                            scanned_quantity: i.scanned_quantity
                        }))
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (result.weight_warning) {
                        // Show weight warning modal
                        this.expectedWeight = result.expected_weight;
                        this.weightDifferencePercent = result.difference_percent;
                        this.showWeightWarning = true;
                    } else {
                        // Success
                        this.showOrderComplete();
                    }
                } else {
                    alert(result.message || 'خطا در ثبت بسته‌بندی');
                }
            } catch (error) {
                console.error('Complete error:', error);
                alert('خطا در ارتباط با سرور');
            } finally {
                this.completing = false;
            }
        },

        async forceCompleteOrder() {
            this.showWeightWarning = false;

            try {
                const response = await fetch('{{ route("warehouse.packing.complete") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        order_id: this.currentOrder.id,
                        package_weight: parseFloat(this.packageWeight),
                        force: true,
                        items: this.orderItems.map(i => ({
                            id: i.id,
                            scanned_quantity: i.scanned_quantity
                        }))
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showOrderComplete();
                } else {
                    alert(result.message || 'خطا در ثبت بسته‌بندی');
                }
            } catch (error) {
                console.error('Force complete error:', error);
                alert('خطا در ارتباط با سرور');
            }
        },

        showOrderComplete() {
            this.completedOrderNumber = this.currentOrder.order_number;
            this.showSuccessModal = true;
            this.playSound('success');
        },

        closeSuccessModal() {
            this.showSuccessModal = false;
            this.cancelOrder();
        }
    }
}
</script>
@endsection
