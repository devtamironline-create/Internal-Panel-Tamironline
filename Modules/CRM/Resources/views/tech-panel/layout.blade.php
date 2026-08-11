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
        {{-- آپدیت اجباری اپ اندروید — روی هر دو میزبانِ پنل تکنسین (panel و
             karbalad). تمام‌صفحه و بدون راهِ رد شدن: تکنسینِ اندرویدی تا
             نسخهٔ جدید را نصب نکند نمی‌تواند با پنل کار کند.
             استایل‌ها عمداً inline هستند تا به build تیل‌ویند وابسته نباشند. --}}
        @if(str_contains(request()->userAgent() ?? '', 'Android'))
            <div dir="rtl" style="position:fixed; inset:0; z-index:99999; background:#fff;
                        display:flex; flex-direction:column; align-items:center; justify-content:center;
                        text-align:center; padding:2rem; gap:1rem;">
                <svg style="width:56px; height:56px; color:#059669;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                <h1 style="font-size:1.25rem; font-weight:800; color:#111827; margin:0;">آپدیت اجباری اپ تکنسین</h1>
                <p style="font-size:.9rem; color:#4b5563; line-height:1.9; max-width:22rem; margin:0;">
                    برای استفاده از اپ تکنسین، نسخهٔ اندروید نرم‌افزار را آپدیت کنید.
                    ادامهٔ کار فقط بعد از نصب نسخهٔ جدید ممکن است.
                </p>
                {{-- زنجیرهٔ فرار از پوسته: ۱) window.open — در مرورگر/PWA همان‌جا
                     دانلود می‌شود؛ ۲) intent:// عمومی — WebView را وادار می‌کند
                     آدرس را به مرورگر سیستم بدهد؛ ۳) intent با package کروم.
                     اگر هیچ‌کدام نگرفت، fallback کپی لینک پایین سر جایش است. --}}
                <script>
                function techApkOpen() {
                    var url = 'https://panel.tamironline.com/karbalad-ver4.apk';
                    var host = 'panel.tamironline.com/karbalad-ver4.apk';
                    try {
                        var w = window.open(url, '_blank');
                        if (w) { return false; }
                    } catch (e) {}
                    try {
                        window.location.href = 'intent://' + host
                            + '#Intent;scheme=https;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;end';
                    } catch (e) {}
                    // اگر بعد از یک‌ونیم ثانیه هنوز همین‌جاییم، کروم را صریح صدا بزن.
                    setTimeout(function () {
                        if (document.visibilityState === 'visible') {
                            try {
                                window.location.href = 'intent://' + host
                                    + '#Intent;scheme=https;package=com.android.chrome;action=android.intent.action.VIEW;end';
                            } catch (e) {}
                        }
                    }, 1500);
                    return false;
                }
                </script>
                <a href="https://panel.tamironline.com/karbalad-ver4.apk" target="_blank" rel="noopener"
                   onclick="return techApkOpen();"
                   style="display:inline-block; background:#059669; color:#fff; font-weight:700;
                          padding:.75rem 2.5rem; border-radius:.75rem; text-decoration:none; font-size:1rem;">
                    دانلود نسخهٔ جدید
                </a>
                {{-- راهِ مطمئن برای WebViewهای کاملاً بسته: پیامک همیشه در
                     مرورگر سیستم باز می‌شود. فقط برای تکنسینِ لاگین‌شده. --}}
                @if(\Illuminate\Support\Facades\Auth::guard('tech')->check())
                    @if(session('app_update_sms_sent'))
                        <p style="font-size:.8rem; color:#059669; font-weight:700; margin:0;">✓ {{ session('app_update_sms_sent') }}</p>
                    @elseif(session('app_update_sms_error'))
                        <p style="font-size:.8rem; color:#dc2626; margin:0;">{{ session('app_update_sms_error') }}</p>
                    @else
                        <form method="POST" action="{{ route('tech.app-update.sms') }}" style="margin:0;">
                            @csrf
                            <button type="submit"
                                    style="background:#fff; color:#059669; border:2px solid #059669; font-weight:700;
                                           padding:.65rem 2rem; border-radius:.75rem; font-size:.9rem; cursor:pointer;">
                                ارسال لینک دانلود با پیامک 📱
                            </button>
                        </form>
                    @endif
                @endif

                <p style="font-size:.75rem; color:#6b7280; margin:0;">
                    یا در مرورگر (Chrome) این آدرس را باز کنید:
                    <b dir="ltr" class="keep-latin" style="color:#111827;">panel.tamironline.com/app</b>
                </p>
                <p style="font-size:.75rem; color:#6b7280; margin:0;">
                    اگر دانلود شروع نشد، لینک را کپی و در مرورگر (Chrome) باز کنید:
                </p>
                <div style="display:flex; align-items:center; gap:.5rem; max-width:100%;">
                    <code id="apk-link" dir="ltr" class="keep-latin"
                          style="font-size:.7rem; color:#374151; background:#f3f4f6; border:1px solid #e5e7eb;
                                 border-radius:.5rem; padding:.5rem .75rem; user-select:all; overflow-x:auto; white-space:nowrap;">https://panel.tamironline.com/karbalad-ver4.apk</code>
                    <button type="button" onclick="
                        var t='https://panel.tamironline.com/karbalad-ver4.apk';
                        (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).catch(function(){
                            var i=document.createElement('textarea'); i.value=t; document.body.appendChild(i);
                            i.select(); document.execCommand('copy'); document.body.removeChild(i);
                        });
                        this.textContent='کپی شد ✓';"
                        style="flex-shrink:0; background:#f3f4f6; border:1px solid #d1d5db; border-radius:.5rem;
                               padding:.5rem .75rem; font-size:.75rem; color:#374151; cursor:pointer;">
                        کپی لینک
                    </button>
                </div>
            </div>
        @endif

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

        // ─── حافظهٔ محلیِ «دیده‌شده» ─────────────────────────────────
        // علتِ لوپ: ackِ سمتِ سرور یک fetchِ ساده بود که اگر اپ در موبایل
        // بک‌گراند/ری‌لود می‌شد قبل از تکمیل، مرورگر آن را کنسل می‌کرد و اعلان
        // «دیده‌نشده» می‌ماند و دوباره نمایش داده می‌شد. حالا:
        //   ۱) idِ دیده‌شده در localStorage ذخیره می‌شود تا روی همین دستگاه
        //      دیگر هرگز دوباره نمایش داده نشود (حتی اگر ackِ سرور گم شود).
        //   ۲) خودِ ack با keepalive فرستاده می‌شود تا با unloadِ صفحه کنسل نشود.
        var SEEN_KEY = 'techAnnSeen';
        function seenGet() {
            try { return JSON.parse(localStorage.getItem(SEEN_KEY) || '[]') || []; }
            catch (e) { return []; }
        }
        function seenHas(id) { return seenGet().indexOf(id) !== -1; }
        function seenAdd(id) {
            try {
                var s = seenGet();
                if (s.indexOf(id) === -1) {
                    s.push(id);
                    if (s.length > 300) s = s.slice(-300); // کران تا رشد نکند
                    localStorage.setItem(SEEN_KEY, JSON.stringify(s));
                }
            } catch (e) {}
        }

        function ack(id) {
            // insertOrIgnore سمتِ سرور → تکرارِ درخواست بی‌ضرر است. keepalive تضمین
            // می‌کند حتی اگر صفحه بلافاصله بسته/ری‌لود شود، درخواست کامل برسد.
            return fetch('/tech/announcements/' + id + '/ack', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                credentials: 'same-origin',
                keepalive: true,
            }).catch(function () {});
        }

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
            // «دیدن = تأیید»: همان لحظه‌ی نمایش، هم در localStorage علامت می‌خورد
            // (تا روی این دستگاه دیگر نمایش داده نشود) و هم ackِ سرور فرستاده می‌شود.
            seenAdd(current.id);
            ack(current.id);
        }

        // delegation تا با مورفِ DOM در ناوبریِ SPA نشکند
        document.addEventListener('click', async function (e) {
            var btn = e.target.closest ? e.target.closest('#techAnnAckBtn') : null;
            if (! btn || ! current) return;
            btn.disabled = true;
            // ackِ دوباره به‌عنوانِ پشتیبان (اگر ackِ لحظه‌ی نمایش به خطای شبکه خورد)
            await ack(current.id);
            btn.disabled = false;
            showNext();
        });

        async function poll() {
            try {
                var res = await fetch('{{ route('tech.announcements.unacked') }}', { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                if (! res.ok) return;
                var json = await res.json();
                (json.items || []).forEach(function (item) {
                    if (known[item.id]) return;
                    known[item.id] = true;
                    // اگر روی این دستگاه قبلاً دیده/تأیید شده، دوباره در صف نگذار
                    // (شکستنِ لوپ حتی وقتی ackِ سرور گم شده باشد).
                    if (seenHas(item.id)) return;
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

    {{-- ─── پاپ‌آپِ بدهی بعد از اتمامِ سفارش ───────────────────────────
         وقتی تکنسین سفارشی را «پایان» می‌دهد و سهمِ شرکت (بدهی) ایجاد می‌شود،
         بلافاصله یادآوری می‌شود که کیف‌پول را شارژ کند. یک‌بار (flash) نمایش
         داده می‌شود؛ با «شارژ» یا «بعداً» بسته می‌شود. --}}
    @if(session('debt_popup'))
        @php($__debt = session('debt_popup'))
        <div id="techDebtModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" style="backdrop-filter: blur(2px);"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-auto p-5 border-t-4 border-rose-500">
                <div class="flex items-center gap-2.5 mb-3">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div class="font-bold text-gray-900 text-sm">بدهی این سفارش</div>
                </div>

                <p class="text-xs text-gray-700 leading-7 mb-3">
                    بابتِ سفارش <span class="font-bold" dir="ltr">{{ $__debt['order_code'] ?? '' }}</span>
                    مبلغِ <span class="font-extrabold text-rose-600">{{ number_format((int) ($__debt['order_debt'] ?? 0)) }}</span> تومان
                    به شرکت بدهکار شدید.
                    @if((int) ($__debt['total_debt'] ?? 0) > 0)
                        <br>مجموعِ بدهیِ فعلیِ شما:
                        <span class="font-extrabold text-rose-600">{{ number_format((int) $__debt['total_debt']) }}</span> تومان.
                    @endif
                    <br>لطفاً هرچه سریع‌تر کیف‌پول را شارژ کنید.
                </p>

                <a href="{{ route('tech.wallet.recharge') }}"
                   class="block text-center w-full py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold active:scale-95 transition">
                    شارژ کیف پول
                </a>
                <button type="button" onclick="var m=document.getElementById('techDebtModal'); if(m) m.remove();"
                        class="mt-2 w-full py-2.5 rounded-xl text-gray-500 text-xs font-medium">
                    بعداً
                </button>
            </div>
        </div>
    @endif

    @stack('scripts')
</body>
</html>
