@extends('layouts.admin')
@section('page-title', 'افزودن بنر')

@section('main')
<div class="p-6 max-w-4xl">
    <a href="{{ route('site.admin.banners.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    <h1 class="text-xl font-bold mt-2 mb-4">افزودن بنر</h1>
    <form method="POST" action="{{ route('site.admin.banners.store') }}">
        @include('site::admin.banners._form')
    </form>
</div>
@endsection
