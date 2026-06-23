@extends('layouts.admin')

@section('page-title', 'ویرایش توصیه‌نامه — ' . $review->author_name)

@section('main')
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold">ویرایش نظر / توصیه‌نامه</h1>
        <a href="{{ route('site.admin.reviews.index', ['type' => 'audio']) }}" class="text-sm text-gray-600 hover:underline">&larr; بازگشت</a>
    </div>

    <form method="POST" action="{{ route('site.admin.reviews.update', $review->id) }}">
        @csrf @method('PUT')
        @include('site::admin.reviews._form')

        <div class="flex items-center gap-3 mt-6 sticky bottom-0 bg-gray-50/80 dark:bg-gray-900/80 backdrop-blur py-3 -mx-1 px-1 rounded-lg">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">به‌روزرسانی</button>
            <a href="{{ route('site.admin.reviews.index', ['type' => 'audio']) }}" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm">انصراف</a>
        </div>
    </form>

    <form method="POST" action="{{ route('site.admin.reviews.destroy', $review->id) }}"
          onsubmit="return confirm('حذف کامل این مورد؟');" class="mt-4">
        @csrf @method('DELETE')
        <button type="submit" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm">حذف این مورد</button>
    </form>
</div>
@endsection
