@extends('layouts.admin')

@section('page-title', 'افزودن شهر')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">افزودن شهر</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.cities.store') }}" method="POST">
            @include('crm::cities._form')
        </form>
    </div>
</div>
@endsection
