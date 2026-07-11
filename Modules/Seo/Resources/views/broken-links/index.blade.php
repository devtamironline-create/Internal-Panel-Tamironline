@extends('layouts.admin')

@section('page-title', 'لینک‌های خراب')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">لینک‌های خراب</h1>
        <a href="{{ route('seo.admin.audit.index') }}" class="text-sm text-blue-600 hover:underline">اجرای کرال جدید &larr;</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    @if(! $run)
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">
            هنوز کرالی با گرافِ لینک اجرا نشده است.
            <a href="{{ route('seo.admin.audit.index') }}" class="text-blue-600 hover:underline">یک کرال اجرا کنید</a>
            تا لینک‌های خراب این‌جا نمایش داده شوند.
        </div>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            بر اساس کرال #{{ $run->id }} — {{ optional($run->finished_at)->format('Y/m/d H:i') }}.
            هر مقصد یک‌بار بررسی شده و صفحاتِ ارجاع‌دهنده به آن گروه‌بندی شده‌اند.
        </p>

        <div class="flex gap-1 text-xs mb-4">
            @php $tabs = ['broken' => 'خراب (4xx/5xx)', 'redirect' => 'ریدایرکت‌شده (3xx)', 'ignored' => 'نادیده‌گرفته']; @endphp
            @foreach($tabs as $val => $label)
                <a href="{{ route('seo.admin.broken-links.index', ['type' => $val]) }}"
                   class="px-3 py-1.5 rounded-full {{ $type === $val ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                    {{ $label }} <span class="opacity-70">({{ $counts[$val] }})</span>
                </a>
            @endforeach
        </div>

        @if(! $targets || $targets->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">
                موردی در این دسته یافت نشد. 🎉
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs">
                        <tr>
                            <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                            <th class="px-4 py-3 text-right font-medium">مقصد</th>
                            <th class="px-4 py-3 text-center font-medium">صفحاتِ ارجاع‌دهنده</th>
                            <th class="px-4 py-3 text-center font-medium">نوع</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($targets as $t)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    @include('seo::broken-links._status', ['t' => $t])
                                </td>
                                <td class="px-4 py-3 max-w-md">
                                    <span dir="ltr" class="block truncate text-gray-800 dark:text-gray-200" title="{{ $t->url }}">{{ $t->url }}</span>
                                    @if($t->is_redirect && $t->final_url)
                                        <span dir="ltr" class="block truncate text-xs text-gray-400" title="{{ $t->final_url }}">↳ {{ $t->final_url }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold">{{ $t->ref_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $t->is_internal ? 'داخلی' : 'خارجی' }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('seo.admin.broken-links.show', $t) }}" class="text-brand-600 hover:underline text-xs whitespace-nowrap">جزئیات و اصلاح &larr;</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $targets->links() }}</div>
        @endif
    @endif
</div>
@endsection
