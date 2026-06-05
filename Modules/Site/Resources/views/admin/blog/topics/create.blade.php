@extends('layouts.admin')
@section('page-title', 'افزودن تاپیک بلاگ')

@section('main')
<div class="p-6 max-w-3xl">
    <a href="{{ route('site.admin.blog.topics.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <h1 class="text-xl font-bold mt-2 mb-4">افزودن تاپیک</h1>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('site.admin.blog.topics.store') }}">
            @include('site::admin.blog.topics._form')
        </form>
    </div>
</div>
@endsection
