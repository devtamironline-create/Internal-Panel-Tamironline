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

                {{-- استراتژی تمام‌صفحه:
                     ۱) ابتدا Fullscreen API روی خود video — این روش
                        کنترل‌های native (پلی/پاز/seek) را حفظ می‌کند و
                        نوار آدرس مرورگر هم پنهان می‌شود. روی Android Chrome،
                        Safari iOS، و اکثر مرورگرها کار می‌کند.
                     ۲) اگر همهٔ APIها fail کردند، CSS pseudo-fullscreen
                        (position:fixed) به‌عنوان fallback. در این حالت
                        wrapper روی نوار آدرس نمی‌رود (محدودیت browser)
                        ولی حداقل ویدیو بزرگ می‌شود. --}}
                <style>
                    .tcs-fs {
                        position: fixed !important;
                        inset: 0 !important;
                        width: 100vw !important;
                        height: 100dvh !important;  /* dvh مساوی viewport بدون نوار آدرس */
                        max-width: 100vw !important;
                        max-height: 100dvh !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        z-index: 2147483647 !important;
                        aspect-ratio: auto !important;
                        background: #000 !important;
                    }
                    .tcs-fs video {
                        width: 100% !important;
                        height: 100% !important;
                        object-fit: contain !important;
                    }
                    /* دکمهٔ تمام‌صفحهٔ سفارشی در حالت fullscreen API نباید
                       روی کنترل‌های native دیده شود — Webkit آن را زیر
                       کنترل‌های native می‌برد. در CSS-fullscreen هم
                       z-index بالا می‌دهیم تا قابل کلیک باشد. */
                    .tcs-fs #tcs-fullscreen { z-index: 2147483646 !important; }
                    body.tcs-fs-lock { overflow: hidden !important; }
                </style>
                <script>
                (function () {
                    var btn = document.getElementById('tcs-fullscreen');
                    var wrap = document.getElementById('tcs-video-wrap');
                    var video = document.getElementById('tcs-video');
                    if (! btn || ! wrap || ! video) return;

                    var icons = {
                        enter: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/></svg>',
                        exit:  '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4H4v5M15 4h5v5M4 15v5h5M20 15v5h-5"/></svg>'
                    };

                    var usingNative = false;

                    function tryNativeOnVideo() {
                        // اولویت با Fullscreen API روی خود <video>:
                        //   - Android Chrome: video.requestFullscreen() → fullscreen واقعی + کنترل‌های native
                        //   - iOS Safari:     video.webkitEnterFullscreen() → fullscreen واقعی
                        //   - Firefox:        video.mozRequestFullScreen()
                        var attempts = [
                            ['requestFullscreen', true],
                            ['webkitRequestFullscreen', true],
                            ['mozRequestFullScreen', true],
                            ['msRequestFullscreen', true],
                            ['webkitEnterFullscreen', false], // iOS — promise نمی‌دهد
                        ];
                        for (var i = 0; i < attempts.length; i++) {
                            var name = attempts[i][0];
                            if (typeof video[name] === 'function') {
                                try {
                                    var ret = video[name]();
                                    if (ret && typeof ret.then === 'function') {
                                        return ret.then(function () {
                                            usingNative = true;
                                            return true;
                                        }).catch(function () { return false; });
                                    }
                                    // sync API (iOS): فرض می‌کنیم موفق بود
                                    usingNative = true;
                                    return Promise.resolve(true);
                                } catch (e) {}
                            }
                        }
                        return Promise.resolve(false);
                    }

                    function enterCssFullscreen() {
                        wrap.classList.add('tcs-fs');
                        document.body.classList.add('tcs-fs-lock');
                        btn.innerHTML = icons.exit;
                        btn.setAttribute('aria-label', 'خروج از تمام صفحه');
                        // قفل جهت‌گیری به landscape — اگر اجازه دهد.
                        try {
                            if (screen.orientation && screen.orientation.lock) {
                                var p = screen.orientation.lock('landscape');
                                if (p && p.catch) p.catch(function(){});
                            }
                        } catch (e) {}
                    }

                    function enter() {
                        tryNativeOnVideo().then(function (ok) {
                            if (! ok) {
                                // همهٔ APIها fail کردند → CSS fallback
                                enterCssFullscreen();
                            }
                            // اگر native کار کرد، نیازی به CSS نیست —
                            // مرورگر ویدیو را در fullscreen واقعی پلی می‌کند.
                        });
                    }

                    function exit() {
                        wrap.classList.remove('tcs-fs');
                        document.body.classList.remove('tcs-fs-lock');
                        btn.innerHTML = icons.enter;
                        btn.setAttribute('aria-label', 'تمام صفحه');
                        usingNative = false;

                        try {
                            if (document.fullscreenElement || document.webkitFullscreenElement) {
                                var fn = document.exitFullscreen || document.webkitExitFullscreen;
                                if (fn) { var p = fn.call(document); if (p && p.catch) p.catch(function(){}); }
                            }
                        } catch (e) {}

                        try {
                            if (screen.orientation && screen.orientation.unlock) {
                                screen.orientation.unlock();
                            }
                        } catch (e) {}
                    }

                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        // اگر در حالت native fullscreen هستیم، خروج با
                        // کنترل‌های native صورت می‌گیرد. در CSS fullscreen
                        // کلیک روی دکمه exit می‌کند.
                        if (wrap.classList.contains('tcs-fs')) exit();
                        else enter();
                    });

                    // Escape کیبورد → خروج
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && wrap.classList.contains('tcs-fs')) exit();
                    });

                    // وقتی fullscreen واقعی پایان یافت (Esc کاربر یا back system)،
                    // اگر دکمهٔ ما در حالت exit است، آن را reset کنیم.
                    function syncNativeExit() {
                        var inNative = document.fullscreenElement
                            || document.webkitFullscreenElement
                            || document.mozFullScreenElement;
                        if (! inNative && usingNative) {
                            usingNative = false;
                            btn.innerHTML = icons.enter;
                            btn.setAttribute('aria-label', 'تمام صفحه');
                            try { screen.orientation && screen.orientation.unlock(); } catch (e) {}
                        }
                    }
                    document.addEventListener('fullscreenchange', syncNativeExit);
                    document.addEventListener('webkitfullscreenchange', syncNativeExit);
                    // iOS: webkitend/beginfullscreen روی خود video
                    video.addEventListener('webkitendfullscreen', function () {
                        usingNative = false;
                        btn.innerHTML = icons.enter;
                    });
                    video.addEventListener('webkitbeginfullscreen', function () {
                        usingNative = true;
                        btn.innerHTML = icons.exit;
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
