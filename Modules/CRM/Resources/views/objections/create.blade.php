@extends('layouts.admin')
@section('page-title', 'افزودن ایراد')
@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto">
    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">افزودن ایراد جدید</h1>
    <form action="{{ route('crm.objections.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @include('crm::objections._form')
    </form>
</div>
@endsection
