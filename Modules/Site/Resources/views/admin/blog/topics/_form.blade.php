@csrf
@php $t = $topic ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">نام تاپیک <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $t?->name) }}" required maxlength="120"
               placeholder="عیب‌یابی"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-500">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Slug (خالی = خودکار)</label>
        <input type="text" name="slug" value="{{ old('slug', $t?->slug) }}" maxlength="120" dir="ltr"
               placeholder="troubleshooting"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">آیکن (Lucide)</label>
        <input type="text" name="icon" value="{{ old('icon', $t?->icon) }}" maxlength="60" dir="ltr"
               placeholder="stethoscope"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $t?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه</label>
        <input type="text" name="color_bg" value="{{ old('color_bg', $t?->color_bg) }}" maxlength="9" dir="ltr"
               placeholder="#f5f3ff"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">رنگ متن</label>
        <input type="text" name="color_fg" value="{{ old('color_fg', $t?->color_fg) }}" maxlength="9" dir="ltr"
               placeholder="#6d28d9"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">رنگ بوردر</label>
        <input type="text" name="color_border" value="{{ old('color_border', $t?->color_border) }}" maxlength="9" dir="ltr"
               placeholder="#ddd6fe"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div class="flex items-end pb-1">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $t?->is_active ?? true))
                   class="w-4 h-4 text-rose-600 rounded">
            <span class="text-sm">فعال</span>
        </label>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">توضیح (اختیاری)</label>
        <textarea name="description" rows="2" maxlength="1000"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('description', $t?->description) }}</textarea>
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <button type="submit" class="px-5 py-2.5 bg-rose-600 text-white rounded-lg text-sm font-medium hover:bg-rose-700">ذخیره</button>
    <a href="{{ route('site.admin.blog.topics.index') }}" class="px-5 py-2.5 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
</div>
