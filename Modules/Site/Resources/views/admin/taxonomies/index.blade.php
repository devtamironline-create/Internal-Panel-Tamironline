@extends('layouts.admin')

@php
    $isFaq = $type === 'faq';
    $title = $isFaq ? 'دسته‌بندی سوالات متداول' : 'دسته‌بندی نظرات مشتریان';
    $countRel = $isFaq ? 'faqs_count' : 'testimonials_count';
@endphp

@section('page-title', $title)

@section('main')
<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $title }}</h1>
            <p class="text-sm text-gray-500 mt-1">دسته‌بندی‌ها در فرانت به‌صورت تب نمایش داده می‌شوند.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-2 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-2 p-3 rounded bg-red-50 text-red-700 text-sm">
        <ul class="list-disc pr-4">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    {{-- فرم ایجاد سریع --}}
    <form method="POST" action="{{ route('site.admin.taxonomies.store', $type) }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-xs mb-1">نام دسته <span class="text-red-500">*</span></label>
            <input type="text" name="name" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="120">
        </div>
        <div>
            <label class="block text-xs mb-1">اسلاگ (اختیاری)</label>
            <input type="text" name="slug" dir="ltr" class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" maxlength="80" placeholder="support">
        </div>
        <div>
            <label class="block text-xs mb-1">توضیح</label>
            <input type="text" name="description" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" maxlength="300">
        </div>
        <div>
            <label class="block text-xs mb-1">ترتیب</label>
            <input type="number" name="sort_order" min="0" value="0" class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
        </div>
        <div>
            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded">افزودن</button>
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-right">نام</th>
                    <th class="px-4 py-2 text-right">اسلاگ</th>
                    <th class="px-4 py-2 text-right">توضیح</th>
                    <th class="px-4 py-2 text-right">ترتیب</th>
                    <th class="px-4 py-2 text-right">آیتم‌ها</th>
                    <th class="px-4 py-2 text-right">فعال</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $t)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <form method="POST" action="{{ route('site.admin.taxonomies.update', [$type, $t->id]) }}">
                        @csrf @method('PUT')
                        <td class="px-4 py-2"><input type="text" name="name" value="{{ $t->name }}" class="w-full px-2 py-1 border border-gray-200 rounded text-sm"></td>
                        <td class="px-4 py-2 font-mono text-xs"><input type="text" name="slug" value="{{ $t->slug }}" dir="ltr" class="w-full px-2 py-1 border border-gray-200 rounded text-sm ltr"></td>
                        <td class="px-4 py-2"><input type="text" name="description" value="{{ $t->description }}" class="w-full px-2 py-1 border border-gray-200 rounded text-sm"></td>
                        <td class="px-4 py-2"><input type="number" name="sort_order" value="{{ $t->sort_order }}" min="0" class="w-20 px-2 py-1 border border-gray-200 rounded text-sm"></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ $t->{$countRel} ?? 0 }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($t->is_active)>
                            </label>
                        </td>
                        <td class="px-4 py-2 flex gap-2">
                            <button type="submit" class="text-blue-600 text-sm hover:underline">ذخیره</button>
                    </form>
                            <form method="POST" action="{{ route('site.admin.taxonomies.destroy', [$type, $t->id]) }}" onsubmit="return confirm('حذف شود؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 text-sm hover:underline">حذف</button>
                            </form>
                        </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">دسته‌ای تعریف نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
