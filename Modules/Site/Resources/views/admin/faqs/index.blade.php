@extends('layouts.admin')

@section('page-title', 'مخزن سوالات متداول')

@section('main')
<div class="p-6"
     x-data="{
        selected: [],
        allIds: @js($allIds),
        get allChecked() { return this.allIds.length > 0 && this.selected.length === this.allIds.length; },
        toggleAll(checked) { this.selected = checked ? [...this.allIds] : []; },
        submitBulk(action) {
            if (this.selected.length === 0) return;
            if (action === 'delete' && ! confirm('حذفِ ' + this.selected.length + ' سوال؟ این عمل برگشت‌ناپذیر است.')) return;
            if ((action === 'attach' || action === 'detach') && ! this.$refs.taxField.value) { alert('ابتدا یک دسته‌بندی انتخاب کنید.'); return; }
            this.$refs.actionField.value = action;
            this.$refs.bulkForm.submit();
        }
     }">

    {{-- ─── Header ─────────────────────────────── --}}
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-xl border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">سوالات متداول</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">مخزن مرکزی FAQ — گروه‌بندی‌شده بر اساس دسته، با مدیریتِ گروهی و کپی.</p>
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
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ─── Filter bar ─────────────────────────── --}}
    <form method="GET" class="mb-4 flex gap-2 flex-wrap items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
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

    {{-- ─── Toolbar: select-all + bulk actions ─── --}}
    <div class="mb-4 flex items-center justify-between flex-wrap gap-3 p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)"
                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
            انتخاب همه ({{ $faqs->count() }} سوال)
        </label>

        <div class="flex items-center gap-2 flex-wrap" x-show="selected.length > 0" x-cloak>
            <span class="text-sm font-bold text-blue-700" x-text="selected.length + ' انتخاب شده'"></span>

            {{-- فرمِ مدیریتِ گروهی — جدا از کارت‌ها (تا nested-form نشود) --}}
            <form method="POST" action="{{ route('site.admin.faqs.bulk') }}" x-ref="bulkForm" class="flex items-center gap-2 flex-wrap">
                @csrf
                <input type="hidden" name="action" x-ref="actionField">
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>

                <select x-ref="taxField" name="taxonomy_id" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-xs">
                    <option value="">— دسته‌بندی —</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <button type="button" @click="submitBulk('attach')" class="px-2.5 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs hover:bg-indigo-100">+ افزودن به دسته</button>
                <button type="button" @click="submitBulk('detach')" class="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs hover:bg-amber-100">− حذف از دسته</button>

                <span class="w-px h-5 bg-gray-200"></span>

                <button type="button" @click="submitBulk('publish')" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs hover:bg-emerald-100">انتشار</button>
                <button type="button" @click="submitBulk('unpublish')" class="px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs hover:bg-gray-200">پیش‌نویس</button>
                <button type="button" @click="submitBulk('duplicate')" class="px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs hover:bg-blue-100">کپی</button>
                <button type="button" @click="submitBulk('delete')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-xs hover:bg-rose-100">حذف</button>
            </form>

            <button type="button" @click="selected = []" class="text-xs text-gray-500 hover:underline">لغو انتخاب</button>
        </div>
    </div>

    {{-- ─── Grouped by category ────────────────── --}}
    @if($faqs->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
            <p class="text-gray-500 mb-3">سوالی مطابق فیلتر یافت نشد.</p>
            <a href="{{ route('site.admin.faqs.create') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">افزودن سوال</a>
        </div>
    @else
        <div class="space-y-6">
            @foreach($categories as $cat)
                @php($group = $byCategory[$cat->id] ?? [])
                @if(count($group) > 0)
                    <section x-data="{ open: true }">
                        <div class="flex items-center justify-between mb-2">
                            <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-gray-100">
                                <svg class="w-4 h-4 transition" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                {{ $cat->name }}
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-100 text-indigo-700">{{ count($group) }}</span>
                            </button>
                            <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                                <input type="checkbox"
                                       @change="$event.target.checked
                                            ? selected = [...new Set([...selected, ...@js(collect($group)->pluck('id')->all())])]
                                            : selected = selected.filter(i => ! @js(collect($group)->pluck('id')->all()).includes(i))"
                                       class="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded">
                                انتخاب این دسته
                            </label>
                        </div>
                        <div class="space-y-2" x-show="open">
                            @foreach($group as $f)
                                @include('site::admin.faqs._card', ['f' => $f])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if(count($uncategorized) > 0)
                <section x-data="{ open: true }">
                    <div class="flex items-center gap-2 mb-2">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-gray-100">
                            <svg class="w-4 h-4 transition" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                            بدونِ دسته‌بندی
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-600">{{ count($uncategorized) }}</span>
                        </button>
                    </div>
                    <div class="space-y-2" x-show="open">
                        @foreach($uncategorized as $f)
                            @include('site::admin.faqs._card', ['f' => $f])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
</div>
@endsection
