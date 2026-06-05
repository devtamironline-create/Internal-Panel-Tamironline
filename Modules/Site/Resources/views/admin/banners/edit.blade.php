@extends('layouts.admin')
@section('page-title', 'ویرایش بنر — ' . $banner->title)

@section('main')
<div class="p-6 max-w-4xl">
    <a href="{{ route('site.admin.banners.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <h1 class="text-xl font-bold mt-2 mb-4">ویرایش: {{ $banner->title }}</h1>
    <form method="POST" action="{{ route('site.admin.banners.update', $banner->id) }}">
        @method('PUT')
        @include('site::admin.banners._form', ['banner' => $banner])
    </form>
</div>
@endsection
