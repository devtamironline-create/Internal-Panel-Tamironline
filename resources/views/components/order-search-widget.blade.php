{{-- Order Quick Search Widget - Shows on all warehouse pages --}}
<div x-data="orderSearchWidget()" x-init="init()" @keydown.window="handleGlobalKey($event)">
    <!-- Floating Search Button -->
    <button
        @click="open()"
        class="fixed bottom-6 left-6 z-50 w-14 h-14 bg-brand-600 hover:bg-brand-700 text-white rounded-full shadow-xl flex items-center justify-center transition-all duration-300 hover:scale-110 group"
        title="جستجوی سریع سفارش (Ctrl+K)"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <span class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
            Ctrl+K
        </span>
    </button>

    <!-- Search Modal Overlay -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm"
        style="display: none;"
    ></div>

    <!-- Search Modal -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-4"
        class="fixed top-[15%] left-1/2 -translate-x-1/2 z-[70] w-full max-w-lg"
        style="display: none;"
        @click.away="close()"
    >
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Search Input -->
            <div class="relative flex items-center border-b border-gray-200 dark:border-gray-700">
                <div class="pr-4 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    x-ref="searchInput"
                    x-model="query"
                    @input.debounce.300ms="search()"
                    @keydown.escape="close()"
                    @keydown.arrow-down.prevent="navigateDown()"
                    @keydown.arrow-up.prevent="navigateUp()"
                    @keydown.enter.prevent="goToSelected()"
                    type="text"
                    class="w-full py-4 pl-4 bg-transparent text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none text-base"
                    placeholder="نام مشتری، شماره سفارش یا اسکن بارکد..."
                    autocomplete="off"
                >
                <div x-show="loading" class="pl-4">
                    <div class="w-5 h-5 border-2 border-brand-200 border-t-brand-600 rounded-full animate-spin"></div>
                </div>
                <div x-show="!loading && query" @click="query = ''; results = []; selectedIndex = -1" class="pl-4 cursor-pointer text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
            </div>

            <!-- Results -->
            <div class="max-h-[400px] overflow-y-auto" x-show="query.length >= 2">
                <!-- Loading State -->
                <div x-show="loading && results.length === 0" class="p-8 text-center">
                    <div class="w-8 h-8 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin mx-auto mb-2"></div>
                    <p class="text-sm text-gray-500">در حال جستجو...</p>
                </div>

                <!-- Results List -->
                <template x-for="(order, index) in results" :key="order.id">
                    <a
                        :href="order.url"
                        class="block px-4 py-3 border-b border-gray-100 dark:border-gray-700/50 transition-colors duration-150"
                        :class="selectedIndex === index ? 'bg-brand-50 dark:bg-brand-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'"
                        @mouseenter="selectedIndex = index"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold text-brand-600 dark:text-brand-400 text-sm" x-text="'#' + order.order_number"></span>
                                    <span
                                        class="px-2 py-0.5 text-xs rounded-full font-medium"
                                        :class="getStatusClass(order.status_color)"
                                        x-text="order.status_label"
                                    ></span>
                                </div>
                                <p class="text-sm text-gray-800 dark:text-gray-200 font-medium" x-text="order.customer_name"></p>
                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                    <span x-show="order.customer_mobile" x-text="order.customer_mobile"></span>
                                    <span x-show="order.items_count" x-text="order.items_count + ' محصول'"></span>
                                    <span x-show="order.shipping_type" class="flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                        <span x-text="order.shipping_type"></span>
                                    </span>
                                </div>
                            </div>
                            <div class="text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                </template>

                <!-- No Results -->
                <div x-show="!loading && query.length >= 2 && results.length === 0 && searched" class="p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">سفارشی با این مشخصات پیدا نشد</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">شماره سفارش، نام مشتری یا بارکد را بررسی کنید</p>
                </div>
            </div>

            <!-- Hint -->
            <div x-show="query.length < 2" class="p-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-50 dark:bg-brand-900/20 rounded-full mb-3">
                    <svg class="w-8 h-8 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">جستجوی سریع سفارش</p>
                <div class="flex flex-wrap justify-center gap-2 text-xs text-gray-400">
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">نام مشتری</span>
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">شماره سفارش</span>
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">شماره موبایل</span>
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">کد رهگیری</span>
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">بارکد</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-400">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1">
                        <kbd class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded text-[10px] font-mono">↑↓</kbd>
                        جابجایی
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded text-[10px] font-mono">Enter</kbd>
                        باز کردن
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded text-[10px] font-mono">Esc</kbd>
                        بستن
                    </span>
                </div>
                <span x-show="results.length > 0" x-text="results.length + ' نتیجه'" class="text-brand-500"></span>
            </div>
        </div>
    </div>
</div>

<script>
function orderSearchWidget() {
    return {
        isOpen: false,
        query: '',
        results: [],
        loading: false,
        searched: false,
        selectedIndex: -1,
        barcodeBuffer: '',
        barcodeTimer: null,

        init() {
            // Barcode scanner detection: rapid keystrokes
        },

        handleGlobalKey(e) {
            // Ctrl+K or Cmd+K to open
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.open();
                return;
            }

            // Barcode scanner detection when modal is closed
            if (!this.isOpen && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const target = e.target;
                const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable;
                if (isInput) return;

                const now = Date.now();
                if (e.key === 'Enter' && this.barcodeBuffer.length >= 4) {
                    e.preventDefault();
                    this.open();
                    this.$nextTick(() => {
                        this.query = this.barcodeBuffer;
                        this.search();
                    });
                    this.barcodeBuffer = '';
                    clearTimeout(this.barcodeTimer);
                    return;
                }

                if (e.key.length === 1) {
                    this.barcodeBuffer += e.key;
                    clearTimeout(this.barcodeTimer);
                    this.barcodeTimer = setTimeout(() => {
                        this.barcodeBuffer = '';
                    }, 100);
                }
            }
        },

        open() {
            this.isOpen = true;
            this.$nextTick(() => {
                this.$refs.searchInput.focus();
            });
        },

        close() {
            this.isOpen = false;
            this.query = '';
            this.results = [];
            this.searched = false;
            this.selectedIndex = -1;
        },

        async search() {
            if (this.query.length < 2) {
                this.results = [];
                this.searched = false;
                return;
            }

            this.loading = true;
            this.selectedIndex = -1;

            try {
                const response = await fetch(`/warehouse/quick-search?q=${encodeURIComponent(this.query)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                this.results = data.results;
                this.searched = true;

                // If only one result from barcode scan, go directly
                if (this.results.length === 1 && this.query.startsWith('WH')) {
                    window.location.href = this.results[0].url;
                }
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                this.loading = false;
            }
        },

        navigateDown() {
            if (this.selectedIndex < this.results.length - 1) {
                this.selectedIndex++;
            }
        },

        navigateUp() {
            if (this.selectedIndex > 0) {
                this.selectedIndex--;
            }
        },

        goToSelected() {
            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                window.location.href = this.results[this.selectedIndex].url;
            } else if (this.results.length === 1) {
                window.location.href = this.results[0].url;
            }
        },

        getStatusClass(color) {
            const map = {
                'blue': 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400',
                'amber': 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400',
                'orange': 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400',
                'cyan': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-400',
                'indigo': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400',
                'green': 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400',
                'red': 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400',
            };
            return map[color] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
        }
    }
}
</script>
