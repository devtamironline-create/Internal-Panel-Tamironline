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
            <p class="text-gray-600 mt-1">اسکن بارکد فاکتور برای انتقال به آماده ارسال</p>
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
                <p class="text-sm text-gray-500 mt-1">بارکد یا شماره سفارش را اسکن کنید تا به مرحله آماده ارسال منتقل شود</p>
            </div>
            <div class="relative">
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

            <!-- Success State -->
            <template x-if="!error && lastOrder">
                <div>
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="font-bold text-green-800" x-text="message"></p>
                                <p class="text-xs text-green-600 mt-0.5">وضعیت: آماده ارسال</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="bg-white rounded-lg p-3 border border-green-100">
                                <p class="text-xs text-gray-500">شماره سفارش</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5" x-text="lastOrder.order_number" dir="ltr"></p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-green-100">
                                <p class="text-xs text-gray-500">مشتری</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5" x-text="lastOrder.customer_name"></p>
                            </div>
                        </div>

                        <!-- Shipping Type Info -->
                        <div class="bg-white rounded-lg p-3 border border-green-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500">نوع ارسال</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5" x-text="lastOrder.shipping_type || 'مشخص نشده'"></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-medium"
                                  :class="lastOrder.shipping_type === 'courier' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                                  x-text="lastOrder.shipping_type === 'courier' ? 'پیک' : 'پست'"></span>
                        </div>

                        <!-- Items -->
                        <template x-if="lastOrder.items && lastOrder.items.length">
                            <div class="mt-3 space-y-1">
                                <template x-for="item in lastOrder.items.slice(0, 4)" :key="item.id">
                                    <div class="flex items-center gap-2 text-xs text-gray-600 bg-white rounded p-2 border border-green-100">
                                        <span class="w-5 h-5 flex items-center justify-center bg-gray-100 rounded text-[10px] font-bold" x-text="item.quantity"></span>
                                        <span class="truncate" x-text="item.product_name"></span>
                                    </div>
                                </template>
                                <template x-if="lastOrder.items.length > 4">
                                    <p class="text-xs text-gray-400 text-center" x-text="'+' + (lastOrder.items.length - 4) + ' مورد دیگر'"></p>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Action Buttons -->
                    <div class="px-6 py-4 bg-green-100/50 border-t border-green-200 flex items-center gap-3">
                        <a :href="'/warehouse/dispatch'" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white text-gray-700 rounded-lg hover:bg-gray-50 border border-gray-200 text-sm font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            پنل ارسال
                        </a>
                        <a :href="'/warehouse/' + lastOrder.id" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white text-gray-700 rounded-lg hover:bg-gray-50 border border-gray-200 text-sm font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            جزئیات سفارش
                        </a>
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
<script>
function scanStation() {
    return {
        barcode: '',
        loading: false,
        message: '',
        error: false,
        lastOrder: null,
        scanCount: 0,
        audioCtx: null,

        initStation() {
            this.$nextTick(() => {
                if (this.$refs.scanInput) this.$refs.scanInput.focus();
            });
        },

        playBeep(success = true) {
            try {
                if (!this.audioCtx) {
                    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                const ctx = this.audioCtx;
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
        }
    };
}
</script>
@endpush
@endsection
