@extends('crm::tech-panel.layout')

@section('title', 'داشبورد تکنسین')

@php
    $name = trim($technician->firstname_tech ?: $technician->first_name) ?: 'تکنسین';
@endphp

@section('body')
<div class="bg-gray-50 min-h-screen pb-nav">
    {{-- ─────── Hero header ─────── --}}
    <div class="relative overflow-hidden rounded-b-[36px] pb-12"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        {{-- Top bar --}}
        <div class="flex items-center justify-between px-5 pt-5">
            <form action="{{ route('tech.logout') }}" method="POST" class="inline">
                @csrf
                <button class="px-3 py-1.5 rounded-full bg-white/15 backdrop-blur text-white text-xs font-medium border border-white/20 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    خروج
                </button>
            </form>
            <div class="flex items-center gap-2">
                @if($supportPhone)
                    <a href="tel:{{ $supportPhone }}" class="w-9 h-9 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </a>
                @endif
                <a href="{{ route('tech.profile') }}" class="w-9 h-9 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- Greeting --}}
        <div class="px-5 pt-6 text-right">
            <div class="text-white/80 text-sm">سلام،</div>
            <h1 class="text-white text-2xl font-bold mt-0.5">{{ $name }}</h1>
            <p class="text-white/70 text-xs mt-1">امروز چه کارهایی برای انجام داری؟</p>
        </div>
    </div>

    {{-- ─────── Service cards (overlap) ─────── --}}
    <div class="px-5 -mt-8 grid grid-cols-3 gap-3">
        <a href="{{ route('tech.wallet') }}"
           class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col items-center text-center">
            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center mb-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm12 8h.01"/>
                </svg>
            </div>
            <div class="text-sm font-bold text-gray-900">کیف‌پول</div>
            <div class="text-[10px] text-gray-400 mt-0.5">مانده و تراکنش</div>
        </a>

        <a href="{{ route('tech.invoices') }}"
           class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col items-center text-center">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center mb-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="text-sm font-bold text-gray-900">فاکتورها</div>
            <div class="text-[10px] text-gray-400 mt-0.5">صورت‌حساب‌ها</div>
        </a>

        <a href="{{ route('tech.orders') }}"
           class="rounded-2xl p-4 shadow-md flex flex-col items-center text-center text-white"
           style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
            <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center mb-2">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div class="text-sm font-bold">سفارش‌ها</div>
            <div class="text-[10px] text-white/80 mt-0.5">مدیریت کار</div>
        </a>
    </div>

    {{-- ─────── Banner image / brand block ─────── --}}
    <div class="px-5 mt-5">
        <div class="relative rounded-2xl overflow-hidden shadow-sm" style="aspect-ratio: 16/9;">
            @if($brandBanner)
                <img src="{{ asset('storage/' . $brandBanner) }}" alt="banner" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-center px-6"
                     style="background: linear-gradient(135deg, #1e3a8a, #1e40af);">
                    <div>
                        <div class="text-gold-400 text-2xl font-bold leading-tight">کاربلدهای<br>تعمیر آنلاین</div>
                        <div class="flex items-center justify-center gap-2 mt-3 text-[11px]">
                            <span class="px-2 py-0.5 rounded-full bg-gold-400 text-brand-900 font-bold">مجرب</span>
                            <span class="px-2 py-0.5 rounded-full bg-gold-400 text-brand-900 font-bold">متخصص</span>
                            <span class="px-2 py-0.5 rounded-full bg-gold-400 text-brand-900 font-bold">متعهد</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ─────── Quick stats ─────── --}}
    <div class="px-5 mt-5 grid grid-cols-2 gap-3">
        <div class="bg-white rounded-2xl p-4 border border-gray-100">
            <div class="text-xs text-gray-400">درصد کمیسیون</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($technician->percent ?? 0) }}<span class="text-sm font-normal text-gray-400 mr-1">%</span></div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-gray-100">
            <div class="text-xs text-gray-400">وضعیت</div>
            <div class="text-sm font-bold text-emerald-600 mt-2 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ $technician->status === 'active' ? 'فعال' : ($technician->status ?? '—') }}
            </div>
        </div>
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.dashboard'])
@endsection
