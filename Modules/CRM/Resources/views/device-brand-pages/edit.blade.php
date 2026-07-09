@extends('layouts.admin')

@section('page-title', 'ویرایش صفحه‌ی ترکیبی')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <a href="{{ route('crm.device-brand-pages.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
        <h1 class="text-xl font-bold mt-2">
            ویرایش: <span class="text-gray-700">{{ $page->device->name ?? '?' }} × {{ $page->brand->name ?? '?' }}</span>
        </h1>
        <p class="text-gray-500 mt-1 text-sm">
            URL: <code class="font-mono ltr text-xs" dir="ltr">/devices/{{ $page->device->slug ?? '?' }}/{{ $page->brand->slug ?? '?' }}</code>
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.device-brand-pages.update', $page->id) }}" method="POST">
            @method('PUT')
            @include('crm::device-brand-pages._form')
        </form>
    </div>

    {{-- بخشِ سئوِ حرفه‌ای — همان پنلی که صفحاتِ دستگاه و برند دارند
         (عنوان/توضیح، کلمهٔ کلیدی، canonical، robots، OG/Twitter، امتیازِ سئو). --}}
    <livewire:seo.meta-panel type="device_brand_page" :model-id="$page->id" :key="'seo-dbp-'.$page->id" />
</div>
@endsection
