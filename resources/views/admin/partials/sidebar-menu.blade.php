<li>
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span x-show="sidebarOpen">داشبورد</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.customers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.customers.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span x-show="sidebarOpen">مشتریان</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.tickets.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.tickets.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        <span x-show="sidebarOpen">تیکت‌ها</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.invoices.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.invoices.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
        <span x-show="sidebarOpen">فاکتورها</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.products.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span x-show="sidebarOpen">محصولات</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.services.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.services.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
        <span x-show="sidebarOpen">سرویس‌ها</span>
    </a>
</li>
<li class="my-4 border-t border-slate-700"></li>
<li>
    <a href="{{ route('admin.staff.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.staff.*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen">پرسنل</span>
    </a>
</li>
<li>
    <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white {{ request()->routeIs('admin.settings*') ? 'bg-slate-700 text-white' : '' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen">تنظیمات</span>
    </a>
</li>
