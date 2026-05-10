@extends('crm::tech-panel.layout')

@section('title', $video->title)

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-16"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.training') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base truncate px-3">{{ $video->title }}</div>
            <div class="w-10"></div>
        </div>
    </div>

    {{-- Player --}}
    <div class="relative z-10 -mt-8 mx-3 bg-black rounded-2xl overflow-hidden shadow-lg">
        @php $type = $video->sourceType(); @endphp

        @switch($type)
            @case('mp4')
                {{-- نسبت ۱۶:۹ ثابت (مثل حالت اولیه) — بهتر از natural ratio
                     برای کاربر چون قبل از لود metadata ارتفاع صفر نمی‌شود
                     و کلیک play به‌جای ویدیو روی المنت زیرین نمی‌خورد. --}}
                <div class="relative w-full bg-black" style="aspect-ratio: 16/9;" id="tcs-video-wrap">
                    <video id="tcs-video" controls playsinline webkit-playsinline preload="metadata"
                           class="absolute inset-0 w-full h-full"
                           @if($video->thumbnail) poster="{{ $video->thumbnailUrl() }}" @endif>
                        <source src="{{ $video->playbackUrl() }}" type="video/mp4">
                        مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                    </video>
                    <button type="button" id="tcs-fullscreen"
                            aria-label="تمام صفحه"
                            class="absolute top-2 left-2 z-10 w-9 h-9 rounded-lg bg-black/55 backdrop-blur-sm text-white flex items-center justify-center hover:bg-black/75 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/>
                        </svg>
                    </button>
                </div>

                <script>
                (function () {
                    var btn = document.getElementById('tcs-fullscreen');
                    var wrap = document.getElementById('tcs-video-wrap');
                    var video = document.getElementById('tcs-video');
                    if (! btn || ! wrap || ! video) return;

                    function isFullscreen() {
                        return document.fullscreenElement
                            || document.webkitFullscreenElement
                            || document.mozFullScreenElement
                            || document.msFullscreenElement
                            || video.webkitDisplayingFullscreen;
                    }

                    function tryCall(fn, ctx) {
                        try {
                            var p = fn.call(ctx);
                            // بعضی مرورگرها Promise برمی‌گردانند که reject می‌شود
                            if (p && typeof p.catch === 'function') p.catch(function () {});
                            return true;
                        } catch (e) { return false; }
                    }

                    function enterFullscreen() {
                        // ۱) Android Chrome: video.requestFullscreen() مستقیم — مطمئن‌ترین
                        if (typeof video.requestFullscreen === 'function') {
                            if (tryCall(video.requestFullscreen, video)) return;
                        }
                        if (typeof video.webkitRequestFullscreen === 'function') {
                            if (tryCall(video.webkitRequestFullscreen, video)) return;
                        }
                        // ۲) iOS Safari: webkitEnterFullscreen روی خود video
                        if (typeof video.webkitEnterFullscreen === 'function') {
                            if (tryCall(video.webkitEnterFullscreen, video)) return;
                        }
                        // ۳) fallback روی wrapper
                        var fns = ['requestFullscreen', 'webkitRequestFullscreen', 'mozRequestFullScreen', 'msRequestFullscreen'];
                        for (var i = 0; i < fns.length; i++) {
                            if (typeof wrap[fns[i]] === 'function') {
                                if (tryCall(wrap[fns[i]], wrap)) return;
                            }
                        }
                        alert('مرورگر شما از حالت تمام‌صفحه پشتیبانی نمی‌کند. ویدیو را خارج از حالت PWA باز کنید.');
                    }

                    function exitFullscreen() {
                        var fns = ['exitFullscreen', 'webkitExitFullscreen', 'mozCancelFullScreen', 'msExitFullscreen'];
                        for (var i = 0; i < fns.length; i++) {
                            if (typeof document[fns[i]] === 'function') {
                                if (tryCall(document[fns[i]], document)) return;
                            }
                        }
                    }

                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isFullscreen()) exitFullscreen();
                        else enterFullscreen();
                    });
                })();
                </script>
                @break

            @case('aparat')
                <div class="w-full" style="aspect-ratio: 16/9;">
                    <iframe src="{{ $video->aparatEmbedUrl() ?: $video->video_url }}"
                            class="w-full h-full" allowfullscreen
                            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
                @break

            @case('youtube')
                <div class="w-full" style="aspect-ratio: 16/9;">
                    <iframe src="{{ $video->youtubeEmbedUrl() ?: $video->video_url }}"
                            class="w-full h-full" allowfullscreen
                            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
                @break

            @default
                <div class="w-full bg-gray-100 p-6 text-center" style="aspect-ratio: 16/9;">
                    <p class="text-sm text-gray-700 mb-3">پخش این ویدیو در صفحه پشتیبانی نمی‌شود.</p>
                    <a href="{{ $video->video_url }}" target="_blank"
                       class="inline-block px-4 py-2 bg-brand-700 text-white rounded-lg text-sm">
                        باز کردن لینک ویدیو
                    </a>
                </div>
        @endswitch
    </div>

    {{-- Title + description --}}
    <div class="mx-3 mt-3 bg-white rounded-2xl shadow-sm p-4">
        @if($video->category)
            <div class="text-[11px] text-brand-700 font-medium mb-1">
                {{ $video->category->name }}
            </div>
        @endif

        <div class="text-base font-bold text-gray-900 leading-7">{{ $video->title }}</div>

        @if($video->duration_seconds)
            <div class="text-[11px] text-gray-400 mt-1" dir="ltr">
                مدت: {{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}
            </div>
        @endif

        @if($video->description)
            <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-700 leading-7 whitespace-pre-line">{{ $video->description }}</div>
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
