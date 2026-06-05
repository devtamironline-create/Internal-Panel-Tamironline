@extends('layouts.admin')
@section('page-title', 'افزودن سوال متداول')

@section('main')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('site.admin.faqs.index') }}" class="text-sm text-blue-600 hover:underline inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به مخزن سوالات
        </a>
        <div class="mt-3 flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">افزودن سوال جدید</h1>
                <p class="text-sm text-gray-500 mt-0.5">سوالات منتشرشده قابل انتخاب در صفحات device، brand و device-brand هستند.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('site.admin.faqs.store') }}">
        @csrf
        @include('site::admin.faqs._form')
        <div class="mt-6 flex items-center gap-2 sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-3 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">ذخیره سوال</button>
            <a href="{{ route('site.admin.faqs.index') }}" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
        </div>
    </form>
</div>
@endsection
