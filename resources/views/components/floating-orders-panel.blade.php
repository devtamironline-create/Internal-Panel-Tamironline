@php
    use Modules\Warehouse\Http\Middleware\CheckWarehouseRole;
    $canSeePanel = auth()->check() && CheckWarehouseRole::canSeeFloatingOrders(auth()->user());
@endphp

@if($canSeePanel)
<div x-data="floatingOrdersPanel()" x-init="init()" class="fixed left-0 top-1/2 -translate-y-1/2 z-40">
    <!-- Toggle Button -->
    <button
        @click="togglePanel()"
        class="absolute left-0 top-1/2 -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-l-none rounded-r-lg shadow-lg transition-all duration-300"
        :class="{ 'translate-x-80': isOpen }"
    >
        <svg x-show="!isOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <svg x-show="isOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
        </svg>
        <span x-show="!isOpen && totalOrders > 0" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" x-text="totalOrders"></span>
    </button>

    <!-- Panel -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="-translate-x-full opacity-0"
        class="w-80 h-[80vh] bg-white dark:bg-gray-800 shadow-2xl rounded-r-xl overflow-hidden flex flex-col border-r-4 border-blue-600"
    >
        <!-- Header -->
        <div class="bg-blue-600 text-white p-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="font-bold">سفارشات فعال</h3>
            </div>
            <div class="flex items-center gap-2">
                <button @click="refresh()" :disabled="loading" class="p-1 hover:bg-blue-500 rounded transition">
                    <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
                <button @click="isOpen = false" class="p-1 hover:bg-blue-500 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
                @click="activeTab = 'pending'"
                :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'pending', 'text-gray-500': activeTab !== 'pending' }"
                class="flex-1 py-2 text-xs font-medium transition"
            >
                در انتظار
                <span x-show="counts.pending > 0" class="bg-yellow-500 text-white text-xs px-1.5 py-0.5 rounded-full mr-1" x-text="counts.pending"></span>
            </button>
            <button
                @click="activeTab = 'processing'"
                :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'processing', 'text-gray-500': activeTab !== 'processing' }"
                class="flex-1 py-2 text-xs font-medium transition"
            >
                در حال پردازش
                <span x-show="counts.processing > 0" class="bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded-full mr-1" x-text="counts.processing"></span>
            </button>
            <button
                @click="activeTab = 'packed'"
                :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'packed', 'text-gray-500': activeTab !== 'packed' }"
                class="flex-1 py-2 text-xs font-medium transition"
            >
                بسته‌بندی
                <span x-show="counts.packed > 0" class="bg-green-500 text-white text-xs px-1.5 py-0.5 rounded-full mr-1" x-text="counts.packed"></span>
            </button>
        </div>

        <!-- Orders List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading State -->
            <div x-show="loading && orders.length === 0" class="p-8 text-center">
                <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-2"></div>
                <p class="text-sm text-gray-500">در حال بارگذاری...</p>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && filteredOrders.length === 0" class="p-8 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">سفارشی یافت نشد</p>
            </div>

            <!-- Orders -->
            <template x-for="order in filteredOrders" :key="order.id">
                <a :href="'/admin/warehouse/orders/' + order.id"
                   class="block p-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-blue-600 dark:text-blue-400 text-sm" x-text="'#' + order.order_number"></span>
                                <span
                                    class="px-1.5 py-0.5 text-xs rounded-full"
                                    :class="getStatusClass(order.internal_status)"
                                    x-text="order.internal_status_label"
                                ></span>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 truncate" x-text="order.customer_name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="order.items_count + ' محصول - ' + order.total"></p>
                        </div>
                        <div class="text-left">
                            <p class="text-xs text-gray-400" x-text="order.date"></p>
                            <div x-show="order.is_printed" class="text-green-500 mt-1" title="چاپ شده">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
            </template>
        </div>

        <!-- Footer -->
        <div class="p-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('warehouse.orders.index') }}"
               class="block w-full text-center py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition">
                مشاهده همه سفارشات
            </a>
        </div>
    </div>
</div>

<script>
function floatingOrdersPanel() {
    return {
        isOpen: false,
        loading: false,
        orders: [],
        activeTab: 'pending',
        counts: {
            pending: 0,
            processing: 0,
            packed: 0
        },
        refreshInterval: null,

        init() {
            // Load saved state
            this.isOpen = localStorage.getItem('floatingOrdersPanelOpen') === 'true';

            // Initial load
            this.refresh();

            // Auto refresh every 30 seconds
            this.refreshInterval = setInterval(() => {
                this.refresh();
            }, 30000);
        },

        togglePanel() {
            this.isOpen = !this.isOpen;
            localStorage.setItem('floatingOrdersPanelOpen', this.isOpen);

            if (this.isOpen && this.orders.length === 0) {
                this.refresh();
            }
        },

        get filteredOrders() {
            return this.orders.filter(order => {
                if (this.activeTab === 'pending') {
                    return ['new', 'confirmed'].includes(order.internal_status);
                } else if (this.activeTab === 'processing') {
                    return ['picking', 'packing'].includes(order.internal_status);
                } else if (this.activeTab === 'packed') {
                    return ['packed'].includes(order.internal_status);
                }
                return true;
            });
        },

        get totalOrders() {
            return this.counts.pending + this.counts.processing + this.counts.packed;
        },

        async refresh() {
            this.loading = true;
            try {
                const response = await fetch('/admin/warehouse/floating-orders', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    this.orders = data.orders;
                    this.counts = data.counts;
                }
            } catch (error) {
                console.error('Error loading orders:', error);
            } finally {
                this.loading = false;
            }
        },

        getStatusClass(status) {
            const classes = {
                'new': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-400',
                'confirmed': 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400',
                'picking': 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-400',
                'packing': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400',
                'packed': 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400',
                'shipped': 'bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-400',
                'delivered': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400',
            };
            return classes[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
        }
    }
}
</script>
@endif
