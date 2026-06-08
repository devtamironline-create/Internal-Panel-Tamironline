@extends('layouts.admin')

@section('page-title', 'مدیریت سایت')

@section('main')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">مدیریت سایت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">از این بخش محتوای سایت، صفحات، بلاگ و تنظیمات عمومی را مدیریت کنید.</p>
    </div>

    @php
        $cards = [
            ['route' => 'site.admin.page-content.index',     'permission' => 'manage-site-pages',           'title' => 'محتوای صفحات',   'desc' => 'محتوای سکشن‌های home، about و contact برای فرانت.', 'count' => null,                  'color' => 'blue',    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['route' => 'site.admin.contact-messages.index', 'permission' => 'view-site-contact-messages', 'title' => 'پیام‌های تماس',  'desc' => 'پیام‌های دریافتی از فرم تماس.',          'count' => $stats['contact_new'], 'badge' => 'جدید', 'color' => 'amber',   'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['route' => 'site.admin.banners.index',          'permission' => 'manage-site-banners',         'title' => 'بنر و اسلایدر',  'desc' => 'بنرهای صفحه اصلی و موقعیت‌های دیگر.',     'count' => $stats['banners'],       'color' => 'rose',    'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['route' => 'site.admin.reviews.index',          'permission' => 'manage-site-reviews',         'title' => 'نظرات و توصیه‌نامه‌ها',  'desc' => 'مخزن یکپارچه‌ی نظرات صوتی و متنی.',     'count' => $stats['testimonials'],  'color' => 'emerald', 'icon' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z'],
            ['route' => 'site.admin.faqs.index',             'permission' => 'manage-site-faqs',            'title' => 'سوالات متداول',  'desc' => 'مخزن سوالات؛ به‌ هر صفحه قابل اتصال.',     'count' => $stats['faqs'],          'color' => 'indigo',  'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'site.admin.settings.edit',          'permission' => 'manage-site-settings',        'title' => 'تنظیمات عمومی', 'desc' => 'اطلاعات تماس، سئو، شبکه‌های اجتماعی.',  'count' => null,                    'color' => 'gray',    'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ];

        $colorMap = [
            'blue'    => 'bg-blue-50 text-blue-600',
            'amber'   => 'bg-amber-50 text-amber-600',
            'rose'    => 'bg-rose-50 text-rose-600',
            'emerald' => 'bg-emerald-50 text-emerald-600',
            'indigo'  => 'bg-indigo-50 text-indigo-600',
            'gray'    => 'bg-gray-100 text-gray-600',
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($cards as $card)
            @can($card['permission'])
            <a href="{{ route($card['route']) }}"
               class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700 hover:shadow-md transition block">
                <div class="flex items-start justify-between mb-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg {{ $colorMap[$card['color']] }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $card['icon'] }}"/>
                        </svg>
                    </span>
                    @if(!is_null($card['count']))
                    <div class="text-end">
                        <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $card['count'] }}</div>
                        @if(!empty($card['badge']))
                        <div class="text-[10px] text-amber-600 font-semibold">{{ $card['badge'] }}</div>
                        @endif
                    </div>
                    @endif
                </div>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">{{ $card['title'] }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $card['desc'] }}</p>
            </a>
            @endcan
        @endforeach

        {{-- لینک‌های منابع مشترک CRM --}}
        @if(Route::has('crm.brands.index'))
        @can('view-crm-taxonomies')
        <a href="{{ route('crm.brands.index') }}"
           class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700 hover:shadow-md transition block">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-purple-50 text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">برندها</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت در ماژول CRM (داده مشترک).</p>
        </a>
        @endcan
        @endif

        @if(Route::has('crm.devices.index'))
        @can('view-crm-taxonomies')
        <a href="{{ route('crm.devices.index') }}"
           class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 border border-gray-100 dark:border-gray-700 hover:shadow-md transition block">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-fuchsia-50 text-fuchsia-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                </span>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white">دستگاه‌ها</h3>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">مدیریت در ماژول CRM (داده مشترک).</p>
        </a>
        @endcan
        @endif
    </div>
</div>
@endsection
