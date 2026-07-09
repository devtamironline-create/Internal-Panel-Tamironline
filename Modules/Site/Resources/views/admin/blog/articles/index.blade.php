@extends('layouts.admin')
@section('page-title', 'مقالات بلاگ')

@section('main')
<div class="p-6" x-data="articleIndex()">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-violet-50 to-fuchsia-50 dark:from-violet-900/30 dark:to-fuchsia-900/30 rounded-xl border border-violet-200 dark:border-violet-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">مقالات بلاگ</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">طبقه‌بندی بر اساس تاپیک، دستگاه و برند — با ویرایش سریع تکی و گروهی.</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('site.admin.blog.topics.index') }}" class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 text-sm hover:bg-gray-50">تاپیک‌ها</a>
            <a href="{{ route('site.admin.blog.articles.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">+ افزودن مقاله</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    {{-- فیلتر --}}
    <form method="GET" class="mb-4 flex gap-2 flex-wrap items-center p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در عنوان/خلاصه/slug..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        <select name="device_id" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">همه دسته‌ها (دستگاه)</option>
            @foreach($devices as $d)
                <option value="{{ $d->id }}" @selected((int) request('device_id') === (int) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="topic" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">همه تاپیک‌ها (اختیاری)</option>
            @foreach($topics as $t)
                <option value="{{ $t->slug }}" @selected(request('topic') === $t->slug)>{{ $t->name }}</option>
            @endforeach
        </select>
        <select name="published" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">همه (انتشار)</option>
            <option value="1" @selected(request('published') === '1')>منتشر شده</option>
            <option value="0" @selected(request('published') === '0')>پیش‌نویس</option>
        </select>
        <button class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    {{-- نوار عملیات گروهی (فرمِ مستقل؛ چک‌باکس‌ها با form="article-bulk" متصل‌اند) --}}
    <form id="article-bulk" method="POST" action="{{ route('site.admin.blog.articles.bulk') }}"
          @submit="onBulkSubmit($event)"
          class="mb-4 flex flex-wrap items-center gap-2 p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        @csrf
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">عملیات گروهی روی انتخاب‌شده‌ها:</span>
        <select name="action" x-model="action" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">— انتخاب عملیات —</option>
            <option value="publish">انتشار</option>
            <option value="unpublish">پیش‌نویس کردن</option>
            <option value="attach">اتصال طبقه‌بندی (تاپیک/دستگاه/برند)</option>
            <option value="delete">حذف</option>
        </select>

        <template x-if="action === 'attach'">
            <span class="flex flex-wrap items-center gap-2">
                <select name="topic_id" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">تاپیک…</option>
                    @foreach($topics as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </select>
                <select name="device_id" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">دستگاه…</option>
                    @foreach($devices as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
                <select name="brand_id" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">برند…</option>
                    @foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </span>
        </template>

        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">اعمال</button>
        <span class="text-xs text-gray-500" x-text="count ? count + ' مقاله انتخاب شده' : ''"></span>
    </form>

    {{-- جدول مقالات --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-3 py-2 w-10 text-center"><input type="checkbox" @change="toggleAll($event)" title="انتخاب همه"></th>
                    <th class="px-3 py-2 text-start">مقاله</th>
                    <th class="px-3 py-2 text-start">طبقه‌بندی</th>
                    <th class="px-3 py-2 text-center w-28">وضعیت</th>
                    <th class="px-3 py-2 text-center w-28">تاریخ انتشار</th>
                    <th class="px-3 py-2 text-center w-28">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($articles as $a)
                    <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50/60 dark:hover:bg-gray-700/30 align-top">
                        <td class="px-3 py-3 text-center">
                            <input type="checkbox" name="ids[]" value="{{ $a->id }}" form="article-bulk" @change="refresh()">
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex gap-3">
                                <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 shrink-0 flex items-center justify-center">
                                    @if($a->cover_image)
                                        <img src="{{ \Modules\Site\Support\MediaUrl::resolve($a->cover_image) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-gray-300 text-xs">بدون کاور</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-gray-100 leading-snug line-clamp-2">{{ $a->title }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono ltr mt-0.5" dir="ltr">/blog/{{ $a->slug }}</div>
                                    @foreach($a->topics as $t)
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold mt-1 me-1"
                                              style="background: {{ $t->color_bg ?? '#f3f4f6' }}; color: {{ $t->color_fg ?? '#374151' }};">{{ $t->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-600 dark:text-gray-300">
                            @if($a->devices->count())
                                <div><span class="text-gray-400">دستگاه:</span> {{ $a->devices->pluck('name')->join('، ') }}</div>
                            @endif
                            @if($a->brands->count())
                                <div class="mt-0.5"><span class="text-gray-400">برند:</span> {{ $a->brands->pluck('name')->join('، ') }}</div>
                            @endif
                            @if(! $a->devices->count() && ! $a->brands->count())
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center">
                            <form method="POST" action="{{ route('site.admin.blog.articles.toggle-publish', $a->id) }}">
                                @csrf @method('PUT')
                                <button type="submit"
                                        class="text-[11px] px-2 py-1 rounded-full font-bold {{ $a->is_published ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}">
                                    {{ $a->is_published ? 'منتشر شده' : 'پیش‌نویس' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-3 py-3 text-center text-xs text-gray-500">
                            {{ $a->published_at ? \Morilog\Jalali\Jalalian::fromDateTime($a->published_at)->format('Y/m/d') : '—' }}
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-center gap-2 text-sm">
                                <a href="{{ route('site.admin.blog.articles.edit', $a->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                                <form method="POST" action="{{ route('site.admin.blog.articles.destroy', $a->id) }}" onsubmit="return confirm('حذف مقاله؟');">
                                    @csrf @method('DELETE')<button class="text-red-600 hover:underline">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">
                        مقاله‌ای ثبت نشده.
                        <a href="{{ route('site.admin.blog.articles.create') }}" class="text-violet-600 hover:underline ms-1">افزودن اولین مقاله</a>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
</div>

<script>
    function articleIndex() {
        return {
            action: '',
            count: 0,
            boxes() { return document.querySelectorAll('input[name="ids[]"]'); },
            selectedCount() { return document.querySelectorAll('input[name="ids[]"]:checked').length; },
            toggleAll(e) { this.boxes().forEach((c) => { c.checked = e.target.checked; }); this.refresh(); },
            refresh() { this.count = this.selectedCount(); },
            onBulkSubmit(e) {
                if (! this.action) { e.preventDefault(); alert('یک عملیات انتخاب کنید.'); return; }
                if (this.selectedCount() === 0) { e.preventDefault(); alert('هیچ مقاله‌ای انتخاب نشده است.'); return; }
                if (this.action === 'delete' && ! confirm('مقالات انتخاب‌شده حذف شوند؟')) { e.preventDefault(); return; }
                if (this.action === 'attach') {
                    const f = e.target;
                    if (! f.topic_id.value && ! f.device_id.value && ! f.brand_id.value) {
                        e.preventDefault(); alert('برای اتصال، حداقل یک تاپیک/دستگاه/برند انتخاب کنید.');
                    }
                }
            },
        };
    }
</script>
@endsection
