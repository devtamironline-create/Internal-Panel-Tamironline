@extends('layouts.admin')

@section('page-title', 'ویرایش دستگاه — ' . $device->name)

@php
    $sections = [
        'basic' => ['title' => 'اطلاعات پایه', 'icon' => '📋'],
        'hero-image' => ['title' => 'تصویر Hero', 'icon' => '🖼️'],
        'sections-enabled' => ['title' => 'سکشن‌های فعال', 'icon' => '🔘'],
        'cms-text' => ['title' => 'متن‌های CMS', 'icon' => '✍️'],
        'cta' => ['title' => 'دکمه‌های Hero', 'icon' => '🔗'],
        'steps-images' => ['title' => 'تصاویر مراحل', 'icon' => '🪜'],
        'pricing' => ['title' => 'قیمت و رنگ', 'icon' => '💰'],
        'brands' => ['title' => 'برندها', 'icon' => '🏢'],
        'faq-categories' => ['title' => 'دسته‌های FAQ', 'icon' => '🏷️'],
        'faqs' => ['title' => 'سوالات منفرد', 'icon' => '❓'],
        'reviews' => ['title' => 'دیدگاه‌ها', 'icon' => '💬'],
        'videos' => ['title' => 'ویدیوها', 'icon' => '🎬'],
        'seo' => ['title' => 'سئو', 'icon' => '🔍'],
    ];
@endphp

@section('main')
<div x-data="entityFormPage()" class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <a href="{{ route('crm.devices.index') }}" class="hover:text-blue-600">فهرست دستگاه‌ها</a>
                    <span>›</span>
                    <span class="font-mono">{{ $device->slug }}</span>
                </div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white truncate flex items-center gap-2">
                    @if($device->thumbnail)
                        <img src="{{ $device->thumbnail }}" alt="" class="w-7 h-7 object-contain rounded">
                    @endif
                    <span>ویرایش دستگاه: {{ $device->name }}</span>
                </h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="expandAll()" class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">باز همه</button>
                <button type="button" @click="collapseAll()" class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">بسته همه</button>
                @if(Route::has('crm.service-prices.index'))
                    <a href="{{ route('crm.service-prices.index', ['device' => $device->id]) }}" class="px-3 py-2 text-xs bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg">تعرفهٔ خدمات</a>
                @endif
                <a href="{{ route('crm.devices.index') }}" class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">انصراف</a>
                <button type="submit" form="device-form"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </div>

    {{-- ── پوشش این خدمت — از تگ‌های تکنسین‌های فعال (فقط نمایش) ── --}}
    <div class="p-4 lg:px-6 lg:pt-6 lg:pb-0" x-data="{ covOpen: false }">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3 p-4 cursor-pointer" @click="covOpen = ! covOpen">
                <span class="text-gray-400 text-xs" x-text="covOpen ? '▾' : '◂'"></span>
                <span class="text-base">📍</span>
                <div class="font-bold text-gray-900 dark:text-gray-100 text-sm">پوشش این خدمت</div>
                @if($serviceCoverage)
                    <span class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 text-[11px] font-bold">{{ $serviceCoverage['province_count'] }} استان</span>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">{{ $serviceCoverage['city_count'] }} شهر</span>
                @else
                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[11px] font-bold">بدون پوشش — فقط لید</span>
                @endif
                <div class="ms-auto flex items-center gap-3" @click.stop>
                    <a href="{{ route('crm.devices.coverage-titles', $device) }}" class="text-xs text-brand-600 hover:text-brand-700">✍ عناوین مناطق تحت پوشش</a>
                    <a href="{{ route('crm.technicians.service-coverage') }}" class="text-xs text-brand-600 hover:text-brand-700">همهٔ خدمات ←</a>
                </div>
            </div>
            <div x-show="covOpen" x-collapse x-cloak>
                <div class="px-4 pb-4 border-t border-gray-100 dark:border-gray-700 pt-3">
                    @if($serviceCoverage)
                        <div class="space-y-2.5">
                            @foreach($serviceCoverage['provinces'] as $p)
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 ml-1">{{ $p['name'] }}:</span>
                                    @foreach($p['cities'] as $c)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-gray-50 dark:bg-gray-700/60 border border-gray-200 dark:border-gray-600 text-xs text-gray-800 dark:text-gray-100">
                                            {{ $c['name'] }}
                                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">{{ $c['technician_count'] }}</span>
                                            @if($c['brands'] !== 'all')
                                                <span class="text-[10px] text-violet-600 dark:text-violet-400">{{ count($c['brands']) }} برند</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-gray-400 mt-2.5">
                            خودکار از تگ‌های شهر/دستگاه/برندِ تکنسین‌های فعال — سایت هم همین را از API می‌خواند.
                            برای تغییر، تگ‌های پروفایل تکنسین‌ها را ویرایش کنید.
                        </p>
                    @else
                        <p class="text-xs text-rose-600">
                            هیچ تکنسین فعالی با تگ این دستگاه (یا همه‌کاره) در هیچ شهری نیست — این خدمت در فرم سفارش و سایت قابل ارائه نیست و تماس‌هایش فقط لید می‌شوند.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                            <h3 class="text-xs font-bold uppercase tracking-wide">سکشن‌ها</h3>
                        </div>
                        <nav class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                            @foreach($sections as $key => $s)
                                <a href="#section-{{ $key }}"
                                   :class="active === '{{ $key }}' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-500' : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-700'"
                                   class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm border-r-2 transition-colors mb-1">
                                    <span class="text-base shrink-0">{{ $s['icon'] }}</span>
                                    <span class="flex-1 truncate">{{ $s['title'] }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>
            </aside>

            <form id="device-form" action="{{ route('crm.devices.update', $device) }}" method="POST" enctype="multipart/form-data">
                @method('PUT')
                @include('crm::devices._form')

                <div class="lg:hidden sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-4 -mx-4 border-t border-gray-200 dark:border-gray-700 shadow-lg flex items-center gap-2 mt-6">
                    <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</a>
                    <button type="submit" class="flex-1 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('crm::partials.entity-form-controller')

@can('manage-seo')
<div class="max-w-3xl mx-auto px-6 pb-8" dir="rtl">
    <livewire:seo.meta-panel type="device" :model-id="$device->id" :key="'seo-device-'.$device->id" />
</div>
@endcan
@endsection
