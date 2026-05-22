@extends('layouts.admin')
@section('page-title', 'ویرایش — ' . $page->title)

@section('main')
<div class="p-6">
    <div class="mb-4">
        <a href="{{ route('site.admin.pages.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('site.admin.pages.update', $page->id) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        @csrf @method('PUT')
        <h1 class="text-xl font-bold mb-4">ویرایش صفحه</h1>
        @include('site::admin.pages._form', [
            'page' => $page,
            'selectedTestimonials' => $selectedTestimonials,
            'selectedFaqs' => $selectedFaqs,
        ])
        <div class="mt-6 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">به‌روزرسانی</button>
            <a href="{{ route('site.admin.pages.index') }}" class="px-4 py-2 bg-gray-100 rounded text-sm">انصراف</a>
        </div>
    </form>
</div>
@endsection
