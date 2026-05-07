@extends('crm::tech-panel.layout')

@section('title', 'آموزش')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.profile') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">آموزش‌های تکنسین</div>
            <div class="w-10"></div>
        </div>

        <div class="px-6 mt-4 text-center">
            <p class="text-white/80 text-xs leading-7 max-w-md mx-auto">
                ویدیوهای آموزش، نکات تخصصی و راهنمای انجام سفارش‌ها را در این بخش مشاهده کنید.
            </p>
        </div>
    </div>

    {{-- Content --}}
    <div class="-mt-12 mx-3 space-y-3">
        @php $totalVideos = $categories->sum(fn($c) => $c->activeVideos->count()) + $uncategorized->count(); @endphp

        @if($totalVideos === 0)
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="w-14 h-14 rounded-2xl bg-brand-50 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="font-bold text-gray-900 mb-1">هنوز ویدیوی آموزشی منتشر نشده</div>
                <p class="text-xs text-gray-500 leading-7">
                    به‌زودی محتوای آموزشی توسط مدیر اضافه خواهد شد.
                </p>
            </div>
        @else
            @foreach($categories as $cat)
                @include('crm::tech-panel._partials.training-category', [
                    'title' => $cat->name,
                    'description' => $cat->description,
                    'videos' => $cat->activeVideos,
                ])
            @endforeach

            @if($uncategorized->isNotEmpty())
                @include('crm::tech-panel._partials.training-category', [
                    'title' => 'سایر',
                    'description' => null,
                    'videos' => $uncategorized,
                ])
            @endif
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
