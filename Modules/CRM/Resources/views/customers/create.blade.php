@extends('layouts.admin')

@section('page-title', 'افزودن مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">افزودن مشتری</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.customers.store') }}" method="POST">
            @include('crm::customers._form')
        </form>
    </div>
</div>
@endsection
