@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام دستگاه <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $device->name ?? '') }}" required
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (در صورت خالی، خودکار از نام ساخته می‌شود)</label>
        <input type="text" name="slug" value="{{ old('slug', $device->slug ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
        @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کلید آیکن (Lucide)</label>
        <input type="text" name="icon" value="{{ old('icon', $device->icon ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr"
               placeholder="washing-machine">
        <p class="text-xs text-gray-500 mt-1">نام آیکن Lucide به‌صورت kebab-case (مثال: washing-machine, refrigerator, snowflake).</p>
        @error('icon')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تم رنگ (Tone)</label>
        <select name="tone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            <option value="">—</option>
            @foreach(['tone-blue','tone-green','tone-cyan','tone-sky','tone-orange','tone-amber','tone-rose','tone-violet','tone-emerald'] as $t)
                <option value="{{ $t }}" @selected(old('tone', $device->tone ?? '') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        @error('tone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $device->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('sort_order')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end gap-6">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $device->is_featured ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">ویژه (نمایش پیش‌فرض در Hero)</span>
        </label>
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
