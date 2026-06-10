@extends('layouts.admin')
@section('page-title', 'افزودن نوع خدمت')
@section('main')
<div class="p-4 md:p-6 max-w-3xl mx-auto">
    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">افزودن نوع خدمت</h1>
    <form action="{{ route('crm.service-types.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @include('crm::service-types._form')
    </form>
</div>
@endsection
