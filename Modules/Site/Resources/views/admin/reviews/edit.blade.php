@extends('layouts.admin')

@section('page-title', 'ویرایش توصیه‌نامه — ' . $review->author_name)

@section('main')
<div class="p-6 max-w-3xl">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">ویرایش توصیه‌نامه</h1>
        <a href="{{ route('site.admin.reviews.index', ['type' => 'audio']) }}" class="text-sm text-gray-600 hover:underline">&larr; بازگشت</a>
    </div>

    <form method="POST" action="{{ route('site.admin.reviews.update', $review->id) }}"
          class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        @csrf @method('PUT')
        @include('site::admin.reviews._form')

        <div class="flex gap-3 mt-6">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">به‌روزرسانی</button>
            <form method="POST" action="{{ route('site.admin.reviews.destroy', $review->id) }}"
                  onsubmit="return confirm('حذف کامل این توصیه‌نامه؟');" class="inline">
                @csrf @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded text-sm">حذف</button>
            </form>
        </div>
    </form>
</div>
@endsection
