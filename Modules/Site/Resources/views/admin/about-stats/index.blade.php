@extends('layouts.admin')

@section('page-title', 'آمار صفحه‌ی About')

@section('main')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">آمار صفحه‌ی درباره ما</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">کارت‌های آمار سکشن A2 — مثلاً «سال تجربه»، «تعمیر موفق».</p>
        </div>
        <a href="{{ route('site.admin.about-stats.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">+ افزودن آمار</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">کلید</th>
                    <th class="px-4 py-2 text-right">مقدار</th>
                    <th class="px-4 py-2 text-right">برچسب</th>
                    <th class="px-4 py-2 text-right">تم</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">انتشار</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $s)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-4 py-3 font-mono text-xs">{{ $s->key }}</td>
                    <td class="px-4 py-3 font-bold">{{ $s->value }}</td>
                    <td class="px-4 py-3">{{ $s->label }}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ $s->tone }}</span></td>
                    <td class="px-4 py-3">{{ $s->sort_order }}</td>
                    <td class="px-4 py-3">
                        @if($s->is_published)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">منتشر</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        <a href="{{ route('site.admin.about-stats.edit', $s->id) }}" class="text-blue-600 hover:underline">ویرایش</a>
                        <form method="POST" action="{{ route('site.admin.about-stats.destroy', $s->id) }}" onsubmit="return confirm('حذف شود؟');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">آماری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
