@extends('layouts.base')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-600 to-indigo-800 py-12 px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white/10 backdrop-blur-sm mb-4">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">سیستم مدیریت هاستینگ</h1>
            <p class="text-blue-200 mt-2">@yield('subtitle', 'به حساب کاربری خود وارد شوید')</p>
        </div>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @yield('form')
        </div>
    </div>
</div>
@endsection
