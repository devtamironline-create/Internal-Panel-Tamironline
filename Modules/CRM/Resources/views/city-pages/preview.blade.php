@extends('layouts.admin')

@section('page-title', 'پیش‌نمایش صفحه')

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('crm.cities.pages.index', $cityPage->city_id) }}" class="text-sm text-gray-500 hover:text-purple-600">← بازگشت به صفحات {{ $cityPage->city?->name }}</a>
        <span class="inline-flex px-2.5 py-1 rounded-full text-xs {{ $cityPage->statusBadge() }}">{{ $cityPage->statusLabel() }}</span>
    </div>

    @unless($cityPage->isPublished())
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">
        این صفحه هنوز منتشر نشده و برای بازدیدکنندهٔ عمومی <b>۴۰۴</b> است. این پیش‌نمایش فقط برای مدیرانِ واردشده به پنل قابل دیدن است.
    </div>
    @endunless

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
        <div class="text-xs text-gray-400 dir-ltr text-left">{{ config('seo.site_url') }}{{ $cityPage->path }}</div>

        {{-- نمایشِ نمونهٔ نتیجهٔ گوگل --}}
        <div class="border-b border-gray-100 dark:border-gray-700 pb-4">
            <div class="text-lg text-blue-700 dark:text-blue-400">{{ $cityPage->title }}</div>
            <div class="text-green-700 dark:text-green-500 text-xs dir-ltr text-left">{{ config('seo.site_url') }}{{ $cityPage->path }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $cityPage->meta_description }}</div>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $cityPage->h1 ?: $cityPage->title }}</h1>

        @if($cityPage->content)
            <div class="prose max-w-none dark:prose-invert text-gray-800 dark:text-gray-200">{!! $cityPage->content !!}</div>
        @else
            <p class="text-gray-400 text-sm">محتوایی برای بدنهٔ صفحه ثبت نشده. سایت از قالبِ پیش‌فرضِ خود برای این نوع صفحه استفاده می‌کند.</p>
        @endif
    </div>

    <div class="text-xs text-gray-400">
        نوع: {{ $cityPage->typeLabel() }}
        @if($cityPage->device) · دستگاه: {{ $cityPage->device->name }} @endif
        @if($cityPage->brand) · برند: {{ $cityPage->brand->name }} @endif
    </div>
</div>
@endsection
