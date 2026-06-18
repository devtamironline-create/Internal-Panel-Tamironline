@extends('layouts.admin')

@section('page-title', 'گزارش Canonical')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">گزارش Canonical</h1>
            <p class="text-sm text-gray-500">
                @if($run)
                    بر پایهٔ آخرین کرال تمام‌شده
                    <a href="{{ route('seo.admin.audit.show', $run) }}" class="text-blue-600 hover:underline">#{{ $run->id }}</a>
                    ({{ optional($run->finished_at ?? $run->created_at)->format('Y/m/d H:i') }})
                @else
                    هنوز کرال تمام‌شده‌ای وجود ندارد.
                @endif
            </p>
        </div>
        <a href="{{ route('seo.admin.audit.index') }}" class="text-sm text-blue-600 hover:underline">مانیتورینگ سئو &larr;</a>
    </div>

    @if(! $run)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-10 text-center text-gray-500">
            ابتدا یک کرال اجرا کنید تا گزارش canonical ساخته شود.
        </div>
    @else
        {{-- شمارش مشکلات canonical --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            @php
                $cardColor = [
                    'canonical_empty' => 'text-amber-600',
                    'canonical_to_redirect' => 'text-amber-600',
                    'canonical_to_noindex' => 'text-amber-600',
                    'canonical_to_404' => 'text-rose-600',
                    'canonical_offsite' => 'text-blue-600',
                ];
            @endphp
            @foreach($labels as $code => $label)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 text-center">
                    <div class="text-2xl font-bold {{ $cardColor[$code] ?? 'text-gray-700' }}">{{ $counts[$code] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- گروه‌های canonical تکراری --}}
        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
            canonicalهای تکراری
            <span class="text-xs font-normal text-gray-400">(چند صفحه با یک canonical مشترک — نیازمند بررسی مدیر)</span>
        </h2>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-right">Canonical</th>
                        <th class="px-3 py-2 text-right">تعداد صفحات</th>
                        <th class="px-3 py-2 text-right">صفحات اشاره‌کننده</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($duplicates as $group)
                    <tr class="border-t border-gray-100 dark:border-gray-700 align-top">
                        <td class="px-3 py-2 font-mono ltr text-xs max-w-xs truncate" dir="ltr" title="{{ $group['canonical'] }}">{{ $group['canonical'] }}</td>
                        <td class="px-3 py-2 font-bold">{{ $group['count'] }}</td>
                        <td class="px-3 py-2">
                            <ul class="space-y-0.5">
                                @foreach($group['urls'] as $url)
                                    <li class="text-xs text-gray-600 dark:text-gray-300 font-mono ltr truncate max-w-md" dir="ltr" title="{{ $url }}">{{ $url }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-3 py-8 text-center text-gray-400">canonical تکراری‌ای یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
