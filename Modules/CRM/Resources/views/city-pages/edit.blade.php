@extends('layouts.admin')

@section('page-title', 'ویرایش صفحهٔ سئو')

@section('main')
<div class="p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('crm.cities.pages.index', $cityPage->city_id) }}" class="hover:text-purple-600">صفحات سئوی {{ $cityPage->city?->name }}</a>
        <span>/</span>
        <span>{{ $cityPage->typeLabel() }}</span>
    </div>

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ویرایش صفحه</h1>
        <span class="inline-flex px-2.5 py-1 rounded-full text-xs {{ $cityPage->statusBadge() }}">{{ $cityPage->statusLabel() }}</span>
    </div>

    <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3 text-xs text-gray-500 dark:text-gray-400 dir-ltr text-left">
        {{ $cityPage->path }}
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <ul class="list-disc pr-5 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form action="{{ route('crm.city-pages.update', $cityPage) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان (Title / تگ عنوانِ سئو)</label>
            <input type="text" name="title" value="{{ old('title', $cityPage->title) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تیتر صفحه (H1)</label>
            <input type="text" name="h1" value="{{ old('h1', $cityPage->h1) }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات متا (Meta Description)</label>
            <textarea name="meta_description" rows="3"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('meta_description', $cityPage->meta_description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">محتوای صفحه (اختیاری)</label>
            <textarea name="content" rows="10"
                      class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">{{ old('content', $cityPage->content) }}</textarea>
            <p class="text-xs text-gray-400 mt-1">این متن در بدنهٔ صفحه روی سایت نمایش داده می‌شود.</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
            <a href="{{ route('crm.city-pages.preview', $cityPage) }}" target="_blank" class="px-5 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پیش‌نمایش</a>
            <a href="{{ route('crm.cities.pages.index', $cityPage->city_id) }}" class="px-5 py-2 text-gray-600 hover:text-gray-900">انصراف</a>
        </div>
    </form>

    {{-- پنلِ سئوی حرفه‌ای — همان کامپوننتِ صفحاتِ دستگاه/برند (canonical/robots/OG/schema). --}}
    @can('manage-seo')
        <livewire:seo.meta-panel type="city_page" :model-id="$cityPage->id" :key="'seo-city-'.$cityPage->id" />
    @endcan
</div>
@endsection
