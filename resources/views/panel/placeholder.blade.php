@extends('layouts.admin')
@section('page-title', $title ?? 'صفحه')
@section('main')
<div class="flex flex-col items-center justify-center py-16">
    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
    </div>
    <h2 class="text-xl font-semibold text-gray-800 mb-2">{{ $title ?? 'این بخش' }}</h2>
    <p class="text-gray-600 mb-6">این بخش در فاز بعدی پیاده‌سازی خواهد شد</p>
    <a href="{{ url()->previous() }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">بازگشت</a>
</div>
@endsection
