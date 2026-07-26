@extends('layouts.admin')

@section('page-title', 'بازوی سئو — گزارش‌ها')

@section('main')
<div class="p-6 space-y-4" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">📊 گزارش‌های سئو</h1>
        <p class="text-sm text-gray-500 mt-1">گزارش‌ها از رکوردهای دیتابیس ساخته می‌شوند؛ خروجی HTML آمادهٔ چاپ/ذخیره است.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($types as $key => $label)
            <a href="{{ route('seo.arm.reports.show', $key) }}"
               class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-brand-400 hover:shadow-sm transition">
                <div class="text-2xl mb-2">
                    {{ ['executive' => '🧭', 'weekly' => '🗓️', 'content' => '✍️', 'keywords' => '🔑', 'pages' => '📄', 'issues' => '🛠️', 'links' => '🔗', 'roadmap' => '🗺️'][$key] ?? '📊' }}
                </div>
                <div class="font-bold text-sm text-gray-800 dark:text-gray-100">{{ $label }}</div>
            </a>
        @endforeach
    </div>
</div>
@endsection
