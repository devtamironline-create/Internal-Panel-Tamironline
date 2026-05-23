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

    @php
        $allDevices = \Modules\CRM\Models\Device::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $selectedDeviceIds = old(
            'device_ids',
            isset($brand) ? $brand->devices()->pluck('crm_devices.id')->all() : []
        );
        $selectedDeviceIds = array_map('intval', (array) $selectedDeviceIds);
    @endphp
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            دستگاه‌های قابل پشتیبانی توسط این برند
        </label>
        <p class="text-xs text-gray-500 mb-2">با Ctrl/Cmd چند مورد را انتخاب کنید. برای فیلتر برندها بر اساس دستگاه در سایت استفاده می‌شود.</p>
        <select name="device_ids[]" multiple size="6"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            @foreach ($allDevices as $d)
                <option value="{{ $d->id }}" @selected(in_array((int) $d->id, $selectedDeviceIds, true))>
                    {{ $d->name }} ({{ $d->slug }})
                </option>
            @endforeach
        </select>
    </div>
</div>

{{-- ───────────────────────── CMS Override Fields ───────────────────────── --}}
<div class="mt-8 space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">محتوای CMS — صفحه‌ی detail برند</h3>
        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
            هر فیلدی که خالی بگذارید، فرانت از <strong>fixture پیش‌فرض</strong> خود استفاده می‌کند. فقط آنچه می‌خواهید override کنید پر کنید.
        </p>
    </div>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg" open>
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">معرفی و رنگ‌ها</summary>
        <div class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">شعار / Tagline (یک خط)</label>
                <input type="text" name="tagline" value="{{ old('tagline', $brand->tagline ?? '') }}" maxlength="1000"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">توضیح کامل برند</label>
                <textarea name="description" rows="8" maxlength="10000"
                          class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $brand->description ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">می‌توانید از قالب‌بندی غنی (تیتر، فهرست، لینک، تصویر) استفاده کنید. خروجی به‌صورت HTML در فرانت render می‌شود.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">رنگ accent (hex)</label>
                    <input type="text" name="tone" value="{{ old('tone', $brand->tone ?? '') }}" placeholder="#1428A0" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                    @error('tone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه (hex)</label>
                    <input type="text" name="bg" value="{{ old('bg', $brand->bg ?? '') }}" placeholder="#EEF2FF" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                    @error('bg')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">آمار (stats)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'stats',
                'label' => 'کارت‌های آمار',
                'help'  => 'مثلاً «۱۲+ سال تجربه»، «۵۰۰۰+ تعمیر موفق».',
                'items' => old('stats', $brand->stats ?? []),
                'item_fields' => [
                    'value' => ['label' => 'مقدار', 'type' => 'string'],
                    'label' => ['label' => 'برچسب', 'type' => 'string'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">مشکلات رایج (issues)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'issues',
                'label' => 'مشکلات و راه‌حل‌های مرتبط با این برند',
                'help'  => 'مثلاً «خاموش شدن ناگهانی»، «صدای غیرعادی موتور».',
                'items' => old('issues', $brand->issues ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان مشکل', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                    'icon'        => ['label' => 'آیکن Lucide (PascalCase: Zap, Wrench, ...)', 'type' => 'string'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">چرا ما (why_us)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'why_us',
                'label' => 'دلایل انتخاب ما برای این برند',
                'help'  => 'مثلاً «قطعات اصلی»، «تکنسین متخصص برند».',
                'items' => old('why_us', $brand->why_us ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                    'icon'        => ['label' => 'آیکن Lucide', 'type' => 'string'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">سوالات متداول (faq)</summary>
        <div class="p-4">
            @include('crm::partials.json-repeater', [
                'name'  => 'faq',
                'label' => 'سوالات متداول مخصوص این برند',
                'items' => old('faq', $brand->faq ?? []),
                'item_fields' => [
                    'question' => ['label' => 'سوال', 'type' => 'string'],
                    'answer'   => ['label' => 'پاسخ', 'type' => 'textarea'],
                ],
            ])
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">گارانتی و پشتیبانی</summary>
        <div class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">متن گارانتی</label>
                <textarea name="warranty_text" rows="3" maxlength="3000"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('warranty_text', $brand->warranty_text ?? '') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">اطلاعات پشتیبانی</label>
                <textarea name="support_info" rows="3" maxlength="3000"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('support_info', $brand->support_info ?? '') }}</textarea>
            </div>
        </div>
    </details>

    <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-semibold bg-gray-50 dark:bg-gray-800">سئو (SEO)</summary>
        <div class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $brand->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $brand->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </details>
</div>

<div class="flex items-center gap-3 mt-6 sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-3">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.brands.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
