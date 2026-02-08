@extends('layouts.admin')

@section('title', 'صف آماده‌سازی')

@section('main')
<div class="p-6" x-data="preparationQueue()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">صف آماده‌سازی</h1>
            <p class="text-slate-400 mt-1">سفارشات در انتظار پرینت و آماده‌سازی</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="refreshOrders()"
                    :disabled="loading"
                    class="flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                <svg class="w-4 h-4" :class="loading && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                بروزرسانی
            </button>
            <a href="{{ route('warehouse.packing.index') }}"
               class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                بسته‌بندی
            </a>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-100">{{ $stats['total_in_queue'] }}</div>
                    <div class="text-sm text-slate-400">در صف</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-red-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-red-400">{{ $stats['overdue'] }}</div>
                    <div class="text-sm text-slate-400">تاخیر دارند</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-400">{{ $stats['post_orders'] }}</div>
                    <div class="text-sm text-slate-400">ارسال پستی</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-cyan-400">{{ $stats['courier_orders'] }}</div>
                    <div class="text-sm text-slate-400">ارسال با پیک</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Sections -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Post Orders (1 hour deadline) -->
        <div class="bg-slate-800 rounded-lg">
            <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-100">سفارشات پستی</h2>
                        <p class="text-xs text-slate-400">ددلاین: {{ $settings['post_deadline_minutes'] }} دقیقه</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-purple-900/50 text-purple-400 rounded-full text-sm font-medium">
                    {{ count($postOrders) }} سفارش
                </span>
            </div>
            <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                @forelse($postOrders as $order)
                <div class="order-card bg-slate-700/50 rounded-lg p-4 hover:bg-slate-700 transition cursor-pointer"
                     x-data="orderTimer({{ $order->date_created?->timestamp ?? 0 }}, {{ $settings['post_deadline_minutes'] }})"
                     x-init="startTimer()"
                     @click="window.location.href='{{ route('warehouse.orders.show', $order) }}'">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-100">#{{ $order->order_number }}</span>
                                @if($order->is_printed)
                                <span class="px-2 py-0.5 bg-green-900/50 text-green-400 text-xs rounded">پرینت شده</span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-400 mt-1">{{ $order->customer_full_name }}</p>
                        </div>
                        <!-- Timer -->
                        <div class="text-left">
                            <div class="font-mono text-lg font-bold"
                                 :class="isOverdue ? 'text-red-400' : (isWarning ? 'text-yellow-400' : 'text-green-400')"
                                 x-text="displayTime"></div>
                            <div class="text-xs" :class="isOverdue ? 'text-red-400' : 'text-slate-500'"
                                 x-text="isOverdue ? 'تاخیر!' : 'باقی‌مانده'"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">{{ $order->items_count }} محصول</span>
                        <span class="text-slate-300">{{ $order->formatted_total }}</span>
                    </div>
                    <!-- Progress bar -->
                    <div class="mt-3 h-1.5 bg-slate-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-1000"
                             :class="isOverdue ? 'bg-red-500' : (isWarning ? 'bg-yellow-500' : 'bg-green-500')"
                             :style="'width: ' + Math.min(100, progressPercent) + '%'"></div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p>سفارش پستی در صف نیست</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Courier Orders (7 hours deadline) -->
        <div class="bg-slate-800 rounded-lg">
            <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-cyan-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-100">سفارشات پیک</h2>
                        <p class="text-xs text-slate-400">ددلاین: {{ $settings['courier_deadline_minutes'] }} دقیقه</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-cyan-900/50 text-cyan-400 rounded-full text-sm font-medium">
                    {{ count($courierOrders) }} سفارش
                </span>
            </div>
            <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                @forelse($courierOrders as $order)
                <div class="order-card bg-slate-700/50 rounded-lg p-4 hover:bg-slate-700 transition cursor-pointer"
                     x-data="orderTimer({{ $order->date_created?->timestamp ?? 0 }}, {{ $settings['courier_deadline_minutes'] }})"
                     x-init="startTimer()"
                     @click="window.location.href='{{ route('warehouse.orders.show', $order) }}'">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-100">#{{ $order->order_number }}</span>
                                @if($order->is_printed)
                                <span class="px-2 py-0.5 bg-green-900/50 text-green-400 text-xs rounded">پرینت شده</span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-400 mt-1">{{ $order->customer_full_name }}</p>
                        </div>
                        <!-- Timer -->
                        <div class="text-left">
                            <div class="font-mono text-lg font-bold"
                                 :class="isOverdue ? 'text-red-400' : (isWarning ? 'text-yellow-400' : 'text-green-400')"
                                 x-text="displayTime"></div>
                            <div class="text-xs" :class="isOverdue ? 'text-red-400' : 'text-slate-500'"
                                 x-text="isOverdue ? 'تاخیر!' : 'باقی‌مانده'"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">{{ $order->items_count }} محصول</span>
                        <span class="text-slate-300">{{ $order->formatted_total }}</span>
                    </div>
                    <!-- Progress bar -->
                    <div class="mt-3 h-1.5 bg-slate-600 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-1000"
                             :class="isOverdue ? 'bg-red-500' : (isWarning ? 'bg-yellow-500' : 'bg-green-500')"
                             :style="'width: ' + Math.min(100, progressPercent) + '%'"></div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                    <p>سفارش پیک در صف نیست</p>
                </div>
                @endforelse
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
// Smart timer component with countdown
function orderTimer(orderCreatedTimestamp, deadlineMinutes) {
    return {
        displayTime: '--:--:--',
        isOverdue: false,
        isWarning: false,
        progressPercent: 0,
        interval: null,
        deadlineSeconds: deadlineMinutes * 60,

        startTimer() {
            if (!orderCreatedTimestamp) {
                this.displayTime = '--:--:--';
                return;
            }

            const updateTime = () => {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - orderCreatedTimestamp;
                const remaining = this.deadlineSeconds - elapsed;

                // Calculate progress (how much time has passed)
                this.progressPercent = Math.min(100, (elapsed / this.deadlineSeconds) * 100);

                // Warning when 80% of time has passed
                this.isWarning = this.progressPercent >= 80 && this.progressPercent < 100;
                this.isOverdue = remaining <= 0;

                if (remaining <= 0) {
                    // Show overdue time
                    const overdueSeconds = Math.abs(remaining);
                    const hours = Math.floor(overdueSeconds / 3600);
                    const minutes = Math.floor((overdueSeconds % 3600) / 60);
                    const seconds = overdueSeconds % 60;

                    this.displayTime = '-' + [
                        hours.toString().padStart(2, '0'),
                        minutes.toString().padStart(2, '0'),
                        seconds.toString().padStart(2, '0')
                    ].join(':');
                } else {
                    // Show remaining time
                    const hours = Math.floor(remaining / 3600);
                    const minutes = Math.floor((remaining % 3600) / 60);
                    const seconds = remaining % 60;

                    this.displayTime = [
                        hours.toString().padStart(2, '0'),
                        minutes.toString().padStart(2, '0'),
                        seconds.toString().padStart(2, '0')
                    ].join(':');
                }
            };

            updateTime();
            this.interval = setInterval(updateTime, 1000);
        },

        destroy() {
            if (this.interval) clearInterval(this.interval);
        }
    }
}

function preparationQueue() {
    return {
        loading: false,
        showToast: false,
        toastMessage: '',
        toastSuccess: true,

        async refreshOrders() {
            this.loading = true;
            try {
                // Just reload the page for now
                location.reload();
            } finally {
                this.loading = false;
            }
        },

        showNotification(message, success) {
            this.toastMessage = message;
            this.toastSuccess = success;
            this.showToast = true;
            setTimeout(() => this.showToast = false, 4000);
        }
    }
}

// Auto-refresh every 30 seconds
setInterval(() => {
    // Only refresh if no modals are open
    if (!document.querySelector('[x-show="showToast"]')?.classList.contains('show')) {
        // Could implement AJAX refresh here instead of full page reload
    }
}, 30000);
</script>
@endsection
