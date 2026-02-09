@extends('layouts.admin')
@section('page-title', 'ایستگاه اسکن')
@section('main')
<div x-data="scanStation()" x-init="initStation()" class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">ایستگاه اسکن</h1>
            <p class="text-gray-600 mt-1">اسکن بارکد فاکتور برای شروع آماده‌سازی</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto space-y-6">

        <!-- Scanner Card -->
        <div class="bg-white rounded-2xl shadow-sm p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-brand-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">اسکن بارکد فاکتور</h2>
                <p class="text-sm text-gray-500 mt-1">بارکد یا شماره سفارش را اسکن کنید تا سفارش پیدا شود</p>
            </div>

            <!-- Scan Input + Camera Button -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                    <input type="text"
                           x-ref="scanInput"
                           x-model="barcode"
                           @keydown.enter.prevent="scanOrder()"
                           :disabled="loading"
                           autofocus
                           placeholder="بارکد سفارش را اسکن کنید..."
                           class="w-full pr-12 pl-4 py-5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-xl font-medium text-center transition-colors"
                           :class="loading ? 'bg-gray-100 cursor-not-allowed' : ''">
                </div>
                <button @click="startCamera()" type="button"
                        class="flex items-center justify-center w-16 h-16 bg-orange-600 text-white rounded-xl hover:bg-orange-700 transition-colors shrink-0"
                        title="اسکن با دوربین">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </button>
            </div>

            <!-- Camera Scanner -->
            <div x-show="cameraActive" x-cloak class="mt-4">
                <div id="packing-barcode-reader" class="rounded-xl overflow-hidden border-2 border-orange-300"></div>
                <button @click="stopCamera()" type="button" class="mt-2 w-full py-2.5 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">
                    بستن دوربین
                </button>
            </div>
        </div>

        <!-- Result Card -->
        <div x-show="message" x-cloak x-transition class="rounded-2xl shadow-sm overflow-hidden"
             :class="error ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">

            <!-- Error State -->
            <template x-if="error">
                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <p class="text-sm font-medium text-red-700" x-text="message"></p>
                    </div>
                </div>
            </template>

            <!-- Success State - Redirecting -->
            <template x-if="!error && lastOrder">
                <div class="p-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-green-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <div>
                            <p class="font-bold text-green-800" x-text="'سفارش ' + lastOrder.order_number + ' - ' + lastOrder.customer_name"></p>
                            <p class="text-xs text-green-600 mt-0.5">در حال انتقال به صفحه سفارش...</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Scan Counter -->
        <div class="text-center text-sm text-gray-400">
            <span x-text="scanCount"></span> سفارش اسکن شده در این نشست
        </div>

    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function scanStation() {
    return {
        barcode: '',
        loading: false,
        message: '',
        error: false,
        lastOrder: null,
        scanCount: 0,
        cameraActive: false,
        html5QrCode: null,

        // USB barcode scanner support
        usbBuffer: '',
        usbTimer: null,
        lastKeyTime: 0,

        initStation() {
            this.$nextTick(() => {
                if (this.$refs.scanInput) this.$refs.scanInput.focus();
                this.setupUsbScanner();
            });
        },

        setupUsbScanner() {
            // USB scanners type very fast (< 50ms between keys) and end with Enter
            document.addEventListener('keydown', (e) => {
                const active = document.activeElement;
                const isScanInput = active === this.$refs.scanInput;

                // If typing in another input, don't intercept
                const isOtherInput = active && active !== document.body && !isScanInput
                    && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
                if (isOtherInput) return;

                const now = Date.now();
                const timeDiff = now - this.lastKeyTime;
                this.lastKeyTime = now;

                if (e.key === 'Enter') {
                    if (this.usbBuffer.length >= 3) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.barcode = this.usbBuffer;
                        this.usbBuffer = '';
                        clearTimeout(this.usbTimer);
                        this.scanOrder();
                        return;
                    }
                    if (isScanInput && this.barcode.trim()) {
                        e.preventDefault();
                        this.usbBuffer = '';
                        this.scanOrder();
                        return;
                    }
                    this.usbBuffer = '';
                    return;
                }

                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    if (timeDiff < 80 || this.usbBuffer.length === 0) {
                        this.usbBuffer += e.key;
                    } else {
                        this.usbBuffer = e.key;
                    }

                    if (!isScanInput && this.$refs.scanInput) {
                        this.$refs.scanInput.focus();
                    }

                    clearTimeout(this.usbTimer);
                    this.usbTimer = setTimeout(() => { this.usbBuffer = ''; }, 200);
                }
            });

            document.addEventListener('click', (e) => {
                const active = document.activeElement;
                const isOtherInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA');
                if (!isOtherInput && this.$refs.scanInput) {
                    this.$refs.scanInput.focus();
                }
            });
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
        },

        async scanOrder() {
            if (!this.barcode.trim()) return;
            this.loading = true;
            this.message = '';
            this.error = false;
            this.lastOrder = null;

            try {
                const response = await fetch('{{ route("warehouse.packing.scan-order") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ barcode: this.barcode.trim() })
                });

                const data = await response.json();

                if (data.success) {
                    this.lastOrder = data.order;
                    this.message = data.message;
                    this.error = false;
                    this.scanCount++;
                    this.playBeep(true);
                    this.stopCamera();
                    // Auto-redirect to order show page
                    setTimeout(() => {
                        window.location.href = '/warehouse/' + data.order.id;
                    }, 800);
                } else {
                    this.message = data.message;
                    this.error = true;
                    this.playBeep(false);
                }
            } catch (e) {
                this.message = 'خطا در ارتباط با سرور';
                this.error = true;
                this.playBeep(false);
            }

            this.barcode = '';
            this.loading = false;

            this.$nextTick(() => {
                if (this.$refs.scanInput) this.$refs.scanInput.focus();
            });
        },

        async startCamera() {
            this.cameraActive = true;
            await this.$nextTick();

            try {
                const formatsToSupport = [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                ];
                this.html5QrCode = new Html5Qrcode("packing-barcode-reader", {
                    formatsToSupport,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true }
                });

                const container = document.getElementById('packing-barcode-reader');
                const containerWidth = container ? container.offsetWidth : 400;
                const qrboxWidth = Math.min(containerWidth - 40, 500);

                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: { width: qrboxWidth, height: 150 },
                        aspectRatio: 1.777,
                        videoConstraints: {
                            facingMode: "environment",
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        }
                    },
                    (decodedText) => {
                        this.barcode = decodedText;
                        this.stopCamera();
                        this.scanOrder();
                    },
                    () => {}
                );
            } catch (err) {
                this.message = 'دسترسی به دوربین ممکن نیست. لطفا دسترسی دوربین را فعال کنید.';
                this.error = true;
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
        }
    };
}
</script>
@endpush
@endsection
