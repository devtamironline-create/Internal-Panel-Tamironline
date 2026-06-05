@extends('layouts.admin')

@section('page-title', 'افزودن صفحه‌ی ترکیبی')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <a href="{{ route('crm.device-brand-pages.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
        <h1 class="text-xl font-bold mt-2">افزودن صفحه‌ی ترکیبی</h1>
        <p class="text-gray-500 mt-1 text-sm">یک رکورد برای یک pair از دستگاه و برند می‌سازد. هر فیلدی که خالی بگذارید از مقدار دستگاه/برند/الگو خوانده می‌شود.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.device-brand-pages.store') }}" method="POST">
            @include('crm::device-brand-pages._form')
        </form>
    </div>
</div>
@endsection
