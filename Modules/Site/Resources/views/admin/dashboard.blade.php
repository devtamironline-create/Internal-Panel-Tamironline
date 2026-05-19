@extends('layouts.admin')

@section('page-title', 'مدیریت سایت')

@section('main')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">مدیریت سایت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">از این بخش محتوای سایت، صفحات، بلاگ و تنظیمات عمومی را مدیریت کنید.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- صفحات استاتیک --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">صفحات استاتیک</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت صفحاتی مانند درباره‌ ما، تماس با ما و قوانین.</p>
        </div>

        {{-- بلاگ --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">بلاگ و مقالات</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">انتشار و مدیریت پست‌های وبلاگ.</p>
        </div>

        {{-- بنر و اسلایدر --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">بنر و اسلایدر</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت بنرهای صفحه اصلی و اسلایدرها.</p>
        </div>

        {{-- برندها (CRM) --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">برندها</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت برندها از طریق ماژول CRM (داده مشترک).</p>
        </div>

        {{-- دستگاه‌ها (CRM) --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-purple-50 text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">دستگاه‌ها</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت دستگاه‌ها از طریق ماژول CRM (داده مشترک).</p>
        </div>

        {{-- تنظیمات سایت --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">تنظیمات عمومی</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">تنظیمات سئو، اطلاعات تماس و شبکه‌های اجتماعی.</p>
        </div>
    </div>
</div>
@endsection
