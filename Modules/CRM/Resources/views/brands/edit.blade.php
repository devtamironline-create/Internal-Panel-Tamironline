@extends('layouts.admin')

@section('page-title', 'ویرایش برند')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش برند: {{ $brand->name }}</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.brands.update', $brand) }}" method="POST" enctype="multipart/form-data">
            @method('PUT')
            @include('crm::brands._form')
        </form>
    </div>
</div>
@endsection
