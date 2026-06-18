@extends('layouts.admin')

@section('page-title', 'داشبورد سئو')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">داشبورد سئو</h1>
            <p class="text-sm text-gray-500">نمای کلی سلامت سئو سایت. روی هر کارت بزنید تا به فهرست مربوطه بروید.</p>
        </div>
        @if($lastAuditAt)
            <span class="text-xs text-gray-500">آخرین آدیت: {{ $lastAuditAt->format('Y/m/d H:i') }}</span>
        @endif
    </div>

    @if(! $latestRun)
        {{-- حالت بدون کرال --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500 mb-6">
            <p class="mb-3">هنوز آدیتی اجرا نشده است. برای دیدن شمارش‌های مبتنی بر کرال، یک آدیت اجرا کنید.</p>
            <a href="{{ route('seo.admin.audit.index') }}" class="inline-block px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">اجرای آدیت</a>
        </div>
    @endif

    {{-- ── نمای کلی ── --}}
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">نمای کلی</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
        <a href="{{ route('seo.admin.tools.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($indexable) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">صفحات قابل‌ایندکس</div>
            <div class="text-xs text-gray-400 mt-1">مجموع موجودیت‌ها منهای noindex</div>
        </a>
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($noindexCount) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">صفحات Noindex</div>
            <div class="text-xs text-gray-400 mt-1">صفحات خارج‌شده از ایندکس</div>
        </a>
        @if($latestRun)
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $latestRun->avg_score >= 80 ? 'text-emerald-500' : ($latestRun->avg_score >= 50 ? 'text-amber-500' : 'text-rose-500') }}">{{ $latestRun->avg_score }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">میانگین امتیاز سلامت</div>
            <div class="text-xs text-gray-400 mt-1">
                سالم {{ $latestRun->ok_count }} / توجه {{ $latestRun->notice_count }} / هشدار {{ $latestRun->warning_count }} / بحرانی {{ $latestRun->critical_count }}
            </div>
        </a>
        @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 opacity-60">
            <div class="text-2xl font-bold text-gray-400">—</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">میانگین امتیاز سلامت</div>
            <div class="text-xs text-gray-400 mt-1">پس از اجرای آدیت</div>
        </div>
        @endif
    </div>

    {{-- ── مشکلات متا ── --}}
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">مشکلات متا</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $missingTitle > 0 ? 'text-rose-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($missingTitle) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">بدون عنوان سئو</div>
            <div class="text-xs text-gray-400 mt-1">عنوان خالی در آخرین آدیت</div>
        </a>
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $missingDescription > 0 ? 'text-rose-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($missingDescription) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">بدون توضیحات متا</div>
            <div class="text-xs text-gray-400 mt-1">meta description خالی</div>
        </a>
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $missingCanonical > 0 ? 'text-amber-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($missingCanonical) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">بدون/خالی canonical</div>
            <div class="text-xs text-gray-400 mt-1">canonical تنظیم‌نشده</div>
        </a>
        <a href="{{ route('seo.admin.audit.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $missingJsonld > 0 ? 'text-amber-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($missingJsonld) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">بدون داده ساختاریافته</div>
            <div class="text-xs text-gray-400 mt-1">JSON-LD ندارند (قابل‌ایندکس)</div>
        </a>
    </div>

    {{-- ── وضعیت فنی ── --}}
    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">وضعیت فنی</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <a href="{{ route('seo.admin.not-found.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $notFoundCount > 0 ? 'text-rose-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($notFoundCount) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">خطاهای ۴۰۴</div>
            <div class="text-xs text-gray-400 mt-1">مجموع بازدید: {{ number_format($notFoundHits) }}</div>
        </a>
        <a href="{{ route('seo.admin.redirects.index') }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold text-emerald-500">{{ number_format($activeRedirects) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">ریدایرکت‌های فعال</div>
            <div class="text-xs text-gray-400 mt-1">قوانین انتقال فعال</div>
        </a>
        @if($latestRun)
        <a href="{{ route('seo.admin.audit.show', $latestRun) }}"
           class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow block">
            <div class="text-2xl font-bold {{ $orphanPages > 0 ? 'text-amber-500' : 'text-gray-800 dark:text-white' }}">{{ number_format($orphanPages) }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">صفحات یتیم</div>
            <div class="text-xs text-gray-400 mt-1">بدون لینک داخلی</div>
        </a>
        @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 opacity-60">
            <div class="text-2xl font-bold text-gray-400">—</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">صفحات یتیم</div>
            <div class="text-xs text-gray-400 mt-1">پس از اجرای آدیت</div>
        </div>
        @endif
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-lg font-bold text-gray-800 dark:text-white">{{ $sitemapLastMod->format('Y/m/d') }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">آخرین به‌روزرسانی سایت‌مپ</div>
            <div class="text-xs text-gray-400 mt-1">{{ $sitemapLastMod->format('H:i') }}</div>
        </div>
    </div>
</div>
@endsection
