@extends('crm::tech-panel.layout')

@section('title', $pageTitle ?? 'صفحه')

@section('body')
<div class="bg-gray-50 min-h-screen pb-nav">
    {{-- Header --}}
    <div class="relative rounded-b-[28px] px-5 pt-6 pb-8"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 60%);">
        <div class="flex items-center justify-between">
            <a href="{{ route('tech.dashboard') }}" class="w-9 h-9 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <h1 class="text-white font-bold">{{ $pageTitle ?? 'صفحه' }}</h1>
            <div class="w-9"></div>
        </div>
    </div>

    {{-- Body --}}
    <div class="px-5 -mt-5">
        <div class="bg-white rounded-2xl p-8 border border-gray-100 text-center">
            <div class="w-16 h-16 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="font-bold text-gray-900 mb-1">در حال پیاده‌سازی</div>
            <p class="text-sm text-gray-500 leading-7">{{ $pageDescription ?? 'این صفحه در فاز بعدی فعال می‌شود.' }}</p>
        </div>
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => $currentNav ?? null])
@endsection
