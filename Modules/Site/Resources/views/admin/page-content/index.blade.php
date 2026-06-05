@extends('layouts.admin')

@section('page-title', 'محتوای صفحات سایت')

@section('main')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">محتوای صفحات سایت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            هر صفحه‌ی فرانت Next.js چند سکشن دارد. اینجا محتوای هر سکشن را برای فرانت تامین می‌کنید.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($pages as $p)
        <a href="{{ route('site.admin.page-content.edit', $p['slug']) }}"
           class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700 hover:shadow-md transition block">
            <div class="flex items-start justify-between mb-3">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div class="text-end text-xs">
                    <div class="text-gray-500">سکشن‌ها</div>
                    <div class="text-lg font-bold text-gray-800 dark:text-white">{{ $p['filled'] }} / {{ $p['sections_count'] }}</div>
                </div>
            </div>
            <h3 class="text-base font-semibold text-gray-800 dark:text-white">{{ $p['title'] }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">/{{ $p['slug'] === 'home' ? '' : $p['slug'] }}</p>
            <div class="mt-3 text-xs">
                <span class="text-emerald-600">{{ $p['published'] }} منتشر</span>
                <span class="text-gray-400 mx-1">•</span>
                <span class="text-gray-500">{{ $p['sections_count'] - $p['published'] }} پیش‌نویس/خالی</span>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endsection
