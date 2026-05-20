@extends('layouts.admin')

@section('page-title', 'محتوای صفحه — ' . $title)

@section('main')
<div class="p-6">
    <div class="mb-4">
        <a href="{{ route('site.admin.page-content.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت به فهرست صفحات</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $title }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 font-mono">slug: {{ $slug }}</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
        <ul class="list-disc pr-4">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('site.admin.page-content.update', $slug) }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach($schemaSections as $sectionKey => $section)
            @php
                $sectionValue   = $values[$sectionKey] ?? ['payload' => [], 'is_published' => true];
                $payload        = $sectionValue['payload'] ?? [];
                $isPublished    = $sectionValue['is_published'] ?? true;
                $sectionLabel   = $section['label'] ?? $sectionKey;
                $sectionDesc    = $section['description'] ?? null;
                $publishedName  = "sections[{$sectionKey}][is_published]";
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                {{-- هدر سکشن --}}
                <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-base font-bold text-gray-800 dark:text-white">{{ $sectionLabel }}</h2>
                        <p class="text-xs text-gray-500 mt-1 font-mono">{{ $sectionKey }}</p>
                        @if($sectionDesc)
                            <p class="text-xs text-gray-500 mt-1">{{ $sectionDesc }}</p>
                        @endif
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="{{ $publishedName }}" value="0">
                        <input type="checkbox" name="{{ $publishedName }}" value="1" @checked(old($publishedName, $isPublished))>
                        <span class="text-xs">منتشر شود</span>
                    </label>
                </div>

                {{-- فیلدها — رندر بازگشتی (پشتیبانی group, repeater, reference, scalar) --}}
                <div class="p-4 space-y-4">
                    @include('site::admin.page-content._fields', [
                        'fields'     => $section['fields'] ?? [],
                        'values'     => is_array($payload) ? $payload : [],
                        'namePrefix' => "sections[{$sectionKey}][payload]",
                        'references' => $references,
                    ])
                </div>
            </div>
        @endforeach

        <div class="sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-4 border-t border-gray-200 dark:border-gray-700 -mx-6 flex items-center justify-end gap-2 shadow-lg">
            <a href="{{ route('site.admin.page-content.index') }}" class="px-4 py-2 bg-gray-100 rounded text-sm">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-semibold">ذخیره همه سکشن‌ها</button>
        </div>
    </form>
</div>
@endsection
