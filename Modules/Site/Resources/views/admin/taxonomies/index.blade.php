@extends('layouts.admin')

@php
    $isFaq = $type === 'faq';
    $title = $isFaq ? 'دسته‌بندی سوالات متداول' : 'دسته‌بندی نظرات مشتریان';
    $countRel = $isFaq ? 'faqs_count' : 'testimonials_count';
    $itemsLabel = $isFaq ? 'سوال' : 'نظر';
@endphp

@section('page-title', $title)

@section('main')
<div class="p-6 space-y-5">

    {{-- ─── Header + stats ─────────────────────── --}}
    <div class="p-5 bg-gradient-to-l from-indigo-50 to-purple-50 dark:from-indigo-900/30 dark:to-purple-900/30 rounded-xl border border-indigo-200 dark:border-indigo-800">
        <div class="flex items-start justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">دسته‌بندی‌ها در فرانت به‌صورتِ تب نمایش داده می‌شوند. ترتیب و وضعیتِ فعال‌بودن را اینجا مدیریت کنید.</p>
                </div>
            </div>
            @if($isFaq)
                <a href="{{ route('site.admin.faqs.index') }}" class="px-3 py-2 rounded-lg bg-white text-gray-700 border border-gray-200 text-sm hover:bg-gray-50 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    بازگشت به سوالات
                </a>
            @endif
        </div>
        <div class="flex items-center gap-3 mt-4 flex-wrap">
            <div class="px-4 py-2 bg-white/70 dark:bg-gray-800/60 rounded-lg border border-indigo-100 dark:border-indigo-800">
                <span class="text-xs text-gray-500">کل دسته‌ها</span>
                <span class="block text-lg font-bold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</span>
            </div>
            <div class="px-4 py-2 bg-white/70 dark:bg-gray-800/60 rounded-lg border border-emerald-100 dark:border-emerald-800">
                <span class="text-xs text-gray-500">فعال</span>
                <span class="block text-lg font-bold text-emerald-600">{{ $stats['active'] }}</span>
            </div>
            <div class="px-4 py-2 bg-white/70 dark:bg-gray-800/60 rounded-lg border border-blue-100 dark:border-blue-800">
                <span class="text-xs text-gray-500">کل {{ $itemsLabel }}‌ها</span>
                <span class="block text-lg font-bold text-blue-600">{{ $stats['items'] }}</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <ul class="list-disc pr-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ─── Add panel (collapsible) ────────────── --}}
    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, name: '', slug: '', touched: false }"
         class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <span class="inline-flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                افزودنِ دستهٔ جدید
            </span>
            <svg class="w-4 h-4 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <form method="POST" action="{{ route('site.admin.taxonomies.store', $type) }}" x-show="open" x-collapse
              class="px-5 pb-5 pt-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end border-t border-gray-100 dark:border-gray-700">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">نام دسته <span class="text-rose-500">*</span></label>
                <input type="text" name="name" x-model="name"
                       @input="if (!touched) slug = name.trim().toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-]/g,'')"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" required maxlength="120" placeholder="مثلاً: گارانتی">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">اسلاگ (انگلیسی)</label>
                <input type="text" name="slug" x-model="slug" @input="touched = true" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr focus:ring-2 focus:ring-indigo-500" maxlength="80" placeholder="warranty">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">توضیح (اختیاری)</label>
                <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" maxlength="300">
            </div>
            <div class="flex items-end gap-3">
                <div class="w-24">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">ترتیب</label>
                    <input type="number" name="sort_order" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <input type="hidden" name="is_active" value="1">
                <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">افزودن</button>
            </div>
        </form>
    </div>

    {{-- ─── List ───────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($items as $t)
            <div x-data="{ editing: false }" class="p-4">

                {{-- نمایش --}}
                <div x-show="!editing" class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-mono bg-gray-100 dark:bg-gray-700 text-gray-500 shrink-0" dir="ltr">#{{ $t->sort_order }}</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-900 dark:text-gray-100">{{ $t->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-indigo-50 text-indigo-600" dir="ltr">{{ $t->slug }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-50 text-blue-600">{{ $t->{$countRel} ?? 0 }} {{ $itemsLabel }}</span>
                                @unless($t->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-500">غیرفعال</span>
                                @endunless
                            </div>
                            @if($t->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $t->description }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        {{-- سوییچِ فعال/غیرفعال (toggle سریع) --}}
                        <form method="POST" action="{{ route('site.admin.taxonomies.toggle', [$type, $t->id]) }}">
                            @csrf @method('PUT')
                            <button type="submit" title="{{ $t->is_active ? 'غیرفعال‌کردن' : 'فعال‌کردن' }}"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $t->is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $t->is_active ? '-translate-x-6' : '-translate-x-1' }}"></span>
                            </button>
                        </form>
                        <button type="button" @click="editing = true" class="text-sm text-blue-600 hover:underline">ویرایش</button>
                        <form method="POST" action="{{ route('site.admin.taxonomies.destroy', [$type, $t->id]) }}" onsubmit="return confirm('این دسته حذف شود؟ (سوالاتِ داخلش حذف نمی‌شوند، فقط از این دسته خارج می‌شوند)');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-rose-600 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>

                {{-- ویرایشِ درجا --}}
                <form x-show="editing" x-cloak method="POST" action="{{ route('site.admin.taxonomies.update', [$type, $t->id]) }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    @csrf @method('PUT')
                    <div class="lg:col-span-1">
                        <label class="block text-[11px] text-gray-500 mb-1">نام</label>
                        <input type="text" name="name" value="{{ $t->name }}" required maxlength="120" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-1">اسلاگ</label>
                        <input type="text" name="slug" value="{{ $t->slug }}" dir="ltr" maxlength="80" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr">
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-1">توضیح</label>
                        <input type="text" name="description" value="{{ $t->description }}" maxlength="300" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                    <div class="w-24">
                        <label class="block text-[11px] text-gray-500 mb-1">ترتیب</label>
                        <input type="number" name="sort_order" value="{{ $t->sort_order }}" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- وضعیتِ فعال از سوییچِ بالا کنترل می‌شود؛ اینجا مقدارِ فعلی حفظ می‌شود --}}
                        <input type="hidden" name="is_active" value="{{ $t->is_active ? 1 : 0 }}">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">ذخیره</button>
                        <button type="button" @click="editing = false" class="px-3 py-2 text-gray-600 text-sm hover:underline">انصراف</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                <p class="mb-1">هنوز دسته‌ای تعریف نشده است.</p>
                <p class="text-xs">از پنلِ «افزودنِ دستهٔ جدید» بالا اولین دسته را بسازید.</p>
            </div>
        @endforelse
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
