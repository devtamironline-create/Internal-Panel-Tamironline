@extends('layouts.admin')
@section('page-title', 'افزودن تعطیلی')
@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto">
    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">افزودن تعطیلی</h1>
    @if($errors->any())<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">
        <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>@endif
    <form action="{{ route('customer-app.holidays.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @include('customerapp::admin.holidays._form')
    </form>
</div>
@endsection
