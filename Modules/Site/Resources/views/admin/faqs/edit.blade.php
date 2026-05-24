@extends('layouts.admin')
@section('page-title', 'ویرایش سوال متداول')

@section('main')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('site.admin.faqs.index') }}" class="text-sm text-blue-600 hover:underline inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            بازگشت به مخزن سوالات
        </a>
        <div class="mt-3 flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">ویرایش سوال</h1>
                <p class="text-sm text-gray-500 mt-0.5 truncate">{{ $faq->question }}</p>
            </div>
            <form method="POST" action="{{ route('site.admin.faqs.destroy', $faq->id) }}"
                  onsubmit="return confirm('این سوال حذف شود؟');">
                @csrf @method('DELETE')
                <button class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg">حذف</button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('site.admin.faqs.update', $faq->id) }}">
        @csrf @method('PUT')
        @include('site::admin.faqs._form', ['faq' => $faq])
        <div class="mt-6 flex items-center gap-2 sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-3 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">به‌روزرسانی</button>
            <a href="{{ route('site.admin.faqs.index') }}" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
        </div>
    </form>
</div>
@endsection
