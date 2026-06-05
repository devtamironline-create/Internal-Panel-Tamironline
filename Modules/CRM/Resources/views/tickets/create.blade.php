@extends('layouts.admin')

@section('page-title', 'ایجاد تیکت برای تکنسین')

@section('main')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('crm.tickets.index') }}" class="text-sm text-brand-700 hover:underline">← بازگشت به لیست تیکت‌ها</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">ایجاد تیکت برای تکنسین</h1>
        <p class="text-sm text-gray-500 mb-6">پیامی که اینجا ارسال کنید، در پنل تکنسین به‌صورت تیکت جدید نمایش داده می‌شود.</p>

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">
                <ul class="list-disc ps-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('crm.tickets.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین گیرنده <span class="text-rose-600">*</span></label>
                <select name="technician_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="">— تکنسین را انتخاب کنید —</option>
                    @foreach($technicians as $tech)
                        @php
                            $display = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                            $display = $display !== '' ? $display : ('تکنسین #' . $tech->id);
                        @endphp
                        <option value="{{ $tech->id }}" @selected(old('technician_id', $preselectedTech) == $tech->id)>
                            {{ $display }} — {{ $tech->mobile }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دسته‌بندی <span class="text-rose-600">*</span></label>
                <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">موضوع (اختیاری)</label>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">متن پیام <span class="text-rose-600">*</span></label>
                <textarea name="body" rows="6" maxlength="5000" required
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ old('body') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">پیوست تصویر (اختیاری)</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full text-sm text-gray-700 dark:text-gray-200">
                <p class="text-xs text-gray-500 mt-1">حداکثر ۱۰ مگابایت — JPG/PNG/WEBP</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">
                    ارسال تیکت
                </button>
                <a href="{{ route('crm.tickets.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
