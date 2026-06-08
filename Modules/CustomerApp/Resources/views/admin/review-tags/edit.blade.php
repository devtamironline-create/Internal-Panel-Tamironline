@extends('layouts.admin')
@section('page-title', 'ویرایش تگ نظرسنجی')
@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto">
    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">ویرایش تگ: {{ $item->label }}</h1>
    <form action="{{ route('customer-app.review-tags.update', $item) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @method('PUT')
        @include('customerapp::admin.review-tags._form')
    </form>
</div>
@endsection
