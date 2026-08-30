@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان <span class="text-red-500">*</span></label>
        <select name="province_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            <option value="">— انتخاب استان —</option>
            @foreach($provinces as $p)
            <option value="{{ $p->id }}" @selected(old('province_id', $city->province_id ?? null) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        @error('province_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام شهر <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $city->name ?? '') }}" required
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (انگلیسی — مثلاً <span dir="ltr">karaj</span>)</label>
        <input type="text" name="slug" value="{{ old('slug', $city->slug ?? '') }}" placeholder="karaj"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
        <p class="text-xs text-gray-400 mt-1">در آدرسِ سئو استفاده می‌شود؛ باید انگلیسی باشد. اگر فارسی وارد شود یا خالی بماند، سیستم خودکار انگلیسی می‌سازد (کرج → karaj). با تغییرِ آن، مسیرِ صفحاتِ سئوی شهر هم به‌روزرسانی می‌شود.</p>
        @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $city->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('sort_order')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.cities.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
