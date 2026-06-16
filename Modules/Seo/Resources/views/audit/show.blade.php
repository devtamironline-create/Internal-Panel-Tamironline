@extends('layouts.admin')

@section('page-title', 'گزارش کرال #'.$run->id)

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">گزارش کرال #{{ $run->id }}</h1>
        <a href="{{ route('seo.admin.audit.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت</a>
    </div>

    @include('seo::audit._summary', ['run' => $run])

    <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
        <div class="flex gap-1 text-xs">
            @foreach(['' => 'همه', 'critical' => 'بحرانی', 'warning' => 'هشدار', 'notice' => 'توجه', 'good' => 'سالم'] as $val => $label)
                <a href="{{ route('seo.admin.audit.show', array_merge([$run], array_filter(['severity' => $val]))) }}"
                   class="px-3 py-1 rounded-full {{ $severity === $val ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-2 text-xs">
            <a href="{{ route('seo.admin.audit.export', [$run, 'csv']) }}" class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">خروجی CSV</a>
            <a href="{{ route('seo.admin.audit.export', [$run, 'json']) }}" class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700">خروجی JSON</a>
        </div>
    </div>

    @include('seo::audit._table', ['audits' => $audits])
</div>
@endsection
