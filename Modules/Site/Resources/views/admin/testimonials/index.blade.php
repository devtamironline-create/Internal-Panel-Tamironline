@extends('layouts.admin')

@section('page-title', 'مخزن نظرات مشتریان')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">نظرات مشتریان</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">مخزن نظرات؛ از هر صفحه می‌توانید نظرات دلخواه را انتخاب کنید.</p>
        </div>
        <a href="{{ route('site.admin.testimonials.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">+ افزودن نظر</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام یا موضوع..."
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm" />
        <select name="published" class="px-3 py-2 border border-gray-200 rounded text-sm">
            <option value="">همه</option>
            <option value="1" @selected(request('published') === '1')>منتشر شده</option>
            <option value="0" @selected(request('published') === '0')>پیش‌نویس</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">نام مشتری</th>
                    <th class="px-4 py-2 text-right">موضوع</th>
                    <th class="px-4 py-2 text-right">امتیاز</th>
                    <th class="px-4 py-2 text-right">انتشار</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $t)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3">{{ $t->customer_name }}</td>
                    <td class="px-4 py-3">{{ $t->topic }}</td>
                    <td class="px-4 py-3">{{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}</td>
                    <td class="px-4 py-3">
                        @if($t->is_published)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">منتشر</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $t->sort_order }}</td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.testimonials.edit', $t->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.testimonials.toggle-publish', $t->id) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="text-amber-600 hover:underline">{{ $t->is_published ? 'مخفی' : 'انتشار' }}</button>
                        </form>
                        <form method="POST" action="{{ route('site.admin.testimonials.destroy', $t->id) }}"
                              onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">موردی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
