<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'پنل کاربری') | Hostlino</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' },
                        gray: { 25: '#fcfcfd', 50: '#f9fafb', 100: '#f2f4f7', 200: '#e4e7ec', 300: '#d0d5dd', 400: '#98a2b3', 500: '#667085', 600: '#475467', 700: '#344054', 800: '#1d2939', 900: '#101828' },
                        success: { 50: '#ecfdf3', 500: '#12b76a', 600: '#039855' },
                        error: { 50: '#fef3f2', 500: '#f04438', 600: '#d92d20' },
                        warning: { 50: '#fffaeb', 500: '#f79009' },
                    },
                    fontFamily: { vazir: ['Rokh', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Rokh', sans-serif; font-weight: 500; }
        [x-cloak] { display: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .menu-item { @apply relative flex items-center gap-3 px-4 py-3 font-medium rounded-xl text-sm transition-all duration-200; }
        .menu-item-active { @apply bg-brand-500 text-white shadow-lg shadow-brand-500/30; }
        .menu-item-inactive { @apply text-gray-600 hover:bg-gray-100 hover:text-gray-900; }
    </style>
    @stack('styles')
</head>
<body
    x-data="{ sidebarToggle: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))"
    :class="{ 'dark': darkMode }"
    class="font-vazir bg-gray-50 dark:bg-gray-900"
>
    @if(session('impersonator_id'))
    <div class="bg-purple-600 text-white py-2 px-4 text-center text-sm fixed top-0 left-0 right-0 z-[100] shadow-lg">
        <div class="flex items-center justify-center gap-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>شما در حال مشاهده پنل مشتری هستید</span>
            <a href="{{ route('stop-impersonate') }}" class="bg-white text-purple-600 px-4 py-1 rounded-lg text-sm font-medium hover:bg-purple-100 transition">
                بازگشت به پنل مدیریت
            </a>
        </div>
    </div>
    @endif

    <div class="flex h-screen overflow-hidden @if(session('impersonator_id')) pt-10 @endif">
        <!-- Sidebar -->
        <aside
            :class="sidebarToggle ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            class="fixed right-0 top-0 z-50 flex h-screen w-72 flex-col overflow-y-hidden bg-white shadow-xl lg:static transition-transform duration-300"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 shadow-lg shadow-brand-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-900">هاستلینو</span>
                </a>
                <button @click="sidebarToggle = false" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- User Info -->
            <div class="p-4 mx-4 mt-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                        {{ mb_substr(auth()->user()->first_name ?? 'ک', 0, 1) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ auth()->user()->full_name }}</p>
                        <p class="text-sm text-gray-500">{{ auth()->user()->mobile }}</p>
                    </div>
                </div>
            </div>

            <!-- Menu -->
            <nav class="flex-1 overflow-y-auto no-scrollbar p-4">
                <ul class="flex flex-col gap-1">
                    <li>
                        <a href="{{ route('panel.dashboard') }}" class="menu-item {{ request()->routeIs('panel.dashboard') ? 'menu-item-active' : 'menu-item-inactive' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            داشبورد
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('panel.services.index') }}" class="menu-item {{ request()->routeIs('panel.services.*') ? 'menu-item-active' : 'menu-item-inactive' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                            سرویس‌های من
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('panel.invoices.index') }}" class="menu-item {{ request()->routeIs('panel.invoices.*') ? 'menu-item-active' : 'menu-item-inactive' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                            فاکتورها
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('panel.tickets.index') }}" class="menu-item {{ request()->routeIs('panel.tickets.*') ? 'menu-item-active' : 'menu-item-inactive' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                            تیکت‌های پشتیبانی
                        </a>
                    </li>
                    <li class="mt-4 pt-4 border-t border-gray-100">
                        <a href="{{ route('panel.profile') }}" class="menu-item {{ request()->routeIs('panel.profile') ? 'menu-item-active' : 'menu-item-inactive' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            پروفایل
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Logout -->
            <div class="p-4 border-t border-gray-100">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        خروج از حساب
                    </button>
                </form>
            </div>
        </aside>

        <!-- Overlay -->
        <div x-show="sidebarToggle" @click="sidebarToggle = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-transition.opacity></div>

        <!-- Main Content -->
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-lg border-b border-gray-100">
                <div class="flex items-center justify-between px-4 py-4 lg:px-6">
                    <!-- Hamburger -->
                    <button @click="sidebarToggle = !sidebarToggle" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-600 lg:hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <!-- Page Title -->
                    <h1 class="text-lg font-bold text-gray-900 hidden lg:block">@yield('page-title', 'داشبورد')</h1>

                    <!-- Logo Mobile -->
                    <a href="{{ route('panel.dashboard') }}" class="lg:hidden">
                        <span class="text-lg font-bold text-gray-900">هاستلینو</span>
                    </a>

                    <!-- Actions -->
                    <div class="flex items-center gap-3">
                        <!-- Dark Mode -->
                        <button @click="darkMode = !darkMode" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                            <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                            <svg x-show="darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </button>

                        <!-- Notifications -->
                        <button class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-red-500"></span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-6">
                @if(session('success'))
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-green-50 border border-green-200 p-4 text-sm text-green-700">
                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="mb-6 flex items-center gap-3 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                    <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ session('error') }}
                </div>
                @endif

                @yield('main')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
