@csrf
<div class="grid grid-cols-1 md:grid-cols-12 gap-4">
    <div class="md:col-span-4">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ شمسی <span class="text-rose-500">*</span></label>
        @php
            $oldDate = old('date_jalali');
            if (! $oldDate && isset($item) && $item->date) {
                $oldDate = \Morilog\Jalali\Jalalian::fromCarbon($item->date)->format('Y/m/d');
            }
        @endphp
        <input type="text" name="date_jalali" required value="{{ $oldDate }}"
               placeholder="مثال: 1405/01/01"
               class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm cursor-pointer bg-white">
        <p class="text-[10px] text-gray-500 mt-1">فرمت: YYYY/MM/DD شمسی</p>
        @error('date_jalali')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        @error('date')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-5">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان <span class="text-rose-500">*</span></label>
        <input type="text" name="label" required maxlength="200" value="{{ old('label', $item->label ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"
               placeholder="مثلاً: نوروز">
        @error('label')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-3">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">نوع</label>
        <select name="type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            @foreach($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $item->type ?? 'custom') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-12">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات (اختیاری)</label>
        <textarea name="description" rows="2" maxlength="500"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ old('description', $item->description ?? '') }}</textarea>
    </div>

    <div class="md:col-span-12">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            <span class="text-sm">فعال</span>
        </label>
    </div>
</div>

<div class="mt-4 flex items-center gap-2">
    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
    <a href="{{ route('customer-app.holidays.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm">انصراف</a>
</div>
