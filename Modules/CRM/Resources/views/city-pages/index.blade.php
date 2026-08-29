@extends('layouts.admin')

@section('page-title', 'صفحات سئوی ' . $city->name)

@section('main')
@php($groupLabels = [
    'city' => 'صفحهٔ اصلی شهر',
    'services' => 'فهرست خدمات شهر',
    'device' => 'صفحاتِ خدمت در شهر',
    'brands' => 'فهرست برندهای شهر',
    'brand' => 'صفحاتِ برند در شهر',
    'combo' => 'صفحاتِ خدمت + برند در شهر',
])
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('crm.city-pages.overview') }}" class="hover:text-purple-600">صفحات سئوی شهرها</a>
                <span>/</span>
                <span>{{ $city->province?->name }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">صفحات سئوی {{ $city->name }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">
                همه: <b>{{ (int) ($summary->total ?? 0) }}</b> ·
                منتشرشده: <b class="text-green-600">{{ (int) ($summary->published ?? 0) }}</b> ·
                پیش‌نویس: <b class="text-amber-600">{{ (int) ($summary->draft ?? 0) }}</b>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <form action="{{ route('crm.cities.pages.sync', $city) }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    همگام‌سازی صفحات
                </button>
            </form>
            @if((int) ($summary->draft ?? 0) > 0)
            <form action="{{ route('crm.cities.pages.publish-all', $city) }}" method="POST"
                  onsubmit="return confirm('همهٔ پیش‌نویس‌های این شهر منتشر می‌شوند و روی سایت قابل دیدن خواهند بود. مطمئنید؟');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    انتشار همه ({{ (int) ($summary->draft ?? 0) }})
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    @if((int) ($summary->total ?? 0) === 0)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 text-sm">
        هنوز صفحه‌ای ساخته نشده. روی «همگام‌سازی صفحات» بزنید تا از روی پوششِ فعلیِ شهر (تکنسین‌های تگ‌خورده)
        ساخته شود. اگر هنوز تکنسینی برای این شهر تگ نخورده، فقط سه صفحهٔ اصلی ساخته می‌شود.
    </div>
    @endif

    {{-- فیلتر وضعیت --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="text-gray-500 dark:text-gray-400">فیلتر:</span>
        @php($tabs = ['' => 'همه', 'draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'archived' => 'بایگانی'])
        @foreach($tabs as $key => $label)
            <a href="{{ route('crm.cities.pages.index', ['city' => $city->id, 'status' => $key]) }}"
               class="px-3 py-1 rounded-full {{ $status === $key ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @forelse($groupLabels as $type => $label)
        @php($rows = $pages->get($type))
        @if($rows && $rows->count())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">{{ $label }}</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $rows->count() }} صفحه</span>
            </div>
            <table class="w-full">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($rows as $page)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $page->title }}</div>
                            <div class="text-xs text-gray-400 dir-ltr text-left mt-0.5">{{ $page->path }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs {{ $page->statusBadge() }}">{{ $page->statusLabel() }}</span>
                        </td>
                        <td class="px-6 py-3 text-left whitespace-nowrap">
                            <div class="flex items-center gap-3 justify-end">
                                <a href="{{ route('crm.city-pages.preview', $page) }}" target="_blank" class="text-gray-600 hover:text-gray-900 text-sm">پیش‌نمایش</a>
                                <a href="{{ route('crm.city-pages.edit', $page) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                                <form action="{{ route('crm.city-pages.toggle-publish', $page) }}" method="POST" class="inline">
                                    @csrf @method('PUT')
                                    @if($page->isPublished())
                                        <button type="submit" class="text-amber-600 hover:text-amber-800 text-sm">بازگردانی به پیش‌نویس</button>
                                    @else
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium">انتشار</button>
                                    @endif
                                </form>
                                <form action="{{ route('crm.city-pages.destroy', $page) }}" method="POST" class="inline"
                                      onsubmit="return confirm('این صفحه حذف شود؟ (با همگام‌سازیِ بعدی دوباره ساخته می‌شود)');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    @empty
    @endforelse
</div>
@endsection
