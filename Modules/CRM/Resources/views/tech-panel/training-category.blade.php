@extends('crm::tech-panel.layout')

@section('title', $category->name . ' — آموزش')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.training') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت به دسته‌بندی‌ها">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base truncate px-3">{{ $category->name }}</div>
            <div class="w-10"></div>
        </div>
        @if(! empty($category->description))
            <div class="px-6 mt-3 text-center">
                <p class="text-white/80 text-xs leading-7 max-w-md mx-auto">{{ $category->description }}</p>
            </div>
        @endif
    </div>

    {{-- لیست ویدیوها --}}
    <div class="-mt-12 mx-3">
        @if($videos->isEmpty())
            <div class="bg-white rounded-2xl p-8 shadow-sm text-center">
                <div class="text-sm text-gray-500">ویدیویی در این دسته نیست.</div>
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 bg-brand-50/40">
                    <span class="text-[10px] text-brand-700 font-bold">{{ $videos->count() }} ویدیو</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($videos as $video)
                        <a href="{{ route('tech.training.show', $video) }}"
                           class="flex items-center gap-3 px-4 py-3 active:bg-gray-50">
                            <div class="flex-shrink-0 w-20 h-14 rounded-lg overflow-hidden bg-gray-100 relative">
                                @if($video->thumbnail)
                                    <img src="{{ $video->thumbnailUrl() }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900">
                                        <svg class="w-6 h-6 text-white/80" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                    <span class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-brand-700" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-gray-900 truncate">{{ $video->title }}</div>
                                @if($video->description)
                                    <div class="text-[11px] text-gray-500 mt-0.5 line-clamp-2 leading-6">{{ $video->description }}</div>
                                @endif
                                @if($video->duration_seconds)
                                    <div class="text-[10px] text-gray-400 mt-1" dir="ltr">
                                        {{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @endif
                            </div>
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
