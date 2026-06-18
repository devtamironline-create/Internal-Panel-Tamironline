@extends('layouts.admin')

@section('page-title', 'هم‌نوع‌خواری کلمات (Cannibalization)')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">هم‌نوع‌خواری کلمات (Cannibalization)</h1>
            <p class="text-sm text-gray-500">
                صفحاتی که برای یک کلمهٔ کلیدی یا عنوانِ یکسان با هم رقابت می‌کنند.
            </p>
        </div>
        <a href="{{ route('seo.admin.dashboard') }}" class="text-sm text-blue-600 hover:underline">داشبورد سئو &larr;</a>
    </div>

    {{-- تداخل کلمهٔ کلیدی --}}
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
        تداخل کلمهٔ کلیدی
        <span class="text-xs font-normal text-gray-400">(یک کلمهٔ کلیدی اصلی که چند صفحه آن را هدف گرفته‌اند)</span>
    </h2>

    @if(empty($keywordConflicts))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500 mb-8">
            تداخل کلمهٔ کلیدی‌ای یافت نشد.
        </div>
    @else
        <div class="space-y-4 mb-8">
            @foreach($keywordConflicts as $group)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                        <div class="font-bold text-gray-800 dark:text-white">
                            «{{ $group['keyword'] }}»
                            <span class="text-xs font-normal text-gray-400">— {{ count($group['pages']) }} صفحه</span>
                        </div>
                        <div class="text-xs text-amber-600">{{ $suggestion }}</div>
                    </div>
                    <ul class="space-y-1">
                        @foreach($group['pages'] as $page)
                            <li class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2 flex-wrap">
                                <span>{{ $page['title'] ?: '(بدون عنوان)' }}</span>
                                @if($page['url'])
                                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener"
                                       class="text-xs text-blue-600 hover:underline font-mono ltr truncate max-w-md" dir="ltr"
                                       title="{{ $page['url'] }}">{{ $page['url'] }}</a>
                                @else
                                    <span class="text-xs text-gray-400">(URL در دسترس نیست)</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif

    {{-- عنوان‌های تکراری --}}
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
        عنوان‌های تکراری
        <span class="text-xs font-normal text-gray-400">(چند صفحه با عنوانِ سئوی یکسان)</span>
    </h2>

    @if(empty($duplicateTitles))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500">
            عنوان تکراری‌ای یافت نشد.
        </div>
    @else
        <div class="space-y-4">
            @foreach($duplicateTitles as $group)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                        <div class="font-bold text-gray-800 dark:text-white">
                            {{ $group['pages'][0]['title'] ?? $group['title'] }}
                            <span class="text-xs font-normal text-gray-400">— {{ count($group['pages']) }} صفحه</span>
                        </div>
                        <div class="text-xs text-amber-600">{{ $suggestion }}</div>
                    </div>
                    <ul class="space-y-1">
                        @foreach($group['pages'] as $page)
                            <li class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2 flex-wrap">
                                <span>{{ $page['title'] ?: '(بدون عنوان)' }}</span>
                                @if($page['url'])
                                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener"
                                       class="text-xs text-blue-600 hover:underline font-mono ltr truncate max-w-md" dir="ltr"
                                       title="{{ $page['url'] }}">{{ $page['url'] }}</a>
                                @else
                                    <span class="text-xs text-gray-400">(URL در دسترس نیست)</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
