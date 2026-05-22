@extends('layouts.admin')
@section('page-title', 'افزودن بنر')

@section('main')
<div class="p-6 max-w-3xl">
    <div class="mb-4">
        <a href="{{ route('site.admin.banners.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    </div>
    <form method="POST" action="{{ route('site.admin.banners.store') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        @csrf
        <h1 class="text-xl font-bold mb-4">افزودن بنر</h1>
        @include('site::admin.banners._form')
        <div class="mt-6 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">ذخیره</button>
            <a href="{{ route('site.admin.banners.index') }}" class="px-4 py-2 bg-gray-100 rounded text-sm">انصراف</a>
        </div>
    </form>
</div>
@endsection
