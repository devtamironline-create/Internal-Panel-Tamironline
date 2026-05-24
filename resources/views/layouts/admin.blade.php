@php
    $siteName = \App\Models\Setting::get('site_name', 'اتوماسیون اداری');
    $siteSubtitle = \App\Models\Setting::get('site_subtitle', 'تعمیرآنلاین');
    $siteLogo = \App\Models\Setting::get('logo');
    $siteFavicon = \App\Models\Setting::get('favicon');
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'داشبورد') | {{ $siteSubtitle }}</title>
    @if($siteFavicon)
        <link rel="icon" href="{{ storage_url($siteFavicon) }}" type="image/png">
    @endif
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="/vendor/js/tailwind.min.js"></script>
    <script src="/vendor/js/apexcharts.min.js"></script>
    {{-- Alpine استاندالون لازم است چون layout چندین directive Alpine
         (سایدبار، dark mode، dropdown ها) دارد که باید همان زمان لود
         صفحه فعال باشند، نه بعد از آن‌که Livewire bundle خودش را اجرا
         کند. هشدار «multiple instances» یک warning بی‌ضرر است. --}}
    <script defer src="/vendor/js/alpine-collapse.min.js"></script>
    <script defer src="/vendor/js/alpine.min.js"></script>
    <link rel="stylesheet" href="/vendor/css/apexcharts.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#ecf3ff', 100: '#dde9ff', 200: '#c2d6ff', 300: '#9cb9ff', 400: '#7592ff', 500: '#465fff', 600: '#3641f5', 700: '#2a31d8', 800: '#252dae', 900: '#262e89' },
                        sidebar: { DEFAULT: '#1a2d48', light: '#243a5e', dark: '#142236' },
                        gray: { 25: '#fcfcfd', 50: '#f9fafb', 100: '#f2f4f7', 200: '#e4e7ec', 300: '#d0d5dd', 400: '#98a2b3', 500: '#667085', 600: '#475467', 700: '#344054', 800: '#1d2939', 900: '#101828' },
                        success: { 50: '#ecfdf3', 500: '#12b76a', 600: '#039855' },
                        error: { 50: '#fef3f2', 500: '#f04438', 600: '#d92d20' },
                        warning: { 50: '#fffaeb', 500: '#f79009' },
                        orange: { 400: '#fd853a', 500: '#fb6514' }
                    },
                    fontFamily: { vazir: ['Vazirmatn', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Vazirmatn', system-ui, sans-serif; }
        body { font-weight: 400; line-height: 1.7; }
        h1, h2, h3, h4, h5, h6, .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        strong, b { font-weight: 700; }
        input, select, textarea, button { font-family: inherit; }
        [x-cloak] { display: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Improved Typography */
        .text-sm { font-size: 0.875rem; line-height: 1.5; }
        .text-xs { font-size: 0.75rem; line-height: 1.4; }
        .text-lg { font-size: 1.125rem; line-height: 1.6; }
        .text-xl { font-size: 1.25rem; line-height: 1.5; }
        .text-2xl { font-size: 1.5rem; line-height: 1.4; }

        /* Dark Mode Global Styles */
        .dark .bg-white { background-color: #1f2937 !important; }
        .dark .bg-gray-50 { background-color: #111827 !important; }
        .dark .bg-gray-100 { background-color: #1f2937 !important; }
        .dark .text-gray-900 { color: #f9fafb !important; }
        .dark .text-gray-800 { color: #f3f4f6 !important; }
        .dark .text-gray-700 { color: #e5e7eb !important; }
        .dark .text-gray-600 { color: #d1d5db !important; }
        .dark .border-gray-200 { border-color: #374151 !important; }
        .dark .border-gray-100 { border-color: #374151 !important; }
        .dark .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3) !important; }
        .dark input, .dark select, .dark textarea {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #f9fafb !important;
        }
        .dark input::placeholder, .dark textarea::placeholder {
            color: #9ca3af !important;
        }
        .dark .rounded-xl.shadow-sm { background-color: #1f2937; }
        .dark table { background-color: #1f2937; }
        .dark th { background-color: #374151; color: #f9fafb; }
        .dark td { color: #e5e7eb; border-color: #374151; }
        .dark tr:hover td { background-color: #374151; }

        .sidebar-menu-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.75rem;
            font-weight: 500;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.85);
            transition: all 0.2s;
        }
        .sidebar-menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .sidebar-menu-item-active {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff !important;
        }
        .sidebar-submenu {
            margin-right: 2rem;
            margin-top: 0.25rem;
        }
        .sidebar-submenu .sidebar-menu-item {
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
        }
        .sidebar-menu-item svg {
            color: rgba(255, 255, 255, 0.85);
            flex-shrink: 0;
        }
        .sidebar-menu-item:hover svg,
        .sidebar-menu-item-active svg {
            color: #ffffff;
        }
    </style>
    <link rel="stylesheet" href="/vendor/css/persian-datepicker.min.css">
    <style>
        /* Persian Datepicker Dark Mode & Enhancements */
        .datepicker-container {
            z-index: 9999 !important;
            font-family: Vazirmatn, system-ui, sans-serif !important;
        }
        .datepicker-container .datepicker-plot-area {
            border-radius: 12px !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
            border: 1px solid #e5e7eb !important;
        }
        .dark .datepicker-container .datepicker-plot-area {
            background: #1f2937 !important;
            border-color: #374151 !important;
        }
        .dark .datepicker-container .datepicker-header {
            background: #111827 !important;
            color: #f9fafb !important;
        }
        .dark .datepicker-container .month-table,
        .dark .datepicker-container .year-table {
            background: #1f2937 !important;
        }
        .dark .datepicker-container td,
        .dark .datepicker-container .header-row-cell,
        .dark .datepicker-container .btn-today {
            color: #e5e7eb !important;
        }
        .dark .datepicker-container td.selected span,
        .dark .datepicker-container td.selected-range-head span,
        .dark .datepicker-container td.selected-range-tail span {
            background: #3b82f6 !important;
            color: #fff !important;
        }
        .dark .datepicker-container td:hover span {
            background: #374151 !important;
        }
        .dark .datepicker-container .toolbox {
            background: #111827 !important;
            border-color: #374151 !important;
        }
        .dark .datepicker-container .btn-submit {
            background: #3b82f6 !important;
            color: #fff !important;
        }
        .datepicker-container .today span {
            border: 2px solid #3b82f6 !important;
            border-radius: 50% !important;
        }
        .datepicker-container .btn-submit,
        .datepicker-container .btn-today {
            border-radius: 6px !important;
            padding: 6px 12px !important;
        }
    </style>
    @stack('styles')

    {{-- Tom Select (searchable dropdown library) — used for selects with
         data-tom-select. Lightweight, RTL-friendly, Livewire-compatible. --}}
    <link href="/vendor/css/tom-select.default.min.css" rel="stylesheet">
    <style>
        /* RTL + Tailwind-friendly tweaks for Tom Select — مطابق ظاهر سایر inputهای پنل */
        .ts-wrapper { font-family: inherit; }
        .ts-wrapper .ts-control,
        .ts-wrapper.single .ts-control,
        .ts-wrapper.multi .ts-control {
            background: #fff !important;
            background-image: none !important;
            padding: 0.625rem 0.75rem !important;
            min-height: 42px;
            border: 1px solid rgb(209 213 219) !important;
            border-radius: 0.5rem !important;
            box-shadow: none !important;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        .ts-wrapper.focus .ts-control,
        .ts-wrapper .ts-control:focus-within {
            border-color: rgb(70 95 255) !important;
            box-shadow: 0 0 0 2px rgb(70 95 255 / 0.2) !important;
        }
        .dark .ts-wrapper .ts-control,
        .dark .ts-wrapper.single .ts-control { background: rgb(55 65 81) !important; border-color: rgb(75 85 99) !important; color: rgb(243 244 246); }
        .ts-wrapper.disabled .ts-control { opacity: 0.5; cursor: not-allowed; background: rgb(243 244 246) !important; }
        .dark .ts-wrapper.disabled .ts-control { background: rgb(31 41 55) !important; }
        .ts-dropdown { font-family: inherit; border: 1px solid rgb(209 213 219); border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); margin-top: 4px; }
        .dark .ts-dropdown { background: rgb(31 41 55); border-color: rgb(75 85 99); color: rgb(243 244 246); }
        .dark .ts-dropdown .active { background: rgb(30 64 175); color: white; }
        .dark .ts-dropdown .option:hover { background: rgb(55 65 81); }
        .ts-control input::placeholder { color: rgb(156 163 175); }
        /* در RTL، فلش انتخاب باید سمت چپ باشد */
        .ts-wrapper.single .ts-control:after { left: 12px; right: auto; border-color: rgb(107 114 128) transparent transparent transparent; }
    </style>
</head>
<body
    x-data="{ sidebarToggle: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))"
    :class="{ 'dark': darkMode }"
    class="font-vazir bg-gray-50 dark:bg-gray-900"
>
    <!-- Full Page Loading -->
    <div id="page-loader" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:#f9fafb;transition:opacity .3s ease">
        <div style="text-align:center">
            <div style="width:40px;height:40px;margin:0 auto 12px;border:3px solid #e5e7eb;border-top-color:#465fff;border-radius:50%;animation:spin .7s linear infinite"></div>
            <p style="color:#6b7280;font-size:13px;font-family:Vazirmatn,sans-serif">در حال بارگذاری...</p>
        </div>
    </div>
    <style>@keyframes spin{to{transform:rotate(360deg)}} .dark #page-loader{background:#111827} .dark #page-loader p{color:#9ca3af} .dark #page-loader div>div{border-color:#374151;border-top-color:#465fff}</style>
    <script>window.addEventListener('load',function(){var l=document.getElementById('page-loader');if(l){l.style.opacity='0';setTimeout(function(){l.remove()},300)}})</script>

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            :class="sidebarToggle ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            class="fixed right-0 top-0 z-50 flex h-screen w-60 flex-col overflow-y-hidden bg-[#1a2d48] px-4 lg:static transition-transform duration-300"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pt-8 pb-7 border-b border-white/10">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    @if($siteLogo)
                        <img src="{{ storage_url($siteLogo) }}" alt="Logo" class="h-12 max-w-[160px] object-contain">
                    @else
                        <div class="flex items-center justify-center w-10 h-10">
                            <svg class="w-8 h-8 text-white/80" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-base font-bold text-white leading-tight">{{ $siteName }}</span>
                            <span class="text-xs text-white/60">{{ $siteSubtitle }}</span>
                        </div>
                    @endif
                </a>
                <button @click="sidebarToggle = false" class="lg:hidden text-white/70 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Menu -->
            <nav class="flex-1 overflow-y-auto no-scrollbar py-6">
                <!-- داشبورد -->
                <a href="{{ route('admin.dashboard') }}" class="sidebar-menu-item mb-2 {{ request()->routeIs('admin.dashboard') ? 'sidebar-menu-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z"/></svg>
                    داشبورد
                </a>

                <!-- پیام‌رسان -->
                @canany(['use-messenger', 'manage-permissions'])
                <a href="{{ route('admin.messenger') }}" class="sidebar-menu-item mb-2 {{ request()->routeIs('admin.messenger') ? 'sidebar-menu-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    پیام‌رسان
                </a>
                @endcanany

                <!-- پرسنل -->
                @canany(['view-staff', 'manage-staff', 'manage-permissions'])
                <a href="{{ route('admin.staff.index') }}" class="sidebar-menu-item mb-2 {{ request()->routeIs('admin.staff.*') ? 'sidebar-menu-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    پرسنل
                </a>
                @endcanany

                <!-- کارتابل پرسنلی -->
                @canany(['view-attendance', 'view-leave', 'manage-attendance', 'manage-leave', 'manage-permissions'])
                <div class="mt-6" x-data="{ open: {{ request()->routeIs('attendance.*') || request()->routeIs('leave.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            کارتابل پرسنلی
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        @canany(['view-attendance', 'manage-permissions'])
                        <a href="{{ route('attendance.index') }}" class="sidebar-menu-item {{ request()->routeIs('attendance.index') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            حضور و غیاب
                        </a>
                        @endcanany
                        @canany(['view-leave', 'request-leave', 'manage-permissions'])
                        <a href="{{ route('leave.index') }}" class="sidebar-menu-item {{ request()->routeIs('leave.index') || request()->routeIs('leave.create') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            مرخصی
                        </a>
                        @endcanany
                        @canany(['manage-attendance', 'manage-permissions'])
                        <a href="{{ route('attendance.admin') }}" class="sidebar-menu-item {{ request()->routeIs('attendance.admin') || request()->routeIs('attendance.settings') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت حضور و غیاب
                        </a>
                        @endcanany
                        @canany(['manage-leave', 'manage-permissions'])
                        <a href="{{ route('leave.approvals') }}" class="sidebar-menu-item {{ request()->routeIs('leave.approvals') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            مدیریت مرخصی
                        </a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                <!-- کارتابل عملیاتی -->
                @canany(['view-tasks', 'manage-tasks', 'manage-teams', 'view-reports', 'manage-permissions'])
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('tasks.*') || request()->routeIs('teams.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            کارتابل عملیاتی
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        @canany(['view-tasks', 'create-tasks', 'manage-tasks', 'manage-permissions'])
                        <a href="{{ route('tasks.index') }}" class="sidebar-menu-item {{ request()->routeIs('tasks.index') || request()->routeIs('tasks.show') || request()->routeIs('tasks.create') || request()->routeIs('tasks.edit') || request()->routeIs('tasks.my') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            مدیریت تسک‌ها
                        </a>
                        @endcanany
                        @canany(['manage-teams', 'manage-permissions'])
                        <a href="{{ route('teams.index') }}" class="sidebar-menu-item {{ request()->routeIs('teams.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            مدیریت تیم‌ها
                        </a>
                        @endcanany
                        @canany(['view-tasks', 'create-tasks', 'manage-tasks', 'manage-permissions'])
                        <a href="{{ route('tasks.calendar') }}" class="sidebar-menu-item {{ request()->routeIs('tasks.calendar') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            تقویم پروژه
                        </a>
                        @endcanany
                        @canany(['manage-tasks', 'manage-permissions'])
                        <a href="{{ route('task-categories.index') }}" class="sidebar-menu-item {{ request()->routeIs('task-categories.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            دسته‌بندی تسک‌ها
                        </a>
                        @endcanany
                        @canany(['view-reports', 'manage-permissions'])
                        <a href="{{ route('tasks.reports.users') }}" class="sidebar-menu-item {{ request()->routeIs('tasks.reports.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            گزارش عملکرد
                        </a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                <!-- OKR -->
                @canany(['view-okr', 'manage-okr', 'manage-permissions'])
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('okr.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            مدیریت OKR
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="{{ route('okr.dashboard') }}" class="sidebar-menu-item {{ request()->routeIs('okr.dashboard') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                            داشبورد OKR
                        </a>
                        @canany(['manage-okr', 'manage-permissions'])
                        <a href="{{ route('okr.cycles.index') }}" class="sidebar-menu-item {{ request()->routeIs('okr.cycles.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            دوره‌ها
                        </a>
                        @endcanany
                        <a href="{{ route('okr.objectives.index') }}" class="sidebar-menu-item {{ request()->routeIs('okr.objectives.index') || request()->routeIs('okr.objectives.show') || request()->routeIs('okr.objectives.create') || request()->routeIs('okr.objectives.edit') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                            اهداف سازمانی
                        </a>
                        <a href="{{ route('okr.objectives.my') }}" class="sidebar-menu-item {{ request()->routeIs('okr.objectives.my') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            اهداف من
                        </a>
                    </div>
                </div>
                @endcanany

                <!-- Salary -->
                @canany(['view-salary', 'manage-salary', 'manage-permissions'])
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('salary.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            حقوق و دستمزد
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="{{ route('salary.dashboard') }}" class="sidebar-menu-item {{ request()->routeIs('salary.dashboard') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            حقوق من
                        </a>
                        <a href="{{ route('salary.history') }}" class="sidebar-menu-item {{ request()->routeIs('salary.history') || request()->routeIs('salary.show') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            تاریخچه فیش‌ها
                        </a>
                        @canany(['manage-salary', 'manage-permissions'])
                        <a href="{{ route('salary.admin.index') }}" class="sidebar-menu-item {{ request()->routeIs('salary.admin.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت حقوق
                        </a>
                        <a href="{{ route('salary.settings.index') }}" class="sidebar-menu-item {{ request()->routeIs('salary.settings.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            تنظیمات حقوق
                        </a>
                        @endcanany
                    </div>
                </div>
                @endcanany

                <!-- مدیریت انبار -->
                @if(Route::has('warehouse.index'))
                @canany(['view-warehouse', 'manage-warehouse', 'manage-permissions'])
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('warehouse.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            مدیریت انبار
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="{{ route('warehouse.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.index') || request()->routeIs('warehouse.show') || request()->routeIs('warehouse.create') || request()->routeIs('warehouse.edit') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            لیست سفارشات
                        </a>
                        @canany(['manage-warehouse', 'manage-permissions'])
                        <a href="{{ route('warehouse.manual-order.create') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.manual-order.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            ثبت سفارش دستی
                        </a>
                        @endcanany
                        @canany(['manage-warehouse', 'manage-permissions'])
                        <a href="{{ route('warehouse.packing.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.packing.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            ایستگاه بسته‌بندی
                        </a>
                        <a href="{{ route('warehouse.exit-scan.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.exit-scan.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1.5 12h11L19 8"/></svg>
                            صف ارسال (اسکن)
                        </a>
                        @endcanany
                        @can('warehouse.reprint-invoice')
                        <a href="{{ route('warehouse.reprint-requests.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.reprint-requests.*') ? 'sidebar-menu-item-active' : '' }}" style="position: relative;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>درخواست چاپ مجدد</span>
                            @php
                                try {
                                    $pendingReprintCount = \Modules\Warehouse\Models\ReprintRequest::where('status', 'pending')->count();
                                } catch (\Exception $e) {
                                    $pendingReprintCount = 0;
                                }
                            @endphp
                            @if($pendingReprintCount > 0)
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 px-2 py-0.5 text-xs font-bold rounded-full bg-amber-500 text-white">{{ $pendingReprintCount }}</span>
                            @endif
                        </a>
                        @endcan
                        @canany(['manage-warehouse', 'manage-permissions'])
                        <a href="{{ route('warehouse.dispatch.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.dispatch.index') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            مدیریت ارسال
                        </a>
                        <a href="{{ route('warehouse.woocommerce.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.woocommerce.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            اتصال ووکامرس
                        </a>
                        <a href="{{ route('warehouse.amadest.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.amadest.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            اتصال آمادست
                        </a>
                        <a href="{{ route('warehouse.tapin.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.tapin.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            اتصال تاپین
                        </a>
                        <a href="{{ route('warehouse.postex.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.postex.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            اتصال پستکس
                        </a>
                        <a href="{{ route('warehouse.cod24.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.cod24.index') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            اتصال COD24
                        </a>
                        <a href="{{ route('warehouse.cod24.city-map') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.cod24.city-map') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            نقشه شهر/استان
                        </a>
                        <a href="{{ route('warehouse.distribution.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.distribution.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            تقسیم‌بندی سفارشات
                        </a>
                        <a href="{{ route('warehouse.settings.index') }}" class="sidebar-menu-item {{ request()->routeIs('warehouse.settings.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            تنظیمات انبار
                        </a>
                        @endcanany
                    </div>
                </div>
                @endcanany
                @endif

                <!-- مدیریت تکنسین‌ها -->
                @if(Route::has('technician.admin.settings'))
                @can('manage-technicians')
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('technician.admin.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                            مدیریت تکنسین‌ها
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="{{ route('technician.admin.registrations') }}" class="sidebar-menu-item {{ request()->routeIs('technician.admin.registrations*') ? 'sidebar-menu-item-active' : '' }}" style="position: relative;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            لیست درخواست‌ها
                            @php
                                $pendingTechCount = \Modules\Technician\Models\TechnicianRegistration::where('status', 'pending')->count();
                            @endphp
                            @if($pendingTechCount > 0)
                            <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); background: #f59e0b; color: white; font-size: 11px; font-weight: bold; padding: 1px 7px; border-radius: 9999px;">{{ $pendingTechCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('technician.admin.appliance-categories') }}" class="sidebar-menu-item {{ request()->routeIs('technician.admin.appliance-categories*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                            مدیریت دستگاه‌ها
                        </a>
                        <a href="{{ route('technician.admin.settings') }}" class="sidebar-menu-item {{ request()->routeIs('technician.admin.settings') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                            تنظیمات صفحه جذب
                        </a>
                        <a href="{{ route('technician.landing') }}" target="_blank" class="sidebar-menu-item">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            مشاهده صفحه عمومی
                        </a>
                    </div>
                </div>
                @endcan
                @endif

                <!-- خدمات تعمیرات (CRM) -->
                @if(Route::has('crm.dashboard'))
                @canany(['view-crm-dashboard', 'view-crm-orders', 'view-crm-customers', 'view-crm-technicians', 'view-crm-financial', 'view-crm-reports', 'manage-crm-orphan-orders', 'view-crm-costs', 'view-crm-taxonomies', 'manage-crm-settings', 'manage-crm-sync', 'manage-permissions', 'view-tech-dashboard', 'view-own-orders'])
                <div class="mt-2" x-data="{ open: {{ request()->routeIs('crm.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            خدمات تعمیرات
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        @can('view-crm-dashboard')
                        <a href="{{ route('crm.dashboard') }}" class="sidebar-menu-item {{ request()->routeIs('crm.dashboard') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            داشبورد CRM
                        </a>
                        @endcan

                        {{-- ── پنل تکنسین ── --}}
                        @canany(['view-tech-dashboard', 'view-own-orders', 'view-own-wallet', 'view-own-invoices'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">پنل تکنسین</div>
                        @can('view-tech-dashboard')
                        <a href="{{ route('crm.tech.dashboard') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tech.dashboard') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            داشبورد من
                        </a>
                        @endcan
                        @can('view-own-orders')
                        <a href="{{ route('crm.orders.my') }}" class="sidebar-menu-item {{ request()->routeIs('crm.orders.my') || request()->routeIs('crm.tech.orders.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            سفارش‌های من
                        </a>
                        @endcan
                        @can('view-own-wallet')
                        <a href="{{ route('crm.tech.wallet') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tech.wallet') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m3-2h10a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2v-6a2 2 0 012-2z"/></svg>
                            کیف‌پول من
                        </a>
                        @endcan
                        @can('view-own-invoices')
                        <a href="{{ route('crm.tech.invoices') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tech.invoices') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            فاکتورهای من
                        </a>
                        @endcan
                        @can('view-tech-dashboard')
                        <a href="{{ route('crm.tech.profile') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tech.profile') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            پروفایل من
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── عملیات ── --}}
                        @canany(['view-crm-orders', 'create-crm-order', 'view-crm-customers'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">عملیات</div>
                        @can('view-crm-orders')
                        <a href="{{ route('crm.orders.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.orders.index') || request()->routeIs('crm.orders.show') || request()->routeIs('crm.orders.edit') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            سفارش‌های تعمیر
                        </a>
                        @endcan
                        @can('create-crm-order')
                        <a href="{{ route('crm.orders.create') }}" class="sidebar-menu-item {{ request()->routeIs('crm.orders.create') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            افزودن سفارش
                        </a>
                        @endcan
                        @can('view-crm-customers')
                        <a href="{{ route('crm.customers.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.customers.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            مشتری‌ها
                        </a>
                        @endcan
                        @can('view-crm-tickets')
                        <a href="{{ route('crm.tickets.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tickets.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            تیکت‌های پشتیبانی
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── افراد ── --}}
                        @canany(['view-crm-technicians', 'view-crm-financial', 'manage-crm-wallet'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">افراد</div>
                        @can('view-crm-technicians')
                        <a href="{{ route('crm.technicians.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.technicians.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63"/></svg>
                            تکنسین‌های فعال
                        </a>
                        @endcan
                        @can('view-crm-financial')
                        <a href="{{ route('crm.wallet.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.wallet.index') || request()->routeIs('crm.wallet.show') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m3-2h10a2 2 0 012 2v6a2 2 0 01-2 2H10a2 2 0 01-2-2v-6a2 2 0 012-2zm7 5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            کیف‌پول تکنسین‌ها
                        </a>
                        @endcan
                        @can('manage-crm-wallet')
                        <a href="{{ route('crm.wallet.add') }}" class="sidebar-menu-item {{ request()->routeIs('crm.wallet.add') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            افزودن شارژ/پاداش/جریمه
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── مالی ── --}}
                        @canany(['view-crm-invoices', 'manage-crm-payment-gateway'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">مالی</div>
                        @can('view-crm-invoices')
                        <a href="{{ route('crm.invoices.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.invoices.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            فاکتورها
                        </a>
                        @endcan
                        @can('manage-crm-payment-gateway')
                        <a href="{{ route('crm.payments.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.payments.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            پرداخت‌ها (زیبال)
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── گزارش‌ها ── --}}
                        @canany(['view-crm-reports', 'manage-crm-orphan-orders'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">گزارش‌ها</div>
                        @can('view-crm-reports')
                        <a href="{{ route('crm.reports.financial') }}" class="sidebar-menu-item {{ request()->routeIs('crm.reports.financial*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            گزارش مالی
                        </a>
                        <a href="{{ route('crm.reports.daily-activity') }}" class="sidebar-menu-item {{ request()->routeIs('crm.reports.daily-activity*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            فعالیت روزانه
                        </a>
                        @endcan
                        @can('manage-crm-orphan-orders')
                        <a href="{{ route('crm.orphan-orders.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.orphan-orders.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            سفارش‌های یتیم
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── پیکربندی ── --}}
                        @canany(['view-crm-taxonomies', 'manage-crm-sms-templates'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">پیکربندی</div>
                        @can('view-crm-taxonomies')
                        <a href="{{ route('crm.brands.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.brands.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            برندها
                        </a>
                        <a href="{{ route('crm.devices.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.devices.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25"/></svg>
                            دستگاه‌ها
                        </a>
                        <a href="{{ route('crm.provinces.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.provinces.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            استان‌ها
                        </a>
                        <a href="{{ route('crm.cities.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.cities.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            شهرها
                        </a>
                        @endcan
                        @can('manage-crm-sms-templates')
                        <a href="{{ route('crm.sms-management.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.sms-management.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            مدیریت پیامک‌ها
                        </a>
                        @endcan
                        @endcanany

                        {{-- ── ابزارها ── --}}
                        @canany(['manage-crm-sync', 'manage-permissions', 'manage-crm-settings'])
                        <div class="px-3 pt-3 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">ابزارها</div>
                        @can('manage-crm-sync')
                        <a href="{{ route('crm.sync.settings') }}" class="sidebar-menu-item {{ request()->routeIs('crm.sync.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            سینک وردپرس
                        </a>
                        <a href="{{ route('crm.sync-logs.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.sync-logs.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            لاگ سینک
                        </a>
                        <a href="{{ route('crm.data-tools.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.data-tools.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            ابزارهای داده
                        </a>
                        @endcan
                        @can('manage-permissions')
                        <a href="{{ route('crm.legacy-import.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.legacy-import.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            انتقال داده وردپرس
                        </a>
                        @endcan
                        @can('manage-crm-settings')
                        <a href="{{ route('crm.tech-panel-settings.index') }}" class="sidebar-menu-item {{ request()->routeIs('crm.tech-panel-settings.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            ظاهر پنل تکنسین
                        </a>
                        <a href="{{ route('crm.training.admin.videos') }}" class="sidebar-menu-item {{ request()->routeIs('crm.training.admin.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            آموزش تکنسین
                        </a>
                        @endcan
                        @endcanany
                    </div>
                </div>
                @endcanany
                @endif

                <!-- مدیریت سیستم -->
                @can('manage-permissions')
                <div class="mt-6" x-data="{ open: {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت سیستم
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="{{ route('admin.roles.index') }}" class="sidebar-menu-item {{ request()->routeIs('admin.roles.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            نقش‌ها
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="sidebar-menu-item {{ request()->routeIs('admin.settings.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            تنظیمات سایت
                        </a>
                        <a href="{{ route('admin.restore.index') }}" class="sidebar-menu-item {{ request()->routeIs('admin.restore.*') ? 'sidebar-menu-item-active' : '' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            بازیابی داده
                        </a>
                    </div>
                </div>
                @endcan

            </nav>

            <!-- User Info at Bottom -->
            <div class="border-t border-white/10 py-4">
                <div class="flex items-center gap-3 px-2">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold overflow-hidden">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover" alt="{{ auth()->user()->full_name }}">
                            @else
                                {{ auth()->user()->initials }}
                            @endif
                        </div>
                        @php
                            $userStatusColor = auth()->user()->getPresenceStatusColor();
                        @endphp
                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-gray-800 bg-{{ $userStatusColor }}-500"></span>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-white">{{ auth()->user()->full_name ?? 'کاربر' }}</div>
                        <div class="text-xs text-white/60">{{ auth()->user()->roles->first()?->name ?? 'کارمند' }}</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Overlay -->
        <div x-show="sidebarToggle" @click="sidebarToggle = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-transition.opacity></div>

        <!-- Main Content -->
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto bg-gray-50 dark:bg-gray-900">
            @if(session('impersonator_id'))
            <div class="bg-red-600 text-white px-4 py-2 flex items-center justify-between gap-4 text-sm">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    شما در حساب «{{ auth()->user()->name ?? auth()->user()->first_name ?? '—' }}» وارد شده‌اید (impersonate).
                </span>
                <form action="{{ route('crm.impersonate.leave') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1 bg-white text-red-700 rounded font-medium hover:bg-gray-100">
                        بازگشت به حساب ادمین
                    </button>
                </form>
            </div>
            @endif

            <!-- Header -->
            <header class="sticky top-0 z-30 flex w-full border-b border-gray-200 bg-white lg:border-b dark:border-gray-800 dark:bg-gray-900">
                <div class="flex grow flex-col items-center justify-between lg:flex-row lg:px-6">
                    <div class="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4 dark:border-gray-800">
                        <!-- Hamburger -->
                        <button @click="sidebarToggle = !sidebarToggle" class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400">
                            <svg class="fill-current" width="16" height="12" viewBox="0 0 16 12"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"/></svg>
                        </button>

                        <!-- Logo Mobile -->
                        <a href="{{ route('admin.dashboard') }}" class="lg:hidden">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">تعمیرآنلاین</span>
                        </a>

                        <!-- Search -->
                        <div class="hidden lg:block" x-data="globalSearch()">
                            <div class="relative">
                                <span class="absolute top-1/2 right-4 -translate-y-1/2">
                                    <svg class="fill-gray-500" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"/></svg>
                                </span>
                                <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="showResults = true" @click.away="showResults = false" placeholder="جستجو یا تایپ دستور..." class="h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pr-12 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 xl:w-[430px] dark:border-gray-800 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">

                                <!-- Search Results Dropdown -->
                                <div x-show="showResults && (results.length > 0 || loading)" x-transition class="absolute top-full right-0 mt-2 w-full bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                                    <div x-show="loading" class="p-4 text-center text-gray-500">
                                        <svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </div>
                                    <template x-for="item in results" :key="item.url">
                                        <a :href="item.url" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="item.type === 'staff' ? 'bg-blue-100 text-blue-600' : (item.type === 'task' ? 'bg-green-100 text-green-600' : (item.type === 'team' ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-600'))">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 dark:text-white text-sm" x-text="item.title"></div>
                                                <div class="text-xs text-gray-500" x-text="item.subtitle"></div>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 px-5 py-4 lg:px-0">
                        <!-- Activity Status -->
                        <div class="relative" x-data="activityStatus()">
                            <button @click="open = !open" class="flex h-11 items-center gap-2 px-3 rounded-full border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 text-sm">
                                <span class="w-2.5 h-2.5 rounded-full" :class="'bg-' + statusColor + '-500'"></span>
                                <span x-text="statusLabel" class="hidden sm:inline"></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition class="absolute left-0 mt-2 w-48 rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900 overflow-hidden z-50">
                                <div class="p-2 text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">وضعیت فعالیت</div>
                                <div class="p-1">
                                    <button @click="setStatus('online')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> آنلاین
                                    </button>
                                    <button @click="setStatus('meeting')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> در جلسه
                                    </button>
                                    <button @click="setStatus('remote')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> دورکاری
                                    </button>
                                    <button @click="setStatus('lunch')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> ناهار
                                    </button>
                                    <button @click="setStatus('break')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-500"></span> استراحت
                                    </button>
                                    <button @click="setStatus('leave')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> مرخصی
                                    </button>
                                    <button @click="setStatus('busy')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> مشغول
                                    </button>
                                    <button @click="setStatus('away')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> دور
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Dark Mode -->
                        <button @click="darkMode = !darkMode" class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800">
                            <svg x-show="!darkMode" class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z"/></svg>
                            <svg x-show="darkMode" class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z"/></svg>
                        </button>

                        <!-- Notifications -->
                        <div class="relative" x-data="notificationPanel()" x-init="loadNotifications()">
                            <button @click="open = !open; if(open) loadNotifications()" class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800">
                                <span x-show="unreadCount > 0" class="absolute top-0.5 left-0 h-2 w-2 rounded-full bg-orange-400"></span>
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"/></svg>
                            </button>

                            <!-- Notifications Dropdown -->
                            <div x-show="open" @click.away="open = false" x-transition class="absolute left-0 mt-4 w-80 rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900 overflow-hidden z-50">
                                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="font-bold text-gray-900 dark:text-white">اعلان‌ها</h3>
                                    <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs text-brand-600 hover:text-brand-700">
                                        خواندن همه
                                    </button>
                                </div>
                                <div class="max-h-80 overflow-y-auto">
                                    <template x-if="notifications.length === 0">
                                        <div class="p-8 text-center text-gray-400">
                                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            <p class="text-sm">اعلان جدیدی ندارید</p>
                                        </div>
                                    </template>
                                    <template x-for="notif in notifications" :key="notif.id">
                                        <a :href="notif.url" @click="markRead(notif.id)" class="flex items-start gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-800 border-b border-gray-100 dark:border-gray-700 last:border-0" :class="{ 'bg-blue-50 dark:bg-blue-900/20': !notif.read }">
                                            <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900 flex items-center justify-center text-brand-600 dark:text-brand-400 flex-shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-gray-900 dark:text-white text-sm" x-text="notif.title"></p>
                                                <p class="text-xs text-gray-500 truncate" x-text="notif.message"></p>
                                                <p class="text-xs text-gray-400 mt-1" x-text="notif.time"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- User Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-3">
                                <span class="hidden lg:block text-right">
                                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-400">{{ auth()->user()->full_name ?? 'کاربر' }}</span>
                                </span>
                                <svg class="hidden lg:block fill-gray-500" width="18" height="20" viewBox="0 0 18 20"><path d="M4.3125 8.65625L9 13.3437L13.6875 8.65625" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition class="absolute left-0 mt-4 w-60 rounded-2xl border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-3 pb-3 border-b border-gray-200 dark:border-gray-800">
                                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-400">{{ auth()->user()->full_name ?? 'کاربر' }}</span>
                                    <span class="block text-xs text-gray-500">{{ auth()->user()->mobile }}</span>
                                </div>
                                <ul class="space-y-1">
                                    <li><a href="{{ route('admin.profile') }}" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        پروفایل
                                    </a></li>
                                </ul>
                                <form action="{{ route('logout') }}" method="POST" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 px-3 py-2 text-sm text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        خروج
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 md:p-6 max-w-screen-2xl mx-auto w-full">
                @if(session('success'))
                <div class="mb-6 flex items-center gap-3 rounded-lg border border-success-500 bg-success-50 p-4 text-sm text-success-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="mb-6 flex items-center gap-3 rounded-lg border border-error-500 bg-error-50 p-4 text-sm text-error-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ session('error') }}
                </div>
                @endif

                @yield('main')
            </main>
        </div>
    </div>

    <script src="/vendor/js/jquery.min.js"></script>
    <script src="/vendor/js/persian-date.min.js"></script>
    <script src="/vendor/js/persian-datepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.jalali-datepicker').each(function() {
                var $input = $(this);
                $input.persianDatepicker({
                    format: 'YYYY/MM/DD',
                    initialValue: false,
                    autoClose: true,
                    responsive: true,
                    position: 'auto',
                    altField: $input.data('alt-field') || null,
                    altFormat: 'X',
                    observer: true,
                    inputDelay: 500,
                    calendar: {
                        persian: {
                            locale: 'fa',
                            showHint: true,
                            leapYearMode: 'algorithmic'
                        }
                    },
                    navigator: {
                        enabled: true,
                        scroll: { enabled: true },
                        text: {
                            btnNextText: '<',
                            btnPrevText: '>'
                        }
                    },
                    toolbox: {
                        enabled: true,
                        calendarSwitch: { enabled: false },
                        todayButton: { enabled: true, text: { fa: 'امروز' } },
                        submitButton: { enabled: true, text: { fa: 'تایید' } }
                    },
                    dayPicker: { enabled: true, titleFormat: 'YYYY MMMM' },
                    monthPicker: { enabled: true, titleFormat: 'YYYY' },
                    yearPicker: { enabled: true, titleFormat: 'YYYY' },
                    minDate: $input.data('min-date') || null,
                    maxDate: $input.data('max-date') || null,
                    onSelect: function(unix) {
                        $input.trigger('change');
                        // Trigger Alpine.js change event if present
                        $input[0].dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });

            // Initialize timepicker for time inputs
            $('.jalali-timepicker').each(function() {
                var $input = $(this);
                $input.persianDatepicker({
                    format: 'HH:mm',
                    initialValue: false,
                    autoClose: true,
                    onlyTimePicker: true,
                    timePicker: {
                        enabled: true,
                        step: 30,
                        hour: { enabled: true },
                        minute: { enabled: true, step: 30 },
                        second: { enabled: false }
                    },
                    onSelect: function(unix) {
                        $input.trigger('change');
                    }
                });
            });
        });
    </script>

    <script src="/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof tinymce !== 'undefined' && document.querySelector('.rich-editor')) {
                tinymce.init({
                    selector: '.rich-editor',
                    height: 300,
                    directionality: 'rtl',
                    language: 'fa',
                    plugins: 'lists link image code table',
                    toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
                    menubar: false,
                    branding: false,
                    content_style: 'body { font-family: Vazirmatn, sans-serif; direction: rtl; text-align: right; }',
                    setup: function(editor) { editor.on('change', function() { editor.save(); }); }
                });
            }
        });
    </script>

    <!-- Global Scripts -->
    <script>
    function activityStatus() {
        return {
            open: false,
            status: '{{ auth()->user()->getPresenceStatus() }}',
            statusLabel: '{{ auth()->user()->getPresenceStatusLabel() }}',
            statusColor: '{{ auth()->user()->getPresenceStatusColor() }}',
            async setStatus(status) {
                try {
                    const response = await fetch('/admin/chat/activity-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ status: status })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.status = data.status;
                        this.statusLabel = data.label;
                        this.statusColor = data.color;
                    }
                } catch (e) {
                    console.error('Activity status error:', e);
                }
                this.open = false;
            }
        };
    }

    function globalSearch() {
        return {
            query: '',
            results: [],
            loading: false,
            showResults: false,
            searchTimeout: null,
            async search() {
                // Clear previous timeout
                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout);
                }

                if (this.query.trim().length < 2) {
                    this.results = [];
                    this.showResults = false;
                    return;
                }

                this.loading = true;
                this.showResults = true;

                // Debounce search
                this.searchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch(`/admin/search?q=${encodeURIComponent(this.query.trim())}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) {
                            throw new Error('Search request failed');
                        }

                        const data = await response.json();
                        this.results = data.results || [];

                        if (this.results.length === 0 && this.query.trim().length >= 2) {
                            this.results = [{
                                type: 'empty',
                                title: 'نتیجه‌ای یافت نشد',
                                subtitle: `برای "${this.query}"`,
                                url: '#'
                            }];
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                        this.results = [{
                            type: 'error',
                            title: 'خطا در جستجو',
                            subtitle: 'لطفاً دوباره تلاش کنید',
                            url: '#'
                        }];
                    } finally {
                        this.loading = false;
                    }
                }, 300);
            }
        };
    }

    function notificationPanel() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            async loadNotifications() {
                try {
                    const response = await fetch('/admin/notifications');
                    const data = await response.json();
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                } catch (e) {
                    console.error('Notification error:', e);
                }
            },
            async markRead(id) {
                try {
                    await fetch(`/admin/notifications/${id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const notif = this.notifications.find(n => n.id === id);
                    if (notif) notif.read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                } catch (e) {}
            },
            async markAllRead() {
                try {
                    await fetch('/admin/notifications/read-all', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    this.notifications.forEach(n => n.read = true);
                    this.unreadCount = 0;
                } catch (e) {}
            }
        };
    }
    </script>

    <!-- Toast Notification Component -->
    <div x-data="toastManager()" x-init="init()" class="fixed bottom-4 left-4 z-[100] space-y-2">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform translate-x-full"
                x-transition:enter-end="opacity-100 transform translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-x-0"
                x-transition:leave-end="opacity-0 transform translate-x-full"
                class="flex items-start gap-3 p-4 rounded-xl shadow-lg max-w-sm"
                :class="{
                    'bg-green-500 text-white': toast.type === 'success',
                    'bg-red-500 text-white': toast.type === 'error',
                    'bg-blue-500 text-white': toast.type === 'info',
                    'bg-yellow-500 text-white': toast.type === 'warning',
                    'bg-white dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700': toast.type === 'default'
                }">
                <div class="flex-shrink-0">
                    <template x-if="toast.type === 'success'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </template>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm" x-text="toast.title"></p>
                    <p class="text-sm opacity-90" x-text="toast.message" x-show="toast.message"></p>
                </div>
                <button @click="removeToast(toast.id)" class="flex-shrink-0 opacity-70 hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <script>
    // Global Toast Manager
    function toastManager() {
        return {
            toasts: [],
            toastId: 0,

            init() {
                // Make toast functions globally available
                window.showToast = this.addToast.bind(this);

                // Check for session flash messages
                @if(session('success'))
                this.addToast('success', '{{ session('success') }}');
                @endif
                @if(session('error'))
                this.addToast('error', '{{ session('error') }}');
                @endif
                @if(session('info'))
                this.addToast('info', '{{ session('info') }}');
                @endif

                // Poll for new notifications
                this.pollNotifications();
            },

            addToast(type, title, message = '', duration = 5000) {
                const id = ++this.toastId;
                this.toasts.push({ id, type, title, message, show: true });

                if (duration > 0) {
                    setTimeout(() => this.removeToast(id), duration);
                }

                return id;
            },

            removeToast(id) {
                const toast = this.toasts.find(t => t.id === id);
                if (toast) {
                    toast.show = false;
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 200);
                }
            },

            async pollNotifications() {
                let lastCheck = Date.now();

                setInterval(async () => {
                    try {
                        const response = await fetch('/admin/notifications?since=' + lastCheck, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                        if (data.new_notifications && data.new_notifications.length > 0) {
                            data.new_notifications.forEach(notif => {
                                let toastType = 'info';
                                let duration = 5000;

                                if (notif.type === 'leave_approved') { toastType = 'success'; }
                                else if (notif.type === 'leave_rejected') { toastType = 'warning'; }
                                else if (notif.type === 'wc_order_changed') { toastType = 'warning'; duration = 15000; }

                                this.addToast(toastType, notif.title, notif.body || notif.message, duration);
                            });
                        }
                        lastCheck = Date.now();
                    } catch (e) {
                        // Silent fail for polling
                    }
                }, 30000); // Check every 30 seconds
            }
        };
    }
    </script>

    @stack('scripts')

    {{-- Tom Select (CDN) + initializer for <select data-tom-select>.
         Used for selects that live inside wire:ignore (cascading wizard
         province/city) — survives Livewire morphs cleanly. --}}
    <script src="/vendor/js/tom-select.complete.min.js"></script>
    <script>
    (function () {
        function persianNorm(s) {
            return String(s == null ? '' : s).replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim();
        }
        function initTomSelect(sel) {
            if (!sel || sel.tomselect || typeof TomSelect === 'undefined') return;
            try {
                new TomSelect(sel, {
                    create: false,
                    allowEmptyOption: true,
                    placeholder: sel.dataset.placeholder || 'انتخاب کنید...',
                    searchField: ['text'],
                    score: function (search) {
                        var q = persianNorm(search);
                        return function (item) {
                            var t = persianNorm(item.text);
                            return q === '' || t.indexOf(q) !== -1 ? 1 : 0;
                        };
                    },
                });
            } catch (e) {}
        }
        function scan(root) {
            (root || document).querySelectorAll('select[data-tom-select]').forEach(initTomSelect);
        }
        document.addEventListener('DOMContentLoaded', function () { scan(); });
        document.addEventListener('livewire:navigated', function () { scan(); });
        // Late-added selects (e.g. step-changes) get caught here
        new MutationObserver(function (records) {
            records.forEach(function (r) {
                r.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.matches && n.matches('select[data-tom-select]')) initTomSelect(n);
                    else if (n.querySelectorAll) scan(n);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    })();
    </script>

    {{-- Searchable select: any <select data-searchable> becomes a dropdown
         with a Persian-normalized search box. Underlying <select> stays in
         the DOM (display:none) so wire:model and form submit keep working. --}}
    <script>
    (function () {
        const norm = s => String(s ?? '').replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim();

        function init(selectEl) {
            if (selectEl.__tcsInited) return;
            selectEl.__tcsInited = true;

            const wrapper = document.createElement('div');
            wrapper.className = 'tcs-ss relative';
            selectEl.parentNode.insertBefore(wrapper, selectEl);
            wrapper.appendChild(selectEl);
            selectEl.style.display = 'none';

            const display = document.createElement('button');
            display.type = 'button';
            display.className = (selectEl.className || '') + ' tcs-ss-display flex items-center justify-between text-right';
            display.style.width = '100%';

            const labelSpan = document.createElement('span');
            labelSpan.className = 'truncate';
            const arrow = document.createElement('span');
            arrow.innerHTML = '▾';
            arrow.className = 'text-xs text-gray-400 ms-2';
            display.appendChild(labelSpan);
            display.appendChild(arrow);
            wrapper.appendChild(display);

            const dropdown = document.createElement('div');
            dropdown.className = 'tcs-ss-dropdown absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-72 overflow-hidden hidden';

            const search = document.createElement('input');
            search.type = 'text';
            search.placeholder = 'جستجو...';
            search.className = 'w-full px-3 py-2 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 text-sm focus:outline-none';

            const list = document.createElement('ul');
            list.className = 'overflow-y-auto max-h-56';

            dropdown.appendChild(search);
            dropdown.appendChild(list);
            wrapper.appendChild(dropdown);

            function readOptions() {
                return Array.from(selectEl.options).map(o => ({
                    value: o.value, label: o.text, disabled: o.disabled,
                }));
            }

            function render(query = '') {
                const q = norm(query);
                const opts = readOptions();
                const items = q ? opts.filter(o => norm(o.label).includes(q)) : opts;
                list.innerHTML = '';
                if (items.length === 0) {
                    const li = document.createElement('li');
                    li.textContent = 'یافت نشد';
                    li.className = 'px-3 py-2 text-sm text-gray-400';
                    list.appendChild(li);
                    return;
                }
                items.forEach(opt => {
                    const li = document.createElement('li');
                    li.textContent = opt.label;
                    const isSelected = String(opt.value) === String(selectEl.value);
                    li.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700' +
                        (isSelected ? ' bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 font-medium' : '');
                    if (opt.disabled) {
                        li.className += ' opacity-50 pointer-events-none';
                    } else {
                        li.addEventListener('click', () => {
                            selectEl.value = opt.value;
                            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                            selectEl.dispatchEvent(new Event('input', { bubbles: true }));
                            updateDisplay();
                            close();
                        });
                    }
                    list.appendChild(li);
                });
            }

            function updateDisplay() {
                const opts = readOptions();
                const cur = opts.find(o => String(o.value) === String(selectEl.value));
                if (cur && cur.value !== '') {
                    labelSpan.textContent = cur.label;
                    labelSpan.classList.remove('text-gray-400');
                } else {
                    labelSpan.textContent = (opts[0] && opts[0].value === '') ? opts[0].label : (selectEl.dataset.placeholder || '— انتخاب کنید —');
                    labelSpan.classList.add('text-gray-400');
                }
                // sync disabled state from underlying select to our button
                display.disabled = selectEl.disabled;
                if (selectEl.disabled) {
                    display.classList.add('opacity-50', 'cursor-not-allowed');
                    close();
                } else {
                    display.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            function open() {
                if (selectEl.disabled) return;
                dropdown.classList.remove('hidden');
                search.value = '';
                render();
                setTimeout(() => search.focus(), 0);
            }
            function close() { dropdown.classList.add('hidden'); }

            display.addEventListener('click', () => {
                dropdown.classList.contains('hidden') ? open() : close();
            });
            search.addEventListener('input', () => render(search.value));
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target)) close();
            });

            // Re-render on Livewire-driven option/value changes (e.g. cascading
            // city select after province changes, or wire:model.live update).
            const observer = new MutationObserver(() => {
                updateDisplay();
                // اگر dropdown باز است، لیست را بعد از تغییر options/disabled
                // به‌روز کن تا کاربر گزینه‌های جدید را ببیند.
                if (!dropdown.classList.contains('hidden')) {
                    render(search.value);
                }
            });
            observer.observe(selectEl, { childList: true, subtree: true, attributes: true });

            updateDisplay();
        }

        function scan(root) {
            (root || document).querySelectorAll('select[data-searchable]').forEach(init);
        }

        document.addEventListener('DOMContentLoaded', () => scan());
        document.addEventListener('livewire:navigated', () => scan());
        document.addEventListener('livewire:initialized', () => {
            if (window.Livewire && Livewire.hook) {
                Livewire.hook('morph.added', ({ el }) => {
                    if (el && el.tagName === 'SELECT' && el.hasAttribute('data-searchable')) init(el);
                    else if (el) scan(el);
                });
            }
        });
        // Late safety: anything injected outside Livewire still gets caught.
        new MutationObserver(records => {
            for (const r of records) for (const n of r.addedNodes) {
                if (n.nodeType === 1) {
                    if (n.tagName === 'SELECT' && n.hasAttribute('data-searchable')) init(n);
                    else scan(n);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    })();
    </script>

    @include('components.call-notification')
    @if(!request()->routeIs('admin.messenger'))
        @include('components.chat-widget')
    @endif
    @if(request()->routeIs('warehouse.*'))
        @include('components.order-search-widget')
    @endif
</body>
</html>
