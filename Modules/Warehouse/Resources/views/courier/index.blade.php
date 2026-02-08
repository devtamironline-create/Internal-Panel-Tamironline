@extends('layouts.admin')

@section('title', 'مدیریت پیک')

@section('main')
<div class="p-6" x-data="courierManager()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">مدیریت پیک</h1>
            <p class="text-slate-400 mt-1">سفارشات آماده ارسال با پیک</p>
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
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-100">{{ $stats['pending'] }}</div>
                    <div class="text-sm text-slate-400">در انتظار تخصیص</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-400">{{ $stats['assigned'] }}</div>
                    <div class="text-sm text-slate-400">تخصیص داده شده</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-400">{{ $stats['delivered_today'] }}</div>
                    <div class="text-sm text-slate-400">تحویل امروز</div>
                </div>
            </div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-500/20 rounded-lg">
                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-400">{{ count($couriers) }}</div>
                    <div class="text-sm text-slate-400">پیک‌های فعال</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Pending Orders -->
        <div class="lg:col-span-2 bg-slate-800 rounded-lg">
            <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                <h2 class="font-semibold text-slate-100">سفارشات در انتظار تخصیص</h2>
                <span class="px-3 py-1 bg-orange-900/50 text-orange-400 rounded-full text-sm">
                    {{ $pendingOrders->count() }} سفارش
                </span>
            </div>
            <div class="divide-y divide-slate-700 max-h-[500px] overflow-y-auto">
                @forelse($pendingOrders as $order)
                <div class="p-4 hover:bg-slate-700/50 transition" x-data="{ expanded: false }">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <a href="{{ route('warehouse.orders.show', $order) }}" class="font-bold text-blue-400 hover:text-blue-300">
                                    #{{ $order->order_number }}
                                </a>
                                @if($order->is_packed)
                                <span class="px-2 py-0.5 bg-green-900/50 text-green-400 text-xs rounded">بسته‌بندی شده</span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-300">{{ $order->customer_full_name }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $order->shipping_city ?? $order->billing_city }}</p>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-slate-200">{{ $order->formatted_total }}</p>
                            <p class="text-xs text-slate-500">{{ $order->date_created?->diffForHumans() }}</p>
                        </div>
                    </div>

                    <!-- Assign Form -->
                    <div class="mt-3 pt-3 border-t border-slate-700">
                        <div class="flex gap-2">
                            <select x-model="selectedCourier_{{ $order->id }}"
                                    class="flex-1 bg-slate-700 border-slate-600 rounded-lg text-sm text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">انتخاب پیک...</option>
                                @foreach($couriers as $courier)
                                <option value="{{ json_encode($courier) }}">{{ $courier['name'] }} - {{ $courier['mobile'] }}</option>
                                @endforeach
                                <option value="new">+ پیک جدید</option>
                            </select>
                            <button @click="assignCourier({{ $order->id }})"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg transition">
                                تخصیص
                            </button>
                        </div>

                        <!-- New Courier Form -->
                        <div x-show="selectedCourier_{{ $order->id }} === 'new'" x-transition class="mt-3 space-y-2">
                            <input type="text"
                                   x-model="newCourierName_{{ $order->id }}"
                                   placeholder="نام پیک"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-sm text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <input type="tel"
                                   x-model="newCourierMobile_{{ $order->id }}"
                                   placeholder="شماره موبایل (09...)"
                                   maxlength="11"
                                   class="w-full bg-slate-700 border-slate-600 rounded-lg text-sm text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    <p>سفارشی در انتظار تخصیص نیست</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Couriers Sidebar -->
        <div class="space-y-6">
            <!-- Active Couriers -->
            <div class="bg-slate-800 rounded-lg">
                <div class="p-4 border-b border-slate-700 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-100">پیک‌های فعال</h3>
                    <button @click="showAddCourier = true"
                            class="text-sm text-blue-400 hover:text-blue-300">
                        + افزودن پیک
                    </button>
                </div>
                <div class="divide-y divide-slate-700">
                    @forelse($couriers as $courier)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-200">{{ $courier['name'] }}</p>
                                <p class="text-sm text-slate-400">{{ $courier['mobile'] }}</p>
                            </div>
                            <div class="text-left">
                                <span class="px-2 py-1 bg-blue-900/50 text-blue-400 text-xs rounded">
                                    {{ $courier['active_orders'] ?? 0 }} سفارش فعال
                                </span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-4 text-center text-slate-500 text-sm">
                        پیکی ثبت نشده
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Assigned Today -->
            <div class="bg-slate-800 rounded-lg">
                <div class="p-4 border-b border-slate-700">
                    <h3 class="font-semibold text-slate-100">تخصیص‌های امروز</h3>
                </div>
                <div class="p-4 space-y-3 max-h-[300px] overflow-y-auto">
                    @forelse($assignedToday as $order)
                    <div class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg">
                        <div>
                            <p class="font-medium text-slate-200">#{{ $order->order_number }}</p>
                            <p class="text-xs text-slate-400">{{ $order->courier_name }}</p>
                        </div>
                        <div class="text-left">
                            @if($order->internal_status === 'delivered')
                            <span class="text-green-400 text-xs">تحویل شده</span>
                            @else
                            <span class="text-blue-400 text-xs">در مسیر</span>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-slate-500 text-sm">تخصیصی امروز ثبت نشده</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Add Courier Modal -->
    <div x-show="showAddCourier"
         x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-slate-800 rounded-lg p-6 max-w-md mx-4 w-full">
            <h3 class="text-lg font-semibold text-slate-100 mb-4">افزودن پیک جدید</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">نام و نام خانوادگی</label>
                    <input type="text"
                           x-model="newCourier.name"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">شماره موبایل</label>
                    <input type="tel"
                           x-model="newCourier.mobile"
                           placeholder="09123456789"
                           maxlength="11"
                           class="w-full bg-slate-700 border-slate-600 rounded-lg text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button @click="showAddCourier = false"
                        class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">
                    انصراف
                </button>
                <button @click="saveCourier()"
                        :disabled="!newCourier.name || !newCourier.mobile"
                        class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-800 text-white rounded-lg transition">
                    ذخیره
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
function courierManager() {
    return {
        loading: false,
        showAddCourier: false,
        showToast: false,
        toastMessage: '',
        toastSuccess: true,
        newCourier: { name: '', mobile: '' },

        // Dynamic properties for each order's courier selection
        @foreach($pendingOrders as $order)
        selectedCourier_{{ $order->id }}: '',
        newCourierName_{{ $order->id }}: '',
        newCourierMobile_{{ $order->id }}: '',
        @endforeach

        async refreshOrders() {
            this.loading = true;
            location.reload();
        },

        async assignCourier(orderId) {
            const selected = this['selectedCourier_' + orderId];

            let courierName, courierMobile;

            if (selected === 'new') {
                courierName = this['newCourierName_' + orderId];
                courierMobile = this['newCourierMobile_' + orderId];
            } else if (selected) {
                const courier = JSON.parse(selected);
                courierName = courier.name;
                courierMobile = courier.mobile;
            } else {
                this.showNotification('لطفاً یک پیک انتخاب کنید', false);
                return;
            }

            if (!courierName || !courierMobile) {
                this.showNotification('اطلاعات پیک ناقص است', false);
                return;
            }

            if (!courierMobile.match(/^09[0-9]{9}$/)) {
                this.showNotification('شماره موبایل نامعتبر است', false);
                return;
            }

            try {
                const response = await fetch(`/admin/warehouse/orders/${orderId}/assign-courier`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        courier_name: courierName,
                        courier_mobile: courierMobile,
                        notify_customer: true
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('پیک با موفقیت تخصیص داده شد', true);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotification(result.message || 'خطا در تخصیص پیک', false);
                }
            } catch (error) {
                console.error(error);
                this.showNotification('خطا در ارتباط با سرور', false);
            }
        },

        async saveCourier() {
            if (!this.newCourier.mobile.match(/^09[0-9]{9}$/)) {
                this.showNotification('شماره موبایل نامعتبر است', false);
                return;
            }

            try {
                const response = await fetch('{{ route("warehouse.courier.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(this.newCourier)
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('پیک جدید اضافه شد', true);
                    this.showAddCourier = false;
                    this.newCourier = { name: '', mobile: '' };
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showNotification(result.message || 'خطا در ذخیره پیک', false);
                }
            } catch (error) {
                console.error(error);
                this.showNotification('خطا در ارتباط با سرور', false);
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
</script>
@endsection
