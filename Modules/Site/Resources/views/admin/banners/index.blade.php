@extends('layouts.admin')
@section('page-title', 'بنرها و اسلایدر')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">بنرها و اسلایدر</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">مدیریت بنرهای صفحه اصلی و سایر موقعیت‌ها.</p>
        </div>
        <a href="{{ route('site.admin.banners.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">+ افزودن بنر</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2">
        <select name="position" class="px-3 py-2 border border-gray-200 rounded text-sm">
            <option value="">همه موقعیت‌ها</option>
            @foreach($positions as $pos)
            <option value="{{ $pos }}" @selected(request('position') === $pos)>{{ $pos }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">پیش‌نمایش</th>
                    <th class="px-4 py-2 text-right">عنوان</th>
                    <th class="px-4 py-2 text-right">موقعیت</th>
                    <th class="px-4 py-2 text-right">انتشار</th>
                    <th class="px-4 py-2 text-right">بازه زمانی</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $b)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3"><img src="{{ $b->image_url }}" alt="" class="h-10 w-16 object-cover rounded"></td>
                    <td class="px-4 py-3">
                        <div class="font-semibold">{{ $b->title }}</div>
                        @if($b->subtitle)<div class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($b->subtitle, 80) }}</div>@endif
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $b->position }}</td>
                    <td class="px-4 py-3">
                        @if($b->is_published)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">منتشر</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $b->starts_at ? \Morilog\Jalali\Jalalian::fromDateTime($b->starts_at)->format('Y/m/d H:i') : '—' }}
                        <br>تا<br>
                        {{ $b->ends_at ? \Morilog\Jalali\Jalalian::fromDateTime($b->ends_at)->format('Y/m/d H:i') : '—' }}
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.banners.edit', $b->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.banners.destroy', $b->id) }}" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">بنری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
