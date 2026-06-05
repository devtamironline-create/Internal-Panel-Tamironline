@extends('layouts.admin')
@section('page-title', 'ویرایش دسته‌بندی دستگاه')

@section('main')
<div class="p-6 max-w-3xl">
    <div class="mb-4">
        <a href="{{ route('crm.device-categories.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
        <h1 class="text-xl font-bold mt-2">ویرایش: {{ $category->name }}</h1>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('crm.device-categories.update', $category->id) }}">
            @method('PUT')
            @include('crm::device-categories._form')
        </form>
    </div>
</div>
@endsection
