@extends('layouts.admin')

@section('page-title', 'مخزن سوالات متداول')

@section('main')
<div class="p-6">

    {{-- ─── Header card ────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-xl border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">سوالات متداول</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">مخزن مرکزی FAQ — قابل انتخاب از پنل device، brand و device-brand.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('site.admin.taxonomies.index', 'faq') }}"
               class="px-3 py-2 rounded-lg bg-white text-gray-700 border border-gray-200 text-sm hover:bg-gray-50">دسته‌بندی FAQ</a>
            <a href="{{ route('site.admin.faqs.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                افزودن سوال جدید
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- ─── Filter bar ─────────────────────────── --}}
    <form method="GET" class="mb-5 flex gap-2 flex-wrap items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در سوال یا پاسخ..."
                   class="w-full pr-3 pl-10 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </div>
        <select name="published" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">همه (انتشار)</option>
            <option value="1" @selected(request('published') === '1')>منتشر شده</option>
            <option value="0" @selected(request('published') === '0')>پیش‌نویس</option>
        </select>
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">فیلتر</button>
        @if(request('q') || request('published') !== null)
            <a href="{{ route('site.admin.faqs.index') }}" class="px-3 py-2 text-gray-600 text-sm hover:underline">پاک‌کردن</a>
        @endif
    </form>

    {{-- ─── List as cards ──────────────────────── --}}
    <div class="space-y-3">
        @forelse($items as $f)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            @if($f->is_published)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 shrink-0">منتشر</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600 shrink-0">پیش‌نویس</span>
                            @endif
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-blue-50 text-blue-600 shrink-0" dir="ltr">#{{ $f->sort_order }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('site.admin.faqs.edit', $f->id) }}" class="text-sm text-blue-600 hover:underline">ویرایش</a>
                            <form method="POST" action="{{ route('site.admin.faqs.destroy', $f->id) }}" onsubmit="return confirm('حذف شود؟');">
                                @csrf @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">حذف</button>
                            </form>
                        </div>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 leading-relaxed">
                        {{ $f->question }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2 leading-relaxed line-clamp-3">
                        {{ \Illuminate\Support\Str::limit(strip_tags($f->answer), 240) }}
                    </p>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-gray-500 mb-3">هنوز سوالی ثبت نشده.</p>
                <a href="{{ route('site.admin.faqs.create') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">افزودن اولین سوال</a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
