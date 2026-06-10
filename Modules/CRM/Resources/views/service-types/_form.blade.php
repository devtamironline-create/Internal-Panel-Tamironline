@csrf
<div class="grid grid-cols-1 md:grid-cols-12 gap-4">
    <div class="md:col-span-7">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان <span class="text-rose-500">*</span></label>
        <input type="text" name="name" required maxlength="100" value="{{ old('name', $item->name ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-3">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">slug</label>
        <input type="text" name="slug" maxlength="60" value="{{ old('slug', $item->slug ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr"
               placeholder="auto از روی نام">
        @error('slug')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
    </div>

    <div class="md:col-span-4">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">آیکن (lucide-key)</label>
        <input type="text" name="icon" maxlength="60" value="{{ old('icon', $item->icon ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr"
               placeholder="wrench, plug, settings-2">
    </div>
    <div class="md:col-span-8">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
        <textarea name="description" rows="2" maxlength="1000"
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
    <a href="{{ route('crm.service-types.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm">انصراف</a>
</div>
