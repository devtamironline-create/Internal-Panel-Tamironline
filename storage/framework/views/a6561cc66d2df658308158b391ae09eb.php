<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('page-title', 'داشبورد'); ?> | تعمیرآنلاین</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="/css/fonts.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/css/persian-datepicker.min.css">
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body
    x-data="{ sidebarToggle: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))"
    :class="{ 'dark': darkMode }"
    class="font-vazir bg-gray-50 dark:bg-gray-900"
>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside
            :class="sidebarToggle ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            class="fixed right-0 top-0 z-50 flex h-screen w-72 flex-col overflow-y-hidden bg-[#1a2d48] px-5 lg:static transition-transform duration-300"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pt-8 pb-7 border-b border-white/10">
                <a href="<?php echo e(route('admin.dashboard')); ?>" class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-white">تعمیرآنلاین</span>
                </a>
                <button @click="sidebarToggle = false" class="lg:hidden text-white/70 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Menu -->
            <nav class="flex-1 overflow-y-auto no-scrollbar py-6">
                <!-- داشبورد -->
                <a href="<?php echo e(route('admin.dashboard')); ?>" class="sidebar-menu-item mb-2 <?php echo e(request()->routeIs('admin.dashboard') ? 'sidebar-menu-item-active' : ''); ?>">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z"/></svg>
                    داشبورد
                </a>

                <!-- پیام‌رسان -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['use-messenger', 'manage-permissions'])): ?>
                <a href="<?php echo e(route('admin.messenger')); ?>" class="sidebar-menu-item mb-2 <?php echo e(request()->routeIs('admin.messenger') ? 'sidebar-menu-item-active' : ''); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    پیام‌رسان
                </a>
                <?php endif; ?>

                <!-- پرسنل -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-staff', 'manage-staff', 'manage-permissions'])): ?>
                <a href="<?php echo e(route('admin.staff.index')); ?>" class="sidebar-menu-item mb-2 <?php echo e(request()->routeIs('admin.staff.*') ? 'sidebar-menu-item-active' : ''); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    پرسنل
                </a>
                <?php endif; ?>

                <!-- کارتابل پرسنلی -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-attendance', 'view-leave', 'manage-attendance', 'manage-leave', 'manage-permissions'])): ?>
                <div class="mt-6" x-data="{ open: <?php echo e(request()->routeIs('attendance.*') || request()->routeIs('leave.*') ? 'true' : 'false'); ?> }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            کارتابل پرسنلی
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-attendance', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('attendance.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('attendance.index') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            حضور و غیاب
                        </a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-leave', 'request-leave', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('leave.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('leave.index') || request()->routeIs('leave.create') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            مرخصی
                        </a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['manage-attendance', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('attendance.admin')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('attendance.admin') || request()->routeIs('attendance.settings') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت حضور و غیاب
                        </a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['manage-leave', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('leave.approvals')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('leave.approvals') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            مدیریت مرخصی
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- کارتابل عملیاتی -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-tasks', 'manage-tasks', 'manage-teams', 'view-reports', 'manage-permissions'])): ?>
                <div class="mt-2" x-data="{ open: <?php echo e(request()->routeIs('tasks.*') || request()->routeIs('teams.*') ? 'true' : 'false'); ?> }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            کارتابل عملیاتی
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-tasks', 'create-tasks', 'manage-tasks', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('tasks.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('tasks.index') || request()->routeIs('tasks.show') || request()->routeIs('tasks.create') || request()->routeIs('tasks.edit') || request()->routeIs('tasks.my') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            مدیریت تسک‌ها
                        </a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['manage-teams', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('teams.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('teams.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            مدیریت تیم‌ها
                        </a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-reports', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('tasks.reports.users')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('tasks.reports.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            گزارش عملکرد
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- OKR -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-okr', 'manage-okr', 'manage-permissions'])): ?>
                <div class="mt-2" x-data="{ open: <?php echo e(request()->routeIs('okr.*') ? 'true' : 'false'); ?> }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            مدیریت OKR
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="<?php echo e(route('okr.dashboard')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('okr.dashboard') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                            داشبورد OKR
                        </a>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['manage-okr', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('okr.cycles.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('okr.cycles.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            دوره‌ها
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo e(route('okr.objectives.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('okr.objectives.index') || request()->routeIs('okr.objectives.show') || request()->routeIs('okr.objectives.create') || request()->routeIs('okr.objectives.edit') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                            اهداف سازمانی
                        </a>
                        <a href="<?php echo e(route('okr.objectives.my')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('okr.objectives.my') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            اهداف من
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Salary -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['view-salary', 'manage-salary', 'manage-permissions'])): ?>
                <div class="mt-2" x-data="{ open: <?php echo e(request()->routeIs('salary.*') ? 'true' : 'false'); ?> }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            حقوق و دستمزد
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="<?php echo e(route('salary.dashboard')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('salary.dashboard') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            حقوق من
                        </a>
                        <a href="<?php echo e(route('salary.history')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('salary.history') || request()->routeIs('salary.show') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            تاریخچه فیش‌ها
                        </a>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['manage-salary', 'manage-permissions'])): ?>
                        <a href="<?php echo e(route('salary.admin.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('salary.admin.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت حقوق
                        </a>
                        <a href="<?php echo e(route('salary.settings.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('salary.settings.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            تنظیمات حقوق
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- مدیریت سیستم -->
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-permissions')): ?>
                <div class="mt-6" x-data="{ open: <?php echo e(request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'true' : 'false'); ?> }">
                    <button @click="open = !open" class="w-full sidebar-menu-item" style="justify-content: space-between;">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            مدیریت سیستم
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="sidebar-submenu">
                        <a href="<?php echo e(route('admin.roles.index')); ?>" class="sidebar-menu-item <?php echo e(request()->routeIs('admin.roles.*') ? 'sidebar-menu-item-active' : ''); ?>">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            نقش‌ها
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </nav>

            <!-- User Info at Bottom -->
            <div class="border-t border-white/10 py-4">
                <div class="flex items-center gap-3 px-2">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold">
                        <?php echo e(mb_substr(auth()->user()->first_name ?? 'A', 0, 1)); ?>

                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium text-white"><?php echo e(auth()->user()->full_name ?? 'کاربر'); ?></div>
                        <div class="text-xs text-white/60"><?php echo e(auth()->user()->roles->first()?->name ?? 'کارمند'); ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Overlay -->
        <div x-show="sidebarToggle" @click="sidebarToggle = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-transition.opacity></div>

        <!-- Main Content -->
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="sticky top-0 z-30 flex w-full border-b border-gray-200 bg-white lg:border-b dark:border-gray-800 dark:bg-gray-900">
                <div class="flex grow flex-col items-center justify-between lg:flex-row lg:px-6">
                    <div class="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4 dark:border-gray-800">
                        <!-- Hamburger -->
                        <button @click="sidebarToggle = !sidebarToggle" class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400">
                            <svg class="fill-current" width="16" height="12" viewBox="0 0 16 12"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"/></svg>
                        </button>

                        <!-- Logo Mobile -->
                        <a href="<?php echo e(route('admin.dashboard')); ?>" class="lg:hidden">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">تعمیرآنلاین</span>
                        </a>

                        <!-- Search -->
                        <div class="hidden lg:block">
                            <div class="relative">
                                <span class="absolute top-1/2 right-4 -translate-y-1/2">
                                    <svg class="fill-gray-500" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"/></svg>
                                </span>
                                <input type="text" placeholder="جستجو یا تایپ دستور..." class="h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pr-12 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 xl:w-[430px] dark:border-gray-800 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 px-5 py-4 lg:px-0">
                        <!-- Dark Mode -->
                        <button @click="darkMode = !darkMode" class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800">
                            <svg x-show="!darkMode" class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z"/></svg>
                            <svg x-show="darkMode" class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z"/></svg>
                        </button>

                        <!-- Notifications -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800">
                                <span class="absolute top-0.5 left-0 h-2 w-2 rounded-full bg-orange-400"></span>
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"/></svg>
                            </button>
                        </div>

                        <!-- User Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-3">
                                <span class="hidden lg:block text-right">
                                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-400"><?php echo e(auth()->user()->full_name ?? 'کاربر'); ?></span>
                                </span>
                                <svg class="hidden lg:block fill-gray-500" width="18" height="20" viewBox="0 0 18 20"><path d="M4.3125 8.65625L9 13.3437L13.6875 8.65625" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-transition class="absolute left-0 mt-4 w-60 rounded-2xl border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-3 pb-3 border-b border-gray-200 dark:border-gray-800">
                                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-400"><?php echo e(auth()->user()->full_name ?? 'کاربر'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php echo e(auth()->user()->mobile); ?></span>
                                </div>
                                <ul class="space-y-1">
                                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">پروفایل</a></li>
                                </ul>
                                <form action="<?php echo e(route('logout')); ?>" method="POST" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                                    <?php echo csrf_field(); ?>
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
                <div class="mb-6 flex items-center gap-3 rounded-lg border border-success-500 bg-success-50 p-4 text-sm text-success-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php echo e(session('success')); ?>

                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
                <div class="mb-6 flex items-center gap-3 rounded-lg border border-error-500 bg-error-50 p-4 text-sm text-error-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?php echo e(session('error')); ?>

                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php echo $__env->yieldContent('main'); ?>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-date@latest/dist/persian-date.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/persian-datepicker@latest/dist/js/persian-datepicker.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.jalali-datepicker').persianDatepicker({
                format: 'YYYY/MM/DD',
                initialValue: false,
                autoClose: true,
                calendar: {
                    persian: {
                        locale: 'fa',
                        showHint: true,
                        leapYearMode: 'algorithmic'
                    }
                },
                toolbox: {
                    enabled: true,
                    calendarSwitch: { enabled: false },
                    todayButton: { enabled: true, text: { fa: 'امروز' } },
                    submitButton: { enabled: true, text: { fa: 'تایید' } }
                },
                dayPicker: { enabled: true, titleFormat: 'YYYY MMMM' },
                onSelect: function(unix) { $(this).trigger('change'); }
            });
        });
    </script>

    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>

    <?php echo $__env->yieldPushContent('scripts'); ?>
    <?php echo $__env->make('components.call-notification', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/Tamironline-CRM/resources/views/layouts/admin.blade.php ENDPATH**/ ?>