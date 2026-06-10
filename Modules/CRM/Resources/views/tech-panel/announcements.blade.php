@extends('crm::tech-panel.layout')

@section('title', 'اعلانات')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">اعلانات</div>
            <div class="w-10 h-10"></div>
        </div>
    </div>

    <div class="relative z-10 mx-3 -mt-12 space-y-3">
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800">{{ session('success') }}</div>
        @endif

        @forelse($announcements as $a)
            @php $isAcked = $ackedAt->has($a->id); @endphp
            <div class="bg-white rounded-2xl shadow-sm p-4 {{ $isAcked ? '' : 'border-2 border-amber-300' }}">
                <div class="flex items-start justify-between gap-2 mb-1.5">
                    <div class="font-bold text-sm text-gray-900 leading-6">{{ $a->title }}</div>
                    @if($isAcked)
                        <span class="flex-shrink-0 px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700">✓ دیده شد</span>
                    @else
                        <span class="flex-shrink-0 px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-800">جدید</span>
                    @endif
                </div>
                <p class="text-xs text-gray-600 leading-6 whitespace-pre-line">{{ $a->body }}</p>
                <div class="flex items-center justify-between gap-2 mt-3">
                    <span class="text-[10px] text-gray-400" dir="ltr">@jdatetime($a->created_at)</span>
                    @unless($isAcked)
                        <form method="POST" action="{{ route('tech.announcements.ack', $a) }}">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold active:scale-95 transition">
                                متوجه شدم ✓
                            </button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm p-8 text-center">
                <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 3.94c.59-1.25 2.73-1.25 3.32 0a1.83 1.83 0 002.06 1.06c1.36-.29 2.5 1.27 1.79 2.47a1.83 1.83 0 00.44 2.27c1.05.9.62 2.74-.74 3.03a1.83 1.83 0 00-1.43 1.81c.05 1.39-1.74 2.21-2.8 1.31a1.83 1.83 0 00-2.32 0c-1.06.9-2.85.08-2.8-1.31a1.83 1.83 0 00-1.43-1.81c-1.36-.29-1.79-2.13-.74-3.03a1.83 1.83 0 00.44-2.27c-.71-1.2.43-2.76 1.79-2.47a1.83 1.83 0 002.06-1.06z"/>
                    </svg>
                </div>
                <div class="text-sm font-bold text-gray-700">اعلانی وجود ندارد</div>
                <div class="text-xs text-gray-400 mt-1">اعلان‌های جدید پشتیبانی اینجا نمایش داده می‌شوند.</div>
            </div>
        @endforelse
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav')
@endsection
