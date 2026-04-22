@extends('layouts.admin')

@section('page-title', 'ویرایش مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش مشتری: {{ $customer->full_name }}</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.customers.update', $customer) }}" method="POST">
            @method('PUT')
            @include('crm::customers._form')
        </form>
    </div>
</div>
@endsection
