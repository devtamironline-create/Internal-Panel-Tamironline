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
                {{-- ویدیو با پلیر Plyr — fullscreen در PWA با fallback های
                     ساخت‌مند خود کتابخانه (شامل CSS pseudo-fullscreen
                     وقتی API مرورگر بلاک است). assetها لوکال هستند. --}}
                <link rel="stylesheet" href="{{ asset('vendor/css/plyr.css') }}">
                <style>
                    /* bottom-nav پنل تکنسین stacking context خود را دارد،
                       پس z-index Plyr رویش اثر ندارد. JS هنگام enterfullscreen
                       کلاس video-fullscreen به body می‌دهد و این CSS
                       شناورها را display:none می‌کند — مستقل از stacking context. */
                    body.video-fullscreen nav.nav-safe,
                    body.video-fullscreen [data-bottom-nav],
                    body.video-fullscreen .fixed.bottom-0 {
                        display: none !important;
                    }

                    /* z-index ماکزیمم روی wrapper. position:fixed و background:#000
                       را خود Plyr ست می‌کند، ما فقط بالا می‌بریم. */
                    .plyr--fullscreen-fallback {
                        z-index: 2147483647 !important;
                    }
                    /* کنترل‌ها در fullscreen دائم قابل دیدن */
                    .plyr--fullscreen-fallback .plyr__controls {
                        opacity: 1 !important;
                        visibility: visible !important;
                        transform: none !important;
                        z-index: 2147483647 !important;
                    }
                </style>
                <video id="tcs-plyr" playsinline controls preload="metadata"
                       class="block w-full bg-black"
                       style="aspect-ratio: 16/9;"
                       data-video-id="{{ $video->id }}"
                       data-src-main="{{ $video->playbackUrl() }}"
                       data-src-low="{{ $video->lowPlaybackUrl() ?? '' }}"
                       @if($video->thumbnail) poster="{{ $video->thumbnailUrl() }}" @endif>
                    <source src="{{ $video->playbackUrl() }}" type="video/mp4">
                    مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                </video>
                @if($video->lowPlaybackUrl())
                    {{-- سوییچ کیفیت — برای نت ضعیف --}}
                    <div id="tcs-quality" class="flex items-center gap-2 px-3 py-2 bg-gray-900 text-[11px]">
                        <span class="text-gray-400">کیفیت:</span>
                        <button type="button" data-q="main" class="tcs-q-btn px-2.5 py-1 rounded-lg font-bold bg-brand-500 text-white">اصلی</button>
                        <button type="button" data-q="low" class="tcs-q-btn px-2.5 py-1 rounded-lg font-bold bg-gray-700 text-gray-200">کم‌حجم (نت ضعیف)</button>
                    </div>
                @endif
                <script src="{{ asset('vendor/js/plyr.min.js') }}"></script>
                <script>
                (function () {
                    if (typeof Plyr === 'undefined') return;
                    var el = document.getElementById('tcs-plyr');
                    if (! el) return;
                    var player = new Plyr(el, {
                        // SVG sprite لوکال — جلوگیری از fetch CDN
                        iconUrl: '{{ asset('vendor/js/plyr.svg') }}',
                        // fullscreen — اجبار به CSS fallback خود Plyr (نه
                        // Fullscreen API مرورگر). در Android PWA و iOS
                        // standalone، API بومی کنترل‌ها را قطع می‌کند یا
                        // اصلاً کار نمی‌کند. با 'force' همیشه از pseudo-
                        // fullscreen خود Plyr استفاده می‌کنیم که کنترل‌های
                        // داخل پلیر دست‌نخورده باقی می‌مانند.
                        fullscreen: { enabled: true, fallback: 'force', iosNative: false },
                        controls: ['play-large','play','progress','current-time','duration','mute','volume','fullscreen'],
                        // کنترل‌ها روی موبایل اتوهاید نشوند — دائم در دسترس.
                        hideControls: false,
                        // ست landscape هنگام fullscreen — اگر اجازه باشد
                        ratio: '16:9',
                        i18n: {
                            play: 'پخش',
                            pause: 'مکث',
                            mute: 'بی‌صدا',
                            unmute: 'صدادار',
                            enterFullscreen: 'تمام صفحه',
                            exitFullscreen: 'خروج از تمام صفحه',
                            seek: 'جستجو',
                            played: 'پخش‌شده',
                            buffered: 'بافر',
                            currentTime: 'زمان فعلی',
                            duration: 'مدت زمان',
                            volume: 'صدا',
                            quality: 'کیفیت',
                            speed: 'سرعت',
                        },
                    });

                    // وقتی Plyr وارد fullscreen می‌شود، bottom-nav و سایر
                    // UI سراسری باید پنهان شوند تا روی ویدیو نیفتند.
                    // (z-index کافی نبود چون bottom-nav stacking context
                    // خودش را دارد.)
                    player.on('enterfullscreen', function () {
                        document.body.classList.add('video-fullscreen');
                    });
                    player.on('exitfullscreen', function () {
                        document.body.classList.remove('video-fullscreen');
                    });

                    // ─── مقاوم‌سازی برای نتِ ضعیف ───
                    var posKey = 'tcs-video-pos-' + el.dataset.videoId;

                    // ادامه از همان‌جا که قطع شد (حتی بعد از بستن صفحه).
                    el.addEventListener('loadedmetadata', function () {
                        var saved = parseFloat(localStorage.getItem(posKey) || '0');
                        if (saved > 5 && el.duration && saved < el.duration - 10 && el.currentTime < 1) {
                            el.currentTime = saved;
                        }
                    }, { once: true });
                    var lastSave = 0;
                    el.addEventListener('timeupdate', function () {
                        var now = Date.now();
                        if (now - lastSave > 5000 && el.currentTime > 0) {
                            lastSave = now;
                            try { localStorage.setItem(posKey, String(el.currentTime)); } catch (e) {}
                        }
                    });
                    el.addEventListener('ended', function () {
                        try { localStorage.removeItem(posKey); } catch (e) {}
                    });

                    // retry خودکار بعد از خطا/گیرکردن طولانی — از همان ثانیه.
                    var retries = 0, stallTimer = null;
                    function retryFromCurrentTime() {
                        if (retries >= 6) return;
                        retries++;
                        var t = el.currentTime || parseFloat(localStorage.getItem(posKey) || '0');
                        var wasPlaying = ! el.paused;
                        setTimeout(function () {
                            el.load();
                            el.addEventListener('loadedmetadata', function () {
                                if (t > 0) el.currentTime = t;
                                if (wasPlaying) { el.play().catch(function () {}); }
                            }, { once: true });
                        }, Math.min(15000, 1000 * retries)); // backoff: ۱ تا ۱۵ ثانیه
                    }
                    el.addEventListener('error', retryFromCurrentTime);
                    el.addEventListener('stalled', function () {
                        clearTimeout(stallTimer);
                        stallTimer = setTimeout(function () {
                            if (! el.paused && el.readyState < 3) retryFromCurrentTime();
                        }, 15000);
                    });
                    el.addEventListener('playing', function () { retries = 0; clearTimeout(stallTimer); });

                    // ─── سوییچ کیفیت (اگر نسخهٔ کم‌حجم موجود است) ───
                    var qWrap = document.getElementById('tcs-quality');
                    if (qWrap && el.dataset.srcLow) {
                        qWrap.querySelectorAll('.tcs-q-btn').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var target = btn.dataset.q === 'low' ? el.dataset.srcLow : el.dataset.srcMain;
                                var source = el.querySelector('source');
                                if (! source || source.getAttribute('src') === target) return;
                                var t = el.currentTime, wasPlaying = ! el.paused;
                                source.setAttribute('src', target);
                                el.load();
                                el.addEventListener('loadedmetadata', function () {
                                    el.currentTime = t;
                                    if (wasPlaying) { el.play().catch(function () {}); }
                                }, { once: true });
                                qWrap.querySelectorAll('.tcs-q-btn').forEach(function (b) {
                                    b.className = 'tcs-q-btn px-2.5 py-1 rounded-lg font-bold ' +
                                        (b === btn ? 'bg-brand-500 text-white' : 'bg-gray-700 text-gray-200');
                                });
                            });
                        });
                    }
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

    {{-- دکمهٔ «دیدم» — برای training gate. اگر آموزش قبلاً تمام شده،
         این دکمه فقط شمارش بالا را به‌روز می‌کند ولی تأثیر دیگری ندارد. --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        @if($alreadyWatched ?? false)
            <div class="flex items-center justify-center gap-2 py-3 bg-emerald-50 text-emerald-700 rounded-xl text-sm font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                این ویدیو قبلاً دیده شده
            </div>
        @else
            <form method="POST" action="{{ route('tech.training.video-watched', $video) }}">
                @csrf
                <button type="submit"
                        class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition">
                    ✓ این ویدیو را دیدم
                </button>
            </form>
            @if(! ($technician->isTrainingCompleted() ?? false) && isset($progress))
                <p class="text-[11px] text-gray-500 text-center mt-2">
                    {{ $progress['watched'] }} از {{ $progress['total'] }} ویدیو دیده شده — {{ $progress['remaining'] }} ویدیو باقی مانده
                </p>
            @endif
        @endif
    </div>

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
