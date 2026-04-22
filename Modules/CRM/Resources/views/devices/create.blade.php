@extends('layouts.admin')

@section('page-title', 'افزودن دستگاه')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">افزودن دستگاه</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">یک دستگاه جدید ثبت کنید.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.devices.store') }}" method="POST">
            @include('crm::devices._form')
        </form>
    </div>
</div>
@endsection
