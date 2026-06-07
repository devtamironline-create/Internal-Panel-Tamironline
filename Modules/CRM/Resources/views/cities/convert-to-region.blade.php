@extends('layouts.admin')

@section('page-title', 'تبدیل شهر به منطقه')

@section('main')
<div class="p-4 md:p-6 max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('crm.cities.index') }}" class="text-xs text-gray-500 hover:text-gray-700">← بازگشت به شهرها</a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2">تبدیل شهر به منطقه</h1>
        <p class="text-xs text-gray-500 mt-1">شهر «{{ $city->name }}» را به‌عنوان منطقه ذیل یکی از شهرهای دیگر همان استان منتقل می‌کنید.</p>
    </div>

    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-sm">
        <div class="font-bold text-amber-800 mb-2">⚠ این عملیات چه می‌کند</div>
        <ol class="list-decimal list-inside text-amber-700 space-y-1 leading-7">
            <li>منطقه‌ای با نام «{{ $city->name }}» ذیل شهر مقصد ساخته می‌شود (اگر نبود).</li>
            <li>تمام سفارش‌های ثبت‌شده روی این شهر ({{ number_format($orderCount) }} سفارش) خودکار به شهر+منطقهٔ جدید منتقل می‌شوند.</li>
            <li>این شهر «{{ $city->name }}» <b>غیرفعال</b> می‌شود (حذف نمی‌شود — history سالم می‌ماند).</li>
            <li>در dropdown ثبت سفارش جدید، این شهر دیگر نمایش داده نمی‌شود.</li>
        </ol>
    </div>

    @if($targets->isEmpty())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-4 text-sm">
            هیچ شهر فعال دیگری در همین استان موجود نیست. ابتدا شهر مقصد (مثلاً «تهران») را در همان استان بسازید.
        </div>
    @else
        <form action="{{ route('crm.cities.convert.store', $city) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر مقصد <span class="text-rose-600">*</span></label>
                <select name="target_city_id" required
                        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($targets as $t)
                        <option value="{{ $t->id }}" @selected(old('target_city_id') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
                @error('target_city_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام منطقه <span class="text-rose-600">*</span></label>
                <input type="text" name="region_name" required maxlength="120" value="{{ old('region_name', $city->name) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">پیش‌فرض: همان نام شهر فعلی. در صورت لزوم می‌توانید مثلاً «۱» را به‌جای «منطقه ۱» بنویسید.</p>
                @error('region_name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        onclick="return confirm('این عملیات قابل بازگشت دستی نیست. مطمئنی؟');"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg">
                    تبدیل و انتقال سفارش‌ها
                </button>
                <a href="{{ route('crm.cities.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg">انصراف</a>
            </div>
        </form>
    @endif
</div>
@endsection
