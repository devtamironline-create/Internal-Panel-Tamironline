@extends('layouts.admin')
@section('page-title', 'پروفایل من')
@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">پروفایل من</h1>
            <p class="text-gray-600 dark:text-gray-400">مشاهده و ویرایش اطلاعات کاربری</p>
        </div>
        <a href="{{ route('admin.profile.edit') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            ویرایش
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 text-center border-b border-gray-200 dark:border-gray-700">
            <div class="relative inline-block">
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white text-4xl font-bold overflow-hidden ring-4 ring-white dark:ring-gray-700 shadow-lg">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" class="w-full h-full object-cover" alt="{{ $user->full_name }}">
                    @else
                        {{ $user->initials }}
                    @endif
                </div>
                @php
                    $statusColor = $user->getPresenceStatusColor();
                    $statusLabel = $user->getPresenceStatusLabel();
                @endphp
                <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white dark:border-gray-700 bg-{{ $statusColor }}-500" title="{{ $statusLabel }}"></span>
            </div>
            <h2 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">{{ $user->full_name }}</h2>
            <p class="text-gray-500 dark:text-gray-400">{{ $user->roles->first()?->name ?? 'کاربر' }}</p>
            <span class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-300 rounded-full text-sm">
                <span class="w-2 h-2 rounded-full bg-{{ $statusColor }}-500"></span>
                {{ $statusLabel }}
            </span>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div class="px-6 py-4 flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">موبایل</span>
                <span class="font-medium text-gray-900 dark:text-white" dir="ltr">{{ $user->mobile }}</span>
            </div>
            <div class="px-6 py-4 flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">ایمیل</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $user->email ?? '-' }}</span>
            </div>
            <div class="px-6 py-4 flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">تاریخ تولد</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $user->birth_date ?? '-' }}</span>
            </div>
            <div class="px-6 py-4 flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">آخرین ورود</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $user->last_login_at?->diffForHumans() ?? '-' }}</span>
            </div>
            <div class="px-6 py-4 flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">تاریخ عضویت</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $user->created_at->format('Y/m/d') }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
