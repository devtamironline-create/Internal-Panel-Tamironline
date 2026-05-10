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

    @stack('scripts')
</body>
</html>
