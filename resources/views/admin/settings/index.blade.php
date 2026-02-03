@extends('layouts.admin')

@section('page-title', 'تنظیمات سایت')

@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">تنظیمات سایت</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">لوگو، فاوآیکون و نام سایت را تنظیم کنید</p>
        </div>

        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            @if(session('success'))
                <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Site Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">نام سایت</label>
                <input type="text" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                @error('site_name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Site Subtitle -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">زیرنویس</label>
                <input type="text" name="site_subtitle" value="{{ old('site_subtitle', $settings['site_subtitle']) }}" class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-brand-500">
                @error('site_subtitle')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Logo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">لوگو</label>
                <div class="flex items-start gap-4">
                    @if($settings['logo'])
                        <div class="relative">
                            <img src="{{ asset('storage/' . $settings['logo']) }}" alt="Logo" class="w-20 h-20 object-contain rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                            <a href="{{ route('admin.settings.delete-logo') }}" onclick="return confirm('آیا مطمئن هستید؟')" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </div>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="logo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-900/30 dark:file:text-brand-300">
                        <p class="text-xs text-gray-400 mt-1">فرمت‌های مجاز: JPG, PNG, GIF, SVG, WebP (حداکثر 2MB)</p>
                    </div>
                </div>
                @error('logo')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Favicon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">فاوآیکون (آیکون تب مرورگر)</label>
                <div class="flex items-start gap-4">
                    @if($settings['favicon'])
                        <div class="relative">
                            <img src="{{ asset('storage/' . $settings['favicon']) }}" alt="Favicon" class="w-12 h-12 object-contain rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                            <a href="{{ route('admin.settings.delete-favicon') }}" onclick="return confirm('آیا مطمئن هستید؟')" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 text-xs">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        </div>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="favicon" accept=".ico,.png,.jpg,.svg" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-900/30 dark:file:text-brand-300">
                        <p class="text-xs text-gray-400 mt-1">فرمت‌های مجاز: ICO, PNG, JPG, SVG (حداکثر 1MB)</p>
                    </div>
                </div>
                @error('favicon')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Preview -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">پیش‌نمایش سایدبار:</p>
                <div class="bg-[#1a2d48] rounded-lg p-4 inline-flex items-center gap-2">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500">
                        @if($settings['logo'])
                            <img src="{{ asset('storage/' . $settings['logo']) }}" alt="Logo" class="w-6 h-6 object-contain">
                        @else
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                        @endif
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base font-bold text-white leading-tight">{{ $settings['site_name'] }}</span>
                        <span class="text-xs text-white/60">{{ $settings['site_subtitle'] }}</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="submit" class="px-6 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg font-medium transition">
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
