{{-- متغیرهای brand* و appName/supportPhone از طریق View::composer
     در CrmServiceProvider به این view و همهٔ child viewهای tech-panel.*
     تزریق می‌شوند. در parent @php تعریف نمی‌شوند چون scope آن به
     @section های child منتقل نمی‌شود. --}}
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

        /* Phone-frame on desktop, full-bleed on mobile */
        .phone-frame {
            min-height: 100vh;
            min-height: 100dvh;
            background: #ffffff;
            position: relative;
            overflow-x: hidden;
        }
        @media (min-width: 640px) {
            body {
                background: #e5e7eb;
                background-image: radial-gradient(at top, #f3f4f6, #d1d5db);
                background-attachment: fixed;
                padding: 24px 0;
            }
            .phone-frame {
                max-width: 420px;
                margin: 0 auto;
                min-height: calc(100vh - 48px);
                min-height: calc(100dvh - 48px);
                border-radius: 36px;
                box-shadow:
                    0 0 0 8px #1f2937,
                    0 25px 50px -12px rgba(0, 0, 0, 0.4);
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
    <div class="phone-frame">
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
    @stack('scripts')
</body>
</html>
