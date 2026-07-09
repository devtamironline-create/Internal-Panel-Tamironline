@csrf
@php $t = $topic ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- ─── دستگاه و برندِ تاپیک (اختیاری، مثلِ صفحاتِ ترکیبی) ─── --}}
@php
    $selDevice = (int) old('device_id', $t?->device_id ?? 0);
    $selBrand = (int) old('brand_id', $t?->brand_id ?? 0);
@endphp
<div class="mb-5 p-4 rounded-xl border border-indigo-200 bg-indigo-50/60 dark:bg-indigo-900/20"
     x-data="{
        device: '{{ $selDevice ?: '' }}',
        brand: '{{ $selBrand ?: '' }}',
        devices: {{ Illuminate\Support\Js::from($allDevices->mapWithKeys(fn($d) => [$d->id => ['name' => $d->name, 'slug' => $d->slug]])) }},
        brands: {{ Illuminate\Support\Js::from($allBrands->mapWithKeys(fn($b) => [$b->id => ['name' => $b->name, 'slug' => $b->slug]])) }},
        get deviceName() { return this.device && this.devices[this.device] ? this.devices[this.device].name : ''; },
        get brandName() { return this.brand && this.brands[this.brand] ? this.brands[this.brand].name : ''; },
     }">
    <div class="flex items-center gap-2 mb-3">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
        <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-200">دستگاه و برندِ این تاپیک</h3>
    </div>
    <p class="text-xs text-indigo-700/80 dark:text-indigo-300 mb-3 leading-6">
        هر دو اختیاری‌اند. مثال: «خطاهای لباسشویی بوش» = دستگاه لباسشویی + برند بوش ·
        «خطاهای ظرفشویی» = فقط دستگاه · «بهترین دستگاه‌های بوش» = فقط برند.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">دستگاه</label>
            <select name="device_id" x-model="device"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700">
                <option value="">— بدون دستگاه —</option>
                @foreach($allDevices as $d)
                    <option value="{{ $d->id }}" @selected($selDevice === (int) $d->id)>{{ $d->name }} ({{ $d->slug }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">برند</label>
            <select name="brand_id" x-model="brand"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700">
                <option value="">— بدون برند —</option>
                @foreach($allBrands as $b)
                    <option value="{{ $b->id }}" @selected($selBrand === (int) $b->id)>{{ $b->name }} ({{ $b->slug }})</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- پیش‌نمایشِ بردکرامبِ فرانت — وقتی دستگاه هست، مسیر بر اساسِ دستگاه است --}}
    <div class="mt-3 text-xs bg-white dark:bg-gray-800 border border-indigo-100 rounded-lg px-3 py-2 text-gray-600 dark:text-gray-300">
        <span class="text-gray-400">پیش‌نمایشِ مسیر در سایت:</span>
        <span class="font-medium">خانه</span>
        <span class="text-gray-300">›</span>
        <span class="font-medium">مقالات</span>
        <template x-if="deviceName">
            <span><span class="text-gray-300">›</span> <span class="font-medium text-indigo-700" x-text="'مقالات ' + deviceName"></span></span>
        </template>
        <span class="text-gray-300">›</span>
        <span class="font-bold text-gray-800 dark:text-gray-100">{{ old('name', $t?->name) ?: 'نامِ تاپیک' }}</span>
    </div>
</div>

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
