@extends('layouts.admin')
@section('page-title', 'ویرایش مقاله')

@section('main')
<div class="p-6 max-w-5xl mx-auto">
    <a href="{{ route('site.admin.blog.articles.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <div class="flex items-center justify-between mt-2 mb-4">
        <h1 class="text-xl font-bold">ویرایش: {{ $article->title }}</h1>
        <a href="/blog/{{ $article->slug }}" target="_blank" class="text-sm text-blue-600 hover:underline">مشاهده در سایت ↗</a>
    </div>
    <form method="POST" action="{{ route('site.admin.blog.articles.update', $article->id) }}">
        @method('PUT')
        @include('site::admin.blog.articles._form')
    </form>
</div>
@endsection
