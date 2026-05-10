@extends('layouts.admin')

@section('page-title', 'دسته‌بندی تیکت‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">دسته‌بندی تیکت‌های پشتیبانی</h1>
        <a href="{{ route('crm.tickets.index') }}" class="text-sm text-brand-700 hover:underline">← بازگشت به تیکت‌ها</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 text-sm text-rose-700">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- فرم افزودن --}}
    <form method="POST" action="{{ route('crm.tickets.categories.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        @csrf
        <div class="text-sm font-bold mb-3">دستهٔ جدید</div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
            <div class="md:col-span-7">
                <label class="block text-xs text-gray-500 mb-1">نام</label>
                <input type="text" name="name" required maxlength="100"
                       value="{{ old('name') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">ترتیب</label>
                <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="flex items-center gap-2 cursor-pointer mt-5">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4">
                    <span class="text-xs">فعال</span>
                </label>
            </div>
            <div class="md:col-span-1">
                <button type="submit" class="w-full px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+</button>
            </div>
        </div>
    </form>

    {{-- لیست --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse($categories as $cat)
            <form method="POST" action="{{ route('crm.tickets.categories.update', $cat) }}"
                  class="p-3 border-b border-gray-100 dark:border-gray-700 last:border-0 grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                @csrf
                @method('PUT')
                <div class="md:col-span-6">
                    <input type="text" name="name" required maxlength="100" value="{{ $cat->name }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <input type="number" name="sort_order" min="0" max="9999" value="{{ $cat->sort_order }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($cat->is_active) class="w-4 h-4">
                        <span class="text-xs">فعال</span>
                    </label>
                </div>
                <div class="md:col-span-1 text-xs text-gray-500 text-center">
                    {{ $cat->tickets_count }} تیکت
                </div>
                <div class="md:col-span-1 flex gap-1">
                    <button type="submit" class="flex-1 px-2 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs">ذخیره</button>
                </div>
            </form>
            <form method="POST" action="{{ route('crm.tickets.categories.destroy', $cat) }}"
                  onsubmit="return confirm('این دسته‌بندی حذف شود؟ تیکت‌های مرتبط بدون دسته خواهند ماند.');"
                  class="px-3 pb-2 -mt-2">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-rose-600 hover:text-rose-800">حذف این دسته</button>
            </form>
        @empty
            <div class="p-8 text-center text-sm text-gray-500">دسته‌بندی‌ای ثبت نشده.</div>
        @endforelse
    </div>
</div>
@endsection
