@extends('layouts.admin')

@section('page-title', 'لینک‌سازی داخلی')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">لینک‌سازی داخلی و صفحاتِ Orphan</h1>
        <a href="{{ route('seo.admin.audit.index') }}" class="text-sm text-blue-600 hover:underline">اجرای کرال جدید &larr;</a>
    </div>

    @if(! $run)
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">
            هنوز کرالی با گرافِ لینک اجرا نشده است.
        </div>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">بر اساس کرال #{{ $run->id }} — {{ optional($run->finished_at)->format('Y/m/d H:i') }}.</p>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ number_format($stats['pages']) }}</div>
                <div class="text-xs text-gray-500 mt-1">صفحاتِ قابل‌ایندکس</div>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ number_format($stats['linked']) }}</div>
                <div class="text-xs text-gray-500 mt-1">دارای لینکِ داخلیِ ورودی</div>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                <div class="text-2xl font-bold {{ $stats['orphan'] > 0 ? 'text-orange-600' : 'text-gray-800 dark:text-white' }}">{{ number_format($stats['orphan']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Orphan (بدونِ لینکِ ورودی)</div>
            </div>
        </div>

        <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-3">صفحاتِ Orphan</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            این صفحات در sitemap/کرال هستند ولی هیچ صفحهٔ دیگری به آن‌ها لینکِ داخلی نداده — برای موتور جست‌وجو کشفشان سخت‌تر است.
        </p>

        @if($orphans->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">
                هیچ صفحهٔ Orphan یافت نشد. 🎉
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs">
                        <tr>
                            <th class="px-4 py-3 text-right font-medium">صفحه</th>
                            <th class="px-4 py-3 text-center font-medium">لینک‌های خروجی</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($orphans as $o)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3"><span dir="ltr" class="block truncate max-w-lg text-gray-800 dark:text-gray-200" title="{{ $o['url'] }}">{{ $o['path'] }}</span></td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $o['outgoing'] }}</td>
                                <td class="px-4 py-3 text-left"><a href="{{ $o['url'] }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline text-xs">باز کردن &larr;</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection
