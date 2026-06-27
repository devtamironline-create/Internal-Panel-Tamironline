{{-- متغیرهای brand* و appName/supportPhone از طریق View::composer
     در CrmServiceProvider به این view و همهٔ child viewهای tech-panel.*
     تزریق می‌شوند. در parent با php-block تعریف نمی‌شوند چون scope آن
     به section های child منتقل نمی‌شود. --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1E40AF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('tech-pwa/icons/icon.svg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('tech-pwa/icons/icon.svg') }}">
    <title>@yield('title', 'پنل تکنسین') | {{ $appName }}</title>

    <link href="/css/fonts.css" rel="stylesheet">
    <script src="/vendor/js/tailwind.min.js"></script>
    <script defer src="/vendor/js/alpine.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: {
                            300: '#fcd34d',
                            400: '#fbbf24',
                            500: '#f59e0b',
                        },
                    },
                    fontFamily: { sans: ['Vazirmatn', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Vazirmatn', system-ui, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { font-weight: 400; line-height: 1.7; background: #eef0f4; }
        h1, h2, h3, h4, h5, h6, .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        input, select, textarea, button { font-family: inherit; }
        [x-cloak] { display: none !important; }

        /* App-frame: full-bleed on mobile, centered card on larger screens */
        .app-frame {
            min-height: 100vh;
            min-height: 100dvh;
            background: #eef0f4;
            position: relative;
            overflow-x: hidden;
        }
        @media (min-width: 640px) {
            body {
                background: #eef0f4;
                background-attachment: fixed;
                padding: 0;
            }
            .app-frame {
                max-width: 480px;
                margin: 0 auto;
                min-height: 100vh;
                min-height: 100dvh;
                border-radius: 0;
                box-shadow: 0 0 40px -8px rgba(15, 23, 42, 0.12);
                overflow: hidden;
            }
        }

        /* Hide scrollbars in tech panel */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Bottom safe area for iOS notch + bottom nav clearance */
        .pb-nav { padding-bottom: calc(80px + env(safe-area-inset-bottom)); }
        .nav-safe { padding-bottom: env(safe-area-inset-bottom); }
    </style>
    @stack('head')
</head>
<body>
    <div class="app-frame">
        @if(session('tech_impersonator_user_id') && \Illuminate\Support\Facades\Auth::guard('tech')->check())
            @php $impersonatedTech = \Illuminate\Support\Facades\Auth::guard('tech')->user(); @endphp
            <div class="bg-amber-50 border-b border-amber-200 px-4 py-2.5 flex items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2 text-amber-800 min-w-0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.024 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.5M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/>
                    </svg>
                    <span class="truncate">به‌جای <b>{{ trim($impersonatedTech->firstname_tech ?: $impersonatedTech->first_name) ?: $impersonatedTech->mobile }}</b> وارد شده‌اید</span>
                </div>
                <form action="{{ route('tech.impersonate.leave') }}" method="POST" class="flex-shrink-0">
                    @csrf
                    <button type="submit" class="px-2.5 py-1 rounded-md bg-amber-600 hover:bg-amber-700 text-white font-medium">
                        خروج از حالت ادمین
                    </button>
                </form>
            </div>
        @endif

        @yield('body')
    </div>

    <script>
    // Service Worker registration — silent failure in dev/local
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' })
                .catch(err => console.warn('SW registration failed:', err));
        });
    }
    </script>

    {{-- تبدیل خودکار ارقام لاتین به فارسی در همهٔ متن‌های نمایشی پنل تکنسین.
         input/textarea/script/style/code و عناصری با class .keep-latin
         دست‌نخورده می‌مانند تا مقدار فرم‌ها و کد و موارد فنی بدون تغییر
         به سرور بروند. روی DOM اولیه اجرا می‌شود و یک MutationObserver
         روی دیگرگاه‌های Livewire/Alpine هم نگه‌بان است. --}}
    <script>
    (function () {
        var LATIN = '0123456789';
        var PERSIAN = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        var SKIP_TAGS = { INPUT:1, TEXTAREA:1, SELECT:1, OPTION:1, SCRIPT:1, STYLE:1, CODE:1, KBD:1, SAMP:1, PRE:1 };

        function shouldSkip(node) {
            for (var p = node.parentNode; p && p.nodeType === 1; p = p.parentNode) {
                if (SKIP_TAGS[p.tagName]) return true;
                if (p.classList && p.classList.contains('keep-latin')) return true;
                if (p.hasAttribute && p.hasAttribute('contenteditable')) return true;
            }
            return false;
        }

        function convertText(s) {
            var out = '';
            for (var i = 0; i < s.length; i++) {
                var ch = s.charAt(i);
                var idx = LATIN.indexOf(ch);
                out += (idx >= 0) ? PERSIAN[idx] : ch;
            }
            return out;
        }

        function walk(node) {
            if (! node) return;
            if (node.nodeType === 3) {
                if (! /[0-9]/.test(node.nodeValue)) return;
                if (shouldSkip(node)) return;
                node.nodeValue = convertText(node.nodeValue);
                return;
            }
            if (node.nodeType !== 1) return;
            var tag = node.tagName;
            if (SKIP_TAGS[tag]) return;
            if (node.classList && node.classList.contains('keep-latin')) return;
            if (node.hasAttribute && node.hasAttribute('contenteditable')) return;
            // پیمایش فرزندان
            for (var c = node.firstChild; c; c = c.nextSibling) walk(c);
        }

        function run() { walk(document.body); }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
        // Livewire/Alpine ممکن است بخش‌هایی از DOM را عوض کنند
        document.addEventListener('livewire:navigated', run);
        document.addEventListener('livewire:initialized', run);

        // پوشش تغییرات بعدی DOM (toast، Alpine x-show و …)
        try {
            new MutationObserver(function (records) {
                records.forEach(function (r) {
                    r.addedNodes.forEach(function (n) {
                        if (n.nodeType === 3) {
                            if (/[0-9]/.test(n.nodeValue) && ! shouldSkip(n)) {
                                n.nodeValue = convertText(n.nodeValue);
                            }
                        } else if (n.nodeType === 1) {
                            walk(n);
                        }
                    });
                    if (r.type === 'characterData' && r.target && r.target.nodeType === 3) {
                        if (/[0-9]/.test(r.target.nodeValue) && ! shouldSkip(r.target)) {
                            r.target.nodeValue = convertText(r.target.nodeValue);
                        }
                    }
                });
            }).observe(document.body, { childList: true, subtree: true, characterData: true });
        } catch (e) {}
    })();
    </script>

    @if(\Illuminate\Support\Facades\Auth::guard('tech')->check())
    {{-- ─── پاپ‌آپ اعلانات تأییدنشده ───────────────────────────────
         اعلان‌هایی که اپراتور از پنل ادمین منتشر کرده و تکنسین هنوز
         «متوجه شدم» نزده، با polling هر ۶۰ ثانیه گرفته و یکی‌یکی
         نمایش داده می‌شوند. تأیید با POST ثبت می‌شود. --}}
    <div id="techAnnModal" style="display:none;" class="fixed inset-0 z-[60] items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" style="backdrop-filter: blur(2px);"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-auto p-5">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84A6 6 0 0118 10v-.7a6 6 0 10-12 0v.7a6 6 0 01-1.5 3.96L3 16h6m1.34-.16L9 16m1.34-.16a6 6 0 003.32 0M9 16v1a3 3 0 006 0v-1m-6 0h6"/>
                    </svg>
                </div>
                <div>
                    <div class="font-bold text-gray-900 text-sm">اعلان جدید</div>
                    <div id="techAnnDate" class="text-[10px] text-gray-400" dir="ltr"></div>
                </div>
            </div>
            <div id="techAnnTitle" class="font-bold text-sm text-gray-900 mb-1.5 leading-6"></div>
            <p id="techAnnBody" class="text-xs text-gray-600 leading-6 whitespace-pre-line max-h-56 overflow-y-auto"></p>
            <button id="techAnnAckBtn" type="button"
                    class="mt-4 w-full py-3 rounded-xl bg-brand-700 hover:bg-brand-800 text-white text-sm font-bold active:scale-95 transition">
                متوجه شدم ✓
            </button>
            <a href="{{ route('tech.announcements') }}" class="block text-center text-[11px] text-gray-400 mt-2.5">
                مشاهدهٔ همهٔ اعلانات
            </a>
        </div>
    </div>
    <script>
    {{-- مقاوم به ناوبریِ SPA لایوویر: عناصر هر بار از DOM خوانده می‌شوند (نه cache)،
         ack با event-delegation، و راه‌اندازی فقط یک‌بار روی realmِ پایدار. --}}
    (function () {
        if (window.__techAnnInit) return; // realm در wire:navigate پایدار می‌ماند
        window.__techAnnInit = true;

        var queue = [];
        var current = null;
        var known = {};
        var meta = document.querySelector('meta[name="csrf-token"]');
        var CSRF = meta ? meta.getAttribute('content') : '';

        function el(id) { return document.getElementById(id); }

        function showNext() {
            var modal = el('techAnnModal');
            if (! modal) return;
            if (! queue.length) { modal.style.display = 'none'; current = null; return; }
            current = queue.shift();
            var t = el('techAnnTitle'), b = el('techAnnBody'), d = el('techAnnDate');
            if (t) t.textContent = current.title;
            if (b) b.textContent = current.body;
            if (d) d.textContent = current.date;
            modal.style.display = 'flex';
        }

        // delegation تا با مورفِ DOM در ناوبریِ SPA نشکند
        document.addEventListener('click', async function (e) {
            var btn = e.target.closest ? e.target.closest('#techAnnAckBtn') : null;
            if (! btn || ! current) return;
            btn.disabled = true;
            var id = current.id;
            try {
                await fetch('/tech/announcements/' + id + '/ack', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
            } catch (err) {}
            btn.disabled = false;
            showNext();
        });

        async function poll() {
            try {
                var res = await fetch('{{ route('tech.announcements.unacked') }}', { headers: { 'Accept': 'application/json' } });
                if (! res.ok) return;
                var json = await res.json();
                (json.items || []).forEach(function (item) {
                    if (known[item.id]) return;
                    known[item.id] = true;
                    queue.push(item);
                });
                // بج اعلانات روی داشبورد (اگر در صفحهٔ جاری وجود دارد)
                var badge = el('techAnnBadge');
                if (badge) {
                    var c = parseInt(json.count || 0, 10);
                    badge.textContent = c > 99 ? '99+' : c;
                    badge.style.display = c > 0 ? 'flex' : 'none';
                }
                if (! current && queue.length) showNext();
            } catch (e) {}
        }

        poll();
        setInterval(poll, 30000);
        // پس از هر ناوبریِ SPA: اگر مودال در صف مانده دوباره نشان بده + یک poll تازه
        document.addEventListener('livewire:navigated', function () {
            if (! current && queue.length) showNext();
            poll();
        });
    })();
    </script>
    @endif

    @stack('scripts')
</body>
</html>
