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
            <div class="w-24 h-24 mx-auto rounded-full bg-brand-500 flex items-center justify-center text-white text-4xl font-bold">
                {{ mb_substr($user->first_name ?? 'U', 0, 1) }}
            </div>
            <h2 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">{{ $user->full_name }}</h2>
            <p class="text-gray-500 dark:text-gray-400">{{ $user->roles->first()?->name ?? 'کاربر' }}</p>
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
