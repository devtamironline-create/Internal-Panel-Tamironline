<li>
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span x-show="sidebarOpen">داشبورد</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.invoices.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.invoices.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
        <span x-show="sidebarOpen">فاکتورها</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.messenger') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.messenger') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span x-show="sidebarOpen">پیام‌رسان</span>
    </a>
</li>
<li class="my-4 border-t border-slate-700"></li>
<!-- Warehouse Section -->
<li x-data="{ warehouseOpen: {{ request()->routeIs('warehouse.*') ? 'true' : 'false' }} }">
    <button @click="warehouseOpen = !warehouseOpen" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('warehouse.*') ? 'bg-slate-700 text-white' : '' }}">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span x-show="sidebarOpen">مدیریت انبار</span>
        </div>
        <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform" :class="warehouseOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <ul x-show="warehouseOpen && sidebarOpen" x-transition class="mt-1 mr-4 space-y-1">
        <li>
            <a href="{{ route('warehouse.journey') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.journey') || request()->routeIs('warehouse.index') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                لیست سفارشات
            </a>
        </li>
        <li>
            <a href="{{ route('warehouse.packing.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.packing.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                ایستگاه بسته‌بندی
            </a>
        </li>
        <li>
            <a href="{{ route('warehouse.dispatch.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.dispatch.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                مدیریت ارسال
            </a>
        </li>
        <li>
            <a href="{{ route('warehouse.woocommerce.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.woocommerce.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                اتصال ووکامرس
            </a>
        </li>
        <li>
            <a href="{{ route('warehouse.amadest.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.amadest.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                اتصال آمادست
            </a>
        </li>
        @can('manage-warehouse')
        <li>
            <a href="{{ route('warehouse.settings.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.settings.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات انبار
            </a>
        </li>
        @endcan
    </ul>
</li>
<li class="my-4 border-t border-slate-700"></li>
<li>
    <a href="{{ route('admin.staff.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.staff.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen">پرسنل</span>
    </a>
</li>
