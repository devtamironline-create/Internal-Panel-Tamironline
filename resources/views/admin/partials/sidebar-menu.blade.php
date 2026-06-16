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
<li>
    <a href="{{ route('warehouse.journey') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('warehouse.journey') || request()->routeIs('warehouse.index') || request()->routeIs('warehouse.show') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <span x-show="sidebarOpen">سفارشات</span>
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
            <a href="{{ route('warehouse.journey') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.journey') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                سفارشات
            </a>
        </li>
        @can('manage-warehouse')
        <li>
            <a href="{{ route('warehouse.manual-order.create') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.manual-order.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                ثبت سفارش دستی
            </a>
        </li>
        @endcan
        @can('warehouse.reprint-invoice')
        <li>
            <a href="{{ route('warehouse.reprint-requests.index') }}" class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.reprint-requests.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>درخواست چاپ</span>
                </div>
                @php
                    $pendingCount = \Modules\Warehouse\Models\ReprintRequest::where('status', 'pending')->count();
                @endphp
                @if($pendingCount > 0)
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-500 text-white">{{ $pendingCount }}</span>
                @endif
            </a>
        </li>
        @endcan
        @can('manage-warehouse')
        <li>
            <a href="{{ route('warehouse.settings.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('warehouse.settings.*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات
            </a>
        </li>
        @endcan
    </ul>
</li>
@canany(['manage-technicians', 'manage-permissions'])
<li x-data="{ technicianOpen: {{ request()->routeIs('technician.*') ? 'true' : 'false' }} }">
    <button @click="technicianOpen = !technicianOpen" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('technician.*') ? 'bg-slate-700 text-white' : '' }}">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span x-show="sidebarOpen">مدیریت تکنسین‌ها</span>
        </div>
        <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform" :class="technicianOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <ul x-show="technicianOpen && sidebarOpen" x-transition class="mt-1 mr-4 space-y-1">
        <li>
            <a href="{{ route('technician.admin.registrations') }}" class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('technician.admin.registrations*') ? 'bg-slate-700/50 text-white' : '' }}">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>لیست درخواست‌ها</span>
                </div>
                @php
                    $pendingTechCount = \Modules\Technician\Models\TechnicianRegistration::where('status', 'pending')->count();
                @endphp
                @if($pendingTechCount > 0)
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-500 text-white">{{ $pendingTechCount }}</span>
                @endif
            </a>
        </li>
        <li>
            <a href="{{ route('technician.admin.appliance-categories') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('technician.admin.appliance-categories*') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                مدیریت دستگاه‌ها
            </a>
        </li>
        <li>
            <a href="{{ route('technician.admin.settings') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('technician.admin.settings') ? 'bg-slate-700/50 text-white' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات صفحه جذب
            </a>
        </li>
        <li>
            <a href="{{ route('technician.landing') }}" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                مشاهده صفحه عمومی
            </a>
        </li>
    </ul>
</li>
@endcanany
@can('manage-seo')
<li x-data="{ seoOpen: {{ request()->routeIs('seo.admin.*') ? 'true' : 'false' }} }">
    <button @click="seoOpen = !seoOpen" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('seo.admin.*') ? 'bg-slate-700 text-white' : '' }}">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span x-show="sidebarOpen">سئو</span>
        </div>
        <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform" :class="seoOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <ul x-show="seoOpen && sidebarOpen" x-transition class="mt-1 mr-4 space-y-1">
        <li>
            <a href="{{ route('seo.admin.settings') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.settings') ? 'bg-slate-700/50 text-white' : '' }}">تنظیمات سئو</a>
        </li>
        <li>
            <a href="{{ route('seo.admin.redirects.index') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.redirects.*') ? 'bg-slate-700/50 text-white' : '' }}">ریدایرکت‌ها</a>
        </li>
        <li>
            <a href="{{ route('seo.admin.not-found.index') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.not-found.*') ? 'bg-slate-700/50 text-white' : '' }}">مانیتور ۴۰۴</a>
        </li>
        <li>
            <a href="{{ route('seo.admin.audit.index') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.audit.*') ? 'bg-slate-700/50 text-white' : '' }}">مانیتورینگ سئو</a>
        </li>
        <li>
            <a href="{{ route('seo.admin.tools.index') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.tools.*') ? 'bg-slate-700/50 text-white' : '' }}">ابزارها و Audit Log</a>
        </li>
        <li>
            <a href="{{ route('seo.admin.roles.index') }}" class="block px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white text-sm {{ request()->routeIs('seo.admin.roles.*') ? 'bg-slate-700/50 text-white' : '' }}">دسترسی نقش‌ها</a>
        </li>
    </ul>
</li>
@endcan
<li class="my-4 border-t border-slate-700"></li>
<li>
    <a href="{{ route('admin.staff.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.staff.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen">پرسنل</span>
    </a>
</li>
