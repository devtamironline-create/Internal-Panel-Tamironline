@extends('layouts.admin')
@section('page-title', 'مدیریت ارسال')
@section('main')
<div class="space-y-6" x-data="dispatchScanner()">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">مدیریت ارسال</h1>
                <p class="text-gray-600 mt-1">ارسال پستی و پیکی سفارشات</p>
            </div>
        </div>
    </div>

    <!-- Barcode Scanner for Dispatch -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-bold text-gray-900">اسکن و ارسال سریع</h2>
                <p class="text-xs text-gray-500">بارکد فاکتور را با بارکدخوان USB اسکن کنید یا دستی وارد کنید</p>
            </div>
            <div class="mr-auto text-sm text-gray-400">
                <span x-text="shipCount"></span> ارسال شده در این نشست
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                <input type="text"
                       x-ref="dispatchScanInput"
                       x-model="barcode"
                       @keydown.enter.prevent="scanAndShip()"
                       :disabled="loading"
                       autofocus
                       placeholder="بارکد سفارش را اسکن کنید..."
                       class="w-full pr-12 pl-4 py-4 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium text-center transition-colors"
                       :class="loading ? 'bg-gray-100 cursor-not-allowed' : ''">
            </div>
            <button @click="startCamera()" type="button"
                    class="flex items-center justify-center w-14 h-14 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shrink-0"
                    title="اسکن با دوربین">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        </div>

        <!-- Camera Scanner -->
        <div x-show="cameraActive" x-cloak class="mt-4">
            <div id="dispatch-barcode-reader" class="rounded-xl overflow-hidden border-2 border-indigo-300"></div>
            <button @click="stopCamera()" type="button" class="mt-2 w-full py-2.5 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">
                بستن دوربین
            </button>
        </div>

        <!-- Result Message -->
        <div x-show="message" x-cloak x-transition class="mt-4 rounded-xl p-4"
             :class="error ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
            <div class="flex items-center gap-3">
                <template x-if="error">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                </template>
                <template x-if="!error">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </template>
                <p class="text-sm font-medium" :class="error ? 'text-red-700' : 'text-green-700'" x-text="message"></p>
            </div>
        </div>

        <!-- Recent Scans Log -->
        <div x-show="recentScans.length > 0" x-cloak class="mt-4">
            <p class="text-xs text-gray-500 mb-2">آخرین اسکن‌ها:</p>
            <div class="space-y-1">
                <template x-for="scan in recentScans" :key="scan.time">
                    <div class="flex items-center gap-2 text-xs py-1 px-2 rounded bg-gray-50">
                        <span class="w-2 h-2 rounded-full shrink-0" :class="scan.success ? 'bg-green-500' : 'bg-red-500'"></span>
                        <span class="text-gray-600" x-text="scan.time"></span>
                        <span class="font-medium text-gray-800" x-text="scan.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b flex gap-0">
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'ready']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'ready' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                آماده ارسال <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $readyCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'shipped']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'shipped' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                ارسال شده <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $shippedCount }}</span>
            </a>
            <a href="{{ route('warehouse.dispatch.index', ['tab' => 'delivered']) }}"
               class="px-6 py-3 text-sm font-medium border-b-2 {{ $tab === 'delivered' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                تحویل شده
            </a>
        </div>

        <div class="p-6">
            @if($orders->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <p>سفارشی در این وضعیت وجود ندارد</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($orders as $order)
                    <div class="border rounded-lg p-4 hover:bg-gray-50" x-data="{ showCourier: false }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div>
                                    <div class="font-bold text-gray-900">{{ $order->order_number }}</div>
                                    <div class="text-sm text-gray-500">{{ $order->customer_name }} - {{ $order->customer_mobile }}</div>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $order->shipping_type === 'courier' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $order->shipping_type === 'courier' ? 'پیک' : 'پست' }}
                                </span>
                                <span class="text-sm text-gray-500">{{ number_format($order->actual_weight ?? $order->total_weight) }} گرم</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($tab === 'ready')
                                    @if($order->shipping_type === 'post')
                                        <button onclick="shipPost({{ $order->id }})" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">ارسال پستی (آمادست)</button>
                                    @else
                                        <button @click="showCourier = !showCourier" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">تخصیص پیک</button>
                                    @endif
                                    <a href="{{ route('warehouse.print.label', $order) }}" target="_blank" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">چاپ برچسب</a>
                                @elseif($tab === 'shipped')
                                    <button onclick="markDelivered({{ $order->id }})" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">تحویل شد</button>
                                    <button onclick="markReturned({{ $order->id }})" class="px-3 py-2 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">مرجوعی</button>
                                    @if($order->tracking_code)
                                        <span class="text-sm text-gray-500" dir="ltr">{{ $order->tracking_code }}</span>
                                    @endif
                                    @if($order->driver_name)
                                        <span class="text-sm text-gray-500">پیک: {{ $order->driver_name }}</span>
                                    @endif
                                @elseif($tab === 'delivered')
                                    <span class="text-sm text-green-600">{{ $order->delivered_at ? \Morilog\Jalali\Jalalian::fromCarbon($order->delivered_at)->format('Y/m/d H:i') : '-' }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Courier Form -->
                        <div x-show="showCourier" x-collapse class="mt-4 pt-4 border-t">
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">نام راننده</label>
                                    <input type="text" id="driver-name-{{ $order->id }}" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="نام راننده">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">تلفن راننده</label>
                                    <input type="text" id="driver-phone-{{ $order->id }}" class="w-full px-3 py-2 border rounded-lg text-sm" dir="ltr" placeholder="09...">
                                </div>
                                <button onclick="shipCourier({{ $order->id }})" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">ثبت و ارسال</button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6">{{ $orders->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const headers = { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function dispatchScanner() {
    return {
        barcode: '',
        loading: false,
        message: '',
        error: false,
        shipCount: 0,
        cameraActive: false,
        html5QrCode: null,
        recentScans: [],

        init() {
            this.$nextTick(() => {
                if (this.$refs.dispatchScanInput) this.$refs.dispatchScanInput.focus();
                this.setupUsbScanner();
            });
        },

        // USB barcode scanner support
        usbBuffer: '',
        usbTimer: null,
        lastKeyTime: 0,

        setupUsbScanner() {
            // USB barcode scanners type very fast (< 50ms between keys) and end with Enter
            // We capture keystrokes globally so even if input loses focus, scanner still works
            document.addEventListener('keydown', (e) => {
                const active = document.activeElement;
                const isDispatchInput = active === this.$refs.dispatchScanInput;

                // If typing in another input (like driver name), don't intercept
                const isOtherInput = active && active !== document.body && !isDispatchInput
                    && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
                if (isOtherInput) return;

                const now = Date.now();
                const timeDiff = now - this.lastKeyTime;
                this.lastKeyTime = now;

                // Enter key = submit the buffer
                if (e.key === 'Enter') {
                    if (this.usbBuffer.length >= 3) {
                        // This is a USB scanner scan (rapid keys + Enter)
                        e.preventDefault();
                        e.stopPropagation();
                        this.barcode = this.usbBuffer;
                        this.usbBuffer = '';
                        clearTimeout(this.usbTimer);
                        this.scanAndShip();
                        return;
                    }
                    // If input is focused and has value, also submit
                    if (isDispatchInput && this.barcode.trim()) {
                        e.preventDefault();
                        this.usbBuffer = '';
                        this.scanAndShip();
                        return;
                    }
                    this.usbBuffer = '';
                    return;
                }

                // Only capture printable characters
                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    // If fast typing (< 80ms) = scanner, if slow = human
                    if (timeDiff < 80 || this.usbBuffer.length === 0) {
                        this.usbBuffer += e.key;
                    } else {
                        // Slow typing - reset buffer
                        this.usbBuffer = e.key;
                    }

                    // Also put it in the input for visual feedback
                    if (!isDispatchInput && this.$refs.dispatchScanInput) {
                        this.$refs.dispatchScanInput.focus();
                    }

                    // Clear buffer after 200ms of no input (scanner is done or user stopped)
                    clearTimeout(this.usbTimer);
                    this.usbTimer = setTimeout(() => {
                        this.usbBuffer = '';
                    }, 200);
                }
            });

            // Keep focus on scan input when clicking empty areas
            document.addEventListener('click', (e) => {
                if (e.target === document.body || e.target.closest('.space-y-6')) {
                    const active = document.activeElement;
                    const isOtherInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT');
                    if (!isOtherInput && this.$refs.dispatchScanInput) {
                        this.$refs.dispatchScanInput.focus();
                    }
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

        addScanLog(message, success) {
            const now = new Date();
            const time = now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.recentScans.unshift({ time, message, success });
            if (this.recentScans.length > 10) this.recentScans.pop();
        },

        async scanAndShip() {
            if (!this.barcode.trim()) return;
            this.loading = true;
            this.message = '';
            this.error = false;

            try {
                const response = await fetch('{{ route("warehouse.dispatch.scan-ship") }}', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ barcode: this.barcode.trim() })
                });

                const data = await response.json();

                if (data.success) {
                    this.message = data.message;
                    this.error = false;
                    this.shipCount++;
                    this.playBeep(true);
                    this.stopCamera();
                    this.addScanLog(data.message, true);
                    // Refresh the page counts after a short delay
                    setTimeout(() => {
                        // Update tab counts without full reload
                        const readyBadge = document.querySelector('a[href*="tab=ready"] .bg-blue-100');
                        if (readyBadge) {
                            const count = parseInt(readyBadge.textContent) - 1;
                            readyBadge.textContent = Math.max(0, count);
                        }
                    }, 300);
                } else {
                    this.message = data.message;
                    this.error = true;
                    this.playBeep(false);
                    this.addScanLog(data.message, false);
                }
            } catch (e) {
                this.message = 'خطا در ارتباط با سرور';
                this.error = true;
                this.playBeep(false);
                this.addScanLog('خطا در ارتباط', false);
            }

            this.barcode = '';
            this.loading = false;
            this.$nextTick(() => {
                if (this.$refs.dispatchScanInput) this.$refs.dispatchScanInput.focus();
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
                this.html5QrCode = new Html5Qrcode("dispatch-barcode-reader", {
                    formatsToSupport,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true }
                });

                // Get container width for responsive qrbox
                const container = document.getElementById('dispatch-barcode-reader');
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
                        this.scanAndShip();
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

function shipPost(orderId) {
    if (!confirm('ارسال از طریق پست (آمادست)؟')) return;
    fetch('/warehouse/' + orderId + '/ship-post', { method: 'POST', headers })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function shipCourier(orderId) {
    const name = document.getElementById('driver-name-' + orderId).value;
    const phone = document.getElementById('driver-phone-' + orderId).value;
    if (!name) { alert('نام راننده الزامی است.'); return; }
    fetch('/warehouse/' + orderId + '/ship-courier', { method: 'POST', headers, body: JSON.stringify({ driver_name: name, driver_phone: phone }) })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function markDelivered(orderId) {
    if (!confirm('تایید تحویل؟')) return;
    fetch('/warehouse/' + orderId + '/delivered', { method: 'POST', headers })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}

function markReturned(orderId) {
    const notes = prompt('دلیل مرجوعی:');
    fetch('/warehouse/' + orderId + '/returned', { method: 'POST', headers, body: JSON.stringify({ notes: notes }) })
        .then(r => r.json()).then(d => { alert(d.message); if (d.success) location.reload(); });
}
</script>
@endpush
@endsection
