@extends('layouts.admin')

@section('page-title', 'ثبت سفارش')

@section('main')
<div class="p-4 md:p-6">
    <div class="mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ثبت سفارش تعمیر</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">اطلاعات را در ۵ مرحله وارد کنید.</p>
    </div>

    @livewire('crm.order-wizard', ['customerId' => $customer?->id])
</div>
@endsection
