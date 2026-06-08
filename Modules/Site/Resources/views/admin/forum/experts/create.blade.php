@extends('layouts.admin')
@section('page-title', 'افزودن کارشناس')

@section('main')
<div class="p-6 max-w-3xl">
    <a href="{{ route('site.admin.forum.experts.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <h1 class="text-xl font-bold mt-2 mb-4">افزودن کارشناس</h1>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('site.admin.forum.experts.store') }}">
            @include('site::admin.forum.experts._form')
        </form>
    </div>
</div>
@endsection
