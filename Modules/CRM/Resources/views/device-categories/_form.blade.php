@csrf
@php $c = $category ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">نام دسته <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $c?->name) }}" required maxlength="120"
               placeholder="لوازم آشپزخانه"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Slug (خالی = خودکار)</label>
        <input type="text" name="slug" value="{{ old('slug', $c?->slug) }}" maxlength="120" dir="ltr"
               placeholder="kitchen-appliances"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr focus:ring-2 focus:ring-indigo-500">
        <p class="text-xs text-gray-500 mt-1">باید English kebab-case باشد.</p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">آیکن (Lucide)</label>
        <input type="text" name="icon" value="{{ old('icon', $c?->icon) }}" maxlength="60" dir="ltr"
               placeholder="utensils-crossed"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">تم رنگ (Tone)</label>
        <select name="tone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            <option value="">—</option>
            @foreach(['tone-blue','tone-green','tone-cyan','tone-sky','tone-orange','tone-amber','tone-rose','tone-violet','tone-emerald'] as $t)
                <option value="{{ $t }}" @selected(old('tone', $c?->tone) === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">توضیح کوتاه</label>
        <textarea name="description" rows="2" maxlength="2000"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $c?->description) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $c?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
    </div>
    <div class="flex items-end pb-1">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $c?->is_active ?? true))
                   class="w-4 h-4 text-indigo-600 rounded">
            <span class="text-sm">فعال</span>
        </label>
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">ذخیره</button>
    <a href="{{ route('crm.device-categories.index') }}" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
</div>
