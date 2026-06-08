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
                <a href="{{ route('crm.devices.index') }}" class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">انصراف</a>
                <button type="submit" form="device-form"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ذخیره تغییرات
                </button>
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
@endsection
