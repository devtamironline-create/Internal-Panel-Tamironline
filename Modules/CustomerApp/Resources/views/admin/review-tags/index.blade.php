@extends('layouts.admin')

@section('page-title', 'تگ‌های نظرسنجی')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تگ‌های نقاط قوت/ضعف</h1>
            <p class="text-xs text-gray-500 mt-1">این تگ‌ها در فرم نظرسنجی اپ موبایل نمایش داده می‌شوند. توصیه: تعداد نقاط قوت و ضعف برابر بمانند.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('customer-app.review-tags.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+ افزودن</a>
            <a href="{{ route('customer-app.dashboard') }}" class="text-xs px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">← هاب اپ</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    {{-- شمارنده‌ها / فیلتر سریع type --}}
    <div class="grid grid-cols-2 gap-3">
        <a href="?type=pro" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 text-center {{ $type === 'pro' ? 'ring-2 ring-emerald-400' : '' }}">
            <div class="text-xs text-gray-500">نقاط قوت</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($counts['pro']) }}</div>
        </a>
        <a href="?type=con" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 text-center {{ $type === 'con' ? 'ring-2 ring-rose-400' : '' }}">
            <div class="text-xs text-gray-500">نقاط ضعف</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($counts['con']) }}</div>
        </a>
    </div>

    @if($counts['pro'] !== $counts['con'])
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">
            ⚠️ تعداد نقاط قوت ({{ $counts['pro'] }}) و ضعف ({{ $counts['con'] }}) برابر نیست. توصیه می‌شود برابر بمانند تا تعادل UX در اپ حفظ شود.
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start w-16">ترتیب</th>
                    <th class="px-4 py-2 text-start">عنوان</th>
                    <th class="px-4 py-2 text-start">slug</th>
                    <th class="px-4 py-2 text-center w-24">نوع</th>
                    <th class="px-4 py-2 text-center w-24">وضعیت</th>
                    <th class="px-4 py-2 text-center w-40">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 text-gray-500">{{ $it->sort_order }}</td>
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                            @if($it->icon)<span class="text-gray-400 text-xs">[{{ $it->icon }}]</span>@endif
                            {{ $it->label }}
                        </td>
                        <td class="px-4 py-2 text-gray-500 font-mono text-xs" dir="ltr">{{ $it->slug }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($it->type === 'pro')
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">نقطه قوت</span>
                            @else
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">نقطه ضعف</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            <form action="{{ route('customer-app.review-tags.toggle-active', $it) }}" method="POST" class="inline">
                                @csrf @method('PUT')
                                <button class="text-[11px] px-2 py-0.5 rounded-full {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $it->is_active ? 'فعال' : 'غیرفعال' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-center space-x-2 space-x-reverse">
                            <a href="{{ route('customer-app.review-tags.edit', $it) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>
                            <form action="{{ route('customer-app.review-tags.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">تگی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
