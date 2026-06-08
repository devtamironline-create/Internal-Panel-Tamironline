@extends('layouts.admin')
@section('page-title', 'کارشناسان انجمن')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-blue-50 to-cyan-50 dark:from-blue-900/30 dark:to-cyan-900/30 rounded-xl border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">کارشناسان انجمن</h1>
                <p class="text-sm text-gray-600 mt-0.5">پاسخ‌های ادمین می‌تواند به‌نام این کارشناسان منتشر شود.</p>
            </div>
        </div>
        <a href="{{ route('site.admin.forum.experts.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">+ افزودن کارشناس</a>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="mb-5 flex gap-2 items-center p-3 bg-white border border-gray-200 rounded-lg">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">فیلتر</button>
    </form>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-right">کارشناس</th>
                    <th class="px-4 py-2 text-right">عنوان</th>
                    <th class="px-4 py-2 text-right">امتیاز</th>
                    <th class="px-4 py-2 text-right">پاسخ‌ها</th>
                    <th class="px-4 py-2 text-right">وضعیت</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($experts as $e)
                <tr class="border-t border-gray-100">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if($e->avatar)
                                <img src="{{ $e->avatar }}" alt="{{ $e->name }}" class="w-9 h-9 rounded-full object-cover">
                            @else
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 font-bold">{{ mb_substr($e->name, 0, 1, 'UTF-8') }}</div>
                            @endif
                            <div>
                                <div class="font-semibold">{{ $e->name }}</div>
                                <div class="text-xs text-gray-500 font-mono ltr" dir="ltr">/{{ $e->slug }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs">{{ $e->title }}</td>
                    <td class="px-4 py-3">⭐ {{ number_format((float) $e->rating, 1) }}</td>
                    <td class="px-4 py-3">{{ $e->answers_count }}</td>
                    <td class="px-4 py-3">
                        @if($e->is_active)<span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">فعال</span>
                        @else<span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-600">غیرفعال</span>@endif
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.forum.experts.edit', $e->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.forum.experts.destroy', $e->id) }}" onsubmit="return confirm('حذف؟');">
                            @csrf @method('DELETE')<button class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">کارشناسی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $experts->links() }}</div>
</div>
@endsection
