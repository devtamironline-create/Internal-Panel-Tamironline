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

    <div class="md:col-span-2">
        @include('crm::partials.image-uploader', [
            'name'        => 'thumbnail',
            'fileName'    => 'thumbnail_file',
            'label'       => 'تصویر بندانگشتی دستگاه',
            'value'       => old('thumbnail', $device->thumbnail ?? null),
            'placeholder' => 'https://cdn.example.com/devices/washing-machine.png',
            'help'        => 'یک تصویر کوچک از دستگاه (در کنار آیکن استفاده می‌شود). ابعاد پیشنهادی ۳۰۰×۳۰۰ پیکسل.',
        ])
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

{{-- ───────────────────────── CMS Override Fields ───────────────────────── --}}
<div class="mt-8 space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">محتوای CMS — صفحه‌ی detail دستگاه</h3>
        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
            هر فیلدی که خالی بگذارید، فرانت از <strong>fixture پیش‌فرض</strong> خود استفاده می‌کند.
        </p>
    </div>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg" open>
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">نام‌ها و توضیح</summary>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">نام کوتاه (short_name)</label>
                    <input type="text" name="short_name" value="{{ old('short_name', $device->short_name ?? '') }}" maxlength="80"
                           placeholder="لباسشویی"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">نام سرویس (service_name)</label>
                    <input type="text" name="service_name" value="{{ old('service_name', $device->service_name ?? '') }}" maxlength="160"
                           placeholder="تعمیر لباسشویی"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">عنوان تکنسین</label>
                    <input type="text" name="technician_name" value="{{ old('technician_name', $device->technician_name ?? '') }}" maxlength="160"
                           placeholder="تکنسین لباسشویی"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">توضیح کامل</label>
                    <textarea name="description" rows="4" maxlength="10000"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $device->description ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">قیمت و رنگ‌ها</summary>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">قیمت شروع (ریال)</label>
                <input type="number" name="starting_price" value="{{ old('starting_price', $device->starting_price ?? '') }}" min="0"
                       placeholder="1500000"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ accent (hex)</label>
                <input type="text" name="accent" value="{{ old('accent', $device->accent ?? '') }}" placeholder="#3B82F6" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('accent')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه (hex)</label>
                <input type="text" name="bg" value="{{ old('bg', $device->bg ?? '') }}" placeholder="#EFF6FF" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('bg')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">مشکلات رایج (issues)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'issues',
                'label' => 'مشکلات رایج این دستگاه',
                'help'  => 'مثلاً «آب‌نبستن درب»، «صدای غیرعادی».',
                'items' => old('issues', $device->issues ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان مشکل', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">سوالات متداول (faq)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'faq',
                'label' => 'سوالات متداول مخصوص این دستگاه',
                'items' => old('faq', $device->faq ?? []),
                'item_fields' => [
                    'question' => ['label' => 'سوال', 'type' => 'string'],
                    'answer'   => ['label' => 'پاسخ', 'type' => 'textarea'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">سئو (SEO)</summary>
        <div class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $device->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $device->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </details>
</div>

<div class="flex items-center gap-3 mt-6 sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-3">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
