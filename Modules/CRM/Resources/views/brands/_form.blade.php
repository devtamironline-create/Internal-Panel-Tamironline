@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام برند <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $brand->name ?? '') }}" required
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (در صورت خالی، خودکار از نام ساخته می‌شود)</label>
        <input type="text" name="slug" value="{{ old('slug', $brand->slug ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
        @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        @include('crm::partials.image-uploader', [
            'name'        => 'logo',
            'fileName'    => 'logo_file',
            'label'       => 'لوگوی برند',
            'value'       => old('logo', $brand->logo ?? null),
            'placeholder' => 'https://cdn.example.com/brands/lg.png',
            'help'        => 'لوگو را آپلود کنید یا URL آن را وارد کنید. ابعاد پیشنهادی ۲۰۰×۲۰۰ پیکسل، فرمت PNG شفاف یا SVG.',
        ])
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $brand->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('sort_order')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end gap-6">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">برند ویژه (نمایش در صفحه‌ی اصلی سایت)</span>
        </label>
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.brands.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
