@extends('layouts.admin')
@section('page-title', 'داشبورد')
@section('main')
<div class="space-y-6">
    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Staff Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="text-2xl font-bold text-gray-900">{{ number_format($stats['staff_count']) }}</h3>
                <p class="text-sm text-gray-600 mt-1">کارمندان</p>
            </div>
        </div>

        <!-- Messenger Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.messenger') }}" class="text-lg font-bold text-gray-900 hover:text-blue-600">پیام‌رسان</a>
                <p class="text-sm text-gray-600 mt-1">ارتباط با همکاران</p>
            </div>
        </div>

        <!-- Staff Management Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.staff.index') }}" class="text-lg font-bold text-gray-900 hover:text-blue-600">مدیریت پرسنل</a>
                <p class="text-sm text-gray-600 mt-1">افزودن و ویرایش کارمندان</p>
            </div>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="bg-white rounded-xl shadow-sm p-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">خوش آمدید به پنل تعمیرآنلاین</h2>
            <p class="text-gray-600 mb-6">از منوی کناری برای دسترسی به بخش‌های مختلف استفاده کنید</p>
            <div class="flex justify-center gap-4">
                <a href="{{ route('admin.messenger') }}" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    ورود به پیام‌رسان
                </a>
                <a href="{{ route('admin.staff.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    مدیریت پرسنل
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
