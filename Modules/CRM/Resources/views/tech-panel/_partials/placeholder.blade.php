@extends('crm::tech-panel.layout')

@section('title', $pageTitle ?? 'صفحه')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- ─────── Hero header (matches dashboard) ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-28"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                {{-- chevron-right (RTL "back") --}}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>

            <div class="text-white font-bold text-base">{{ $pageTitle ?? 'صفحه' }}</div>

            <div class="w-10"></div>
        </div>
    </div>

    {{-- ─────── White sheet (overlapping) ─────── --}}
    <div class="relative z-10 -mt-20 mx-3 bg-white rounded-[28px] shadow-lg pt-4 pb-6 px-4">
        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-5"></div>

        <div class="px-2 py-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="font-bold text-gray-900 mb-2">در حال پیاده‌سازی</div>
            <p class="text-sm text-gray-500 leading-7 max-w-xs mx-auto">
                {{ $pageDescription ?? 'این صفحه در فاز بعدی فعال می‌شود.' }}
            </p>
        </div>
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => $currentNav ?? null])
@endsection
