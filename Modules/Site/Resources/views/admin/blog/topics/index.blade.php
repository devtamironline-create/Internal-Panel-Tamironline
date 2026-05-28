@extends('layouts.admin')
@section('page-title', 'تاپیک‌های بلاگ')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-rose-50 to-amber-50 dark:from-rose-900/30 dark:to-amber-900/30 rounded-xl border border-rose-200 dark:border-rose-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-rose-500 to-amber-500 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">تاپیک‌های بلاگ</h1>
                <p class="text-sm text-gray-600 mt-0.5">عیب‌یابی، نگهداری، تعمیرات، کد خطا و ... — هر مقاله می‌تواند چند تاپیک داشته باشد.</p>
            </div>
        </div>
        <a href="{{ route('site.admin.blog.topics.create') }}" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-medium hover:bg-rose-700">+ افزودن تاپیک</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="mb-5 flex gap-2 items-center p-3 bg-white border border-gray-200 rounded-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <button class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-right">تاپیک</th>
                    <th class="px-4 py-2 text-right">slug</th>
                    <th class="px-4 py-2 text-right">پیش‌نمایش</th>
                    <th class="px-4 py-2 text-right">مقالات</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topics as $t)
                <tr class="border-t border-gray-100">
                    <td class="px-4 py-3 font-semibold">{{ $t->name }}</td>
                    <td class="px-4 py-3 font-mono text-xs ltr" dir="ltr">{{ $t->slug }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold"
                              style="background: {{ $t->color_bg ?? '#f3f4f6' }}; color: {{ $t->color_fg ?? '#374151' }}; border-color: {{ $t->color_border ?? '#e5e7eb' }};">
                            @if($t->icon)<span class="font-mono text-[10px] ltr" dir="ltr">[{{ $t->icon }}]</span>@endif
                            {{ $t->name }}
                        </span>
                    </td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-blue-50 text-blue-700">{{ $t->articles_count }}</span></td>
                    <td class="px-4 py-3">{{ $t->sort_order }}</td>
                    <td class="px-4 py-3">
                        @if($t->is_active)<span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">فعال</span>
                        @else<span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600">غیرفعال</span>@endif
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.blog.topics.edit', $t->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.blog.topics.destroy', $t->id) }}" onsubmit="return confirm('حذف؟');">
                            @csrf @method('DELETE')<button class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">تاپیکی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $topics->links() }}</div>
</div>
@endsection
