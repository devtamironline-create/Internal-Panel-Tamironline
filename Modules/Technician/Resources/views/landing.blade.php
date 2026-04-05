<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title ?? 'همکاری با ما' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="/css/fonts.css" rel="stylesheet">
    <style>
        * { font-family: 'Vazirmatn', Tahoma, sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #1e3a5f 0%, #0f4c81 40%, #1a6b4a 100%); }
        .gradient-cta  { background: linear-gradient(135deg, #0f4c81 0%, #1a6b4a 100%); }
        .benefit-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .benefit-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.12); }
        .faq-item summary::-webkit-details-marker { display: none; }
        .step-line { background: linear-gradient(to bottom, #0f4c81, #1a6b4a); }
    </style>
</head>
<body class="bg-gray-50 antialiased">
<div class="min-h-screen" dir="rtl">

    {{-- ===== NAVBAR ===== --}}
    <nav class="bg-white/90 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                @if($brand_logo)
                    <img src="{{ asset('storage/' . $brand_logo) }}" alt="{{ $brand_name }}" class="h-14 max-w-[220px] w-auto object-contain">
                @else
                    <span class="text-xl font-black text-blue-900">{{ $brand_name }}</span>
                @endif
            </div>
            <a href="#register-form"
               class="px-5 py-2.5 bg-gradient-to-l from-blue-600 to-blue-700 text-white text-sm font-bold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                {{ $hero_cta_text }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </a>
        </div>
    </nav>

    {{-- ===== HERO ===== --}}
    <section class="{{ $hero_bg_image ? '' : 'gradient-hero' }} text-white py-20 md:py-28 relative"
             @if($hero_bg_image)
             style="background-image: url('{{ asset('storage/' . $hero_bg_image) }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
             @endif>
        @if($hero_bg_image)
        <div class="absolute inset-0" style="background-color: {{ $hero_overlay_color }}; opacity: {{ intval($hero_overlay_opacity) / 100 }};"></div>
        @endif
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 text-center">
            @if($hero_badge)
            <span class="inline-block mb-5 px-4 py-1.5 bg-white/15 backdrop-blur text-sm font-semibold rounded-full border border-white/30">
                ✨ {{ $hero_badge }}
            </span>
            @endif
            <h1 class="text-3xl md:text-5xl font-black leading-tight mb-4">
                {{ $hero_title }}
            </h1>
            <p class="text-lg md:text-2xl text-blue-200 font-semibold mb-6">
                {{ $hero_subtitle }}
            </p>
            <p class="text-base md:text-lg text-blue-100 max-w-2xl mx-auto leading-relaxed mb-10">
                {{ $hero_description }}
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
                <a href="{{ $hero_cta_link }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-blue-900 font-bold text-sm rounded-full hover:bg-blue-50 transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                    {{ $hero_cta_text }}
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                @if($hero_secondary_text)
                <a href="{{ $hero_secondary_link }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 bg-white/10 backdrop-blur border border-white/25 text-white font-medium text-sm rounded-full hover:bg-white/20 transition-all">
                    {{ $hero_secondary_text }}
                    <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                @endif
            </div>
        </div>
    </section>

    {{-- ===== BENEFITS ===== --}}

    @if(!empty($benefits))
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl md:text-3xl font-black text-gray-900 text-center mb-12">
                {{ $benefits_title }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($benefits as $benefit)
                <div class="benefit-card bg-gray-50 border border-gray-100 rounded-2xl p-6">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-4">
                        @switch($benefit['icon'] ?? '')
                            @case('money')
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @break
                            @case('clock')
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @break
                            @case('map')
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                @break
                            @case('support')
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                @break
                            @case('growth')
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                @break
                            @default
                                <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                        @endswitch
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $benefit['title'] }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $benefit['description'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ===== HOW IT WORKS ===== --}}
    @if(!empty($steps))
    <section id="how-it-works" class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl md:text-3xl font-black text-gray-900 text-center mb-12">
                {{ $steps_title }}
            </h2>
            <div class="relative">
                <div class="absolute right-7 top-8 bottom-8 w-0.5 step-line hidden sm:block"></div>
                <div class="space-y-8">
                    @foreach($steps as $step)
                    <div class="flex gap-5 items-start relative">
                        <div class="w-14 h-14 flex-shrink-0 rounded-full bg-blue-700 text-white flex items-center justify-center text-xl font-black shadow-md z-10">
                            {{ $step['number'] }}
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex-1">
                            <h3 class="font-bold text-gray-900 text-lg mb-1">{{ $step['title'] }}</h3>
                            <p class="text-gray-600 text-sm leading-relaxed">{{ $step['description'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- ===== REQUIREMENTS ===== --}}
    @if(!empty($requirements))
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl md:text-3xl font-black text-gray-900 text-center mb-10">
                {{ $requirements_title }}
            </h2>
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 md:p-8">
                <ul class="space-y-3">
                    @foreach($requirements as $item)
                    <li class="flex items-start gap-3 text-gray-800">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="leading-relaxed">{{ $item }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
    @endif

    {{-- ===== FAQ ===== --}}
    @if(!empty($faq))
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl md:text-3xl font-black text-gray-900 text-center mb-10">
                {{ $faq_title }}
            </h2>
            <div class="space-y-3">
                @foreach($faq as $item)
                <details class="faq-item bg-white rounded-xl border border-gray-100 shadow-sm group">
                    <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer list-none select-none">
                        <span class="font-semibold text-gray-900">{{ $item['question'] }}</span>
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="px-5 pb-5 text-gray-600 text-sm leading-relaxed border-t border-gray-50 pt-4">
                        {{ $item['answer'] }}
                    </div>
                </details>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ===== CTA ===== --}}
    <section id="register-form" class="gradient-cta py-20 md:py-28 text-white text-center relative overflow-hidden">
        {{-- دایره‌های تزئینی پس‌زمینه --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-white/5 rounded-full"></div>
            <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-white/5 rounded-full"></div>
        </div>
        <div class="max-w-2xl mx-auto px-4 sm:px-6 relative z-10">
            @if($cta_badge)
            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur border border-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-6">
                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                {{ $cta_badge }}
            </div>
            @endif
            <h2 class="text-3xl md:text-4xl font-black mb-4 leading-tight">{{ $cta_title }}</h2>
            <p class="text-blue-100 text-lg mb-10 leading-relaxed">{{ $cta_description }}</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ $cta_button_link }}"
                   class="inline-flex items-center justify-center gap-3 px-8 py-4 bg-white text-blue-900 font-black text-lg rounded-2xl hover:bg-blue-50 transition-all shadow-xl hover:shadow-2xl hover:-translate-y-0.5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ $cta_button_text }}
                </a>
                @if($cta_phone)
                <a href="tel:{{ $cta_phone }}"
                   class="inline-flex items-center justify-center gap-3 px-8 py-4 bg-white/10 backdrop-blur border border-white/30 text-white font-bold text-lg rounded-2xl hover:bg-white/20 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ $cta_phone_text }}
                </a>
                @endif
            </div>
            @if($cta_footnote)
            <p class="mt-8 text-blue-200/70 text-sm">{{ $cta_footnote }}</p>
            @endif
        </div>
    </section>

    {{-- ===== FOOTER ===== --}}
    <footer class="bg-gray-900 text-gray-400 text-sm text-center py-6">
        <p>© {{ now()->year }} {{ $brand_name }} — تمامی حقوق محفوظ است.</p>
    </footer>

</div>
</body>
</html>
