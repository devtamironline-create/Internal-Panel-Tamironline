@extends('layouts.admin')

@section('page-title', 'صفحات سایت')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">صفحات سایت</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">صفحات استاتیک و خدمات (about، contact، home، ...).</p>
        </div>
        <a href="{{ route('site.admin.pages.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">+ افزودن صفحه</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در عنوان یا اسلاگ..."
               class="flex-1 px-3 py-2 border border-gray-200 rounded text-sm" />
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">جستجو</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">عنوان</th>
                    <th class="px-4 py-2 text-right">اسلاگ</th>
                    <th class="px-4 py-2 text-right">انتشار</th>
                    <th class="px-4 py-2 text-right">آخرین تغییر</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $p)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3 font-semibold">{{ $p->title }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $p->slug }}</td>
                    <td class="px-4 py-3">
                        @if($p->is_published)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">منتشر</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($p->updated_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.pages.edit', $p->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.pages.destroy', $p->id) }}" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">صفحه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
