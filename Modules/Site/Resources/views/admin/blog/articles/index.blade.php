@extends('layouts.admin')
@section('page-title', 'مقالات بلاگ')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-violet-50 to-fuchsia-50 dark:from-violet-900/30 dark:to-fuchsia-900/30 rounded-xl border border-violet-200 dark:border-violet-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">مقالات بلاگ</h1>
                <p class="text-sm text-gray-600 mt-0.5">طبقه‌بندی بر اساس تاپیک، دستگاه، برند یا ترکیبی از آن‌ها.</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('site.admin.blog.topics.index') }}" class="px-3 py-2 rounded-lg bg-white text-gray-700 border border-gray-200 text-sm hover:bg-gray-50">تاپیک‌ها</a>
            <a href="{{ route('site.admin.blog.articles.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">+ افزودن مقاله</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="mb-5 flex gap-2 flex-wrap items-center p-3 bg-white border border-gray-200 rounded-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در عنوان/excerpt..."
               class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <select name="topic" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه تاپیک‌ها</option>
            @foreach($topics as $t)
                <option value="{{ $t->slug }}" @selected(request('topic') === $t->slug)>{{ $t->name }}</option>
            @endforeach
        </select>
        <select name="published" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">همه (انتشار)</option>
            <option value="1" @selected(request('published') === '1')>منتشر شده</option>
            <option value="0" @selected(request('published') === '0')>پیش‌نویس</option>
        </select>
        <button class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    <div class="space-y-3">
        @forelse($articles as $a)
            <div class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition p-5">
                <div class="flex items-start gap-3 mb-2 flex-wrap">
                    @if($a->is_published)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">منتشر شده</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600">پیش‌نویس</span>
                    @endif
                    @foreach($a->topics as $t)
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold"
                              style="background: {{ $t->color_bg ?? '#f3f4f6' }}; color: {{ $t->color_fg ?? '#374151' }};">
                            {{ $t->name }}
                        </span>
                    @endforeach
                    @if($a->published_at)
                        <span class="text-xs text-gray-400">{{ \Morilog\Jalali\Jalalian::fromDateTime($a->published_at)->format('Y/m/d') }}</span>
                    @endif
                    @if($a->read_time_minutes)
                        <span class="text-xs text-gray-400">{{ $a->read_time_minutes }} دقیقه مطالعه</span>
                    @endif
                    <div class="ml-auto flex gap-2 text-sm">
                        <a href="{{ route('site.admin.blog.articles.edit', $a->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.blog.articles.destroy', $a->id) }}" onsubmit="return confirm('حذف مقاله؟');">
                            @csrf @method('DELETE')<button class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
                <h3 class="text-base font-bold leading-relaxed">{{ $a->title }}</h3>
                @if($a->excerpt)
                    <p class="text-sm text-gray-600 mt-2 leading-relaxed line-clamp-2">{{ $a->excerpt }}</p>
                @endif
                <div class="mt-3 flex gap-4 text-xs text-gray-500">
                    <span class="font-mono ltr" dir="ltr">/blog/{{ $a->slug }}</span>
                    @if($a->devices->count() > 0)
                        <span>دستگاه‌ها: {{ $a->devices->pluck('name')->join('، ') }}</span>
                    @endif
                    @if($a->brands->count() > 0)
                        <span>برندها: {{ $a->brands->pluck('name')->join('، ') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500">
                مقاله‌ای ثبت نشده.
                <a href="{{ route('site.admin.blog.articles.create') }}" class="text-violet-600 hover:underline ml-1">افزودن اولین مقاله</a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
</div>
@endsection
