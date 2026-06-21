@extends('layouts.admin')
@section('page-title', 'افزودن مقاله')

@section('main')
<div class="p-6 max-w-7xl mx-auto">
    <a href="{{ route('site.admin.blog.articles.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <h1 class="text-xl font-bold mt-2 mb-4">افزودن مقاله</h1>
    <form method="POST" action="{{ route('site.admin.blog.articles.store') }}">
        @include('site::admin.blog.articles._form')
    </form>
</div>
@endsection
