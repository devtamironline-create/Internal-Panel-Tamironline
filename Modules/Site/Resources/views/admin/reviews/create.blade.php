@extends('layouts.admin')

@section('page-title', 'افزودن توصیه‌نامه‌ی صوتی')

@section('main')
<div class="p-6 max-w-3xl">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">افزودن توصیه‌نامه‌ی صوتی</h1>
        <a href="{{ route('site.admin.reviews.index') }}" class="text-sm text-gray-600 hover:underline">&larr; بازگشت</a>
    </div>

    <form method="POST" action="{{ route('site.admin.reviews.store') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        @csrf
        @include('site::admin.reviews._form')

        <div class="flex gap-3 mt-6">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">ذخیره</button>
            <a href="{{ route('site.admin.reviews.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded text-sm">انصراف</a>
        </div>
    </form>
</div>
@endsection
