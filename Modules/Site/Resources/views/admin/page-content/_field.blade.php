{{--
    Render a single field of a section.
    Variables expected: $fieldKey, $field (schema def), $value (current value), $name (input name)
--}}
@php
    $type = $field['type'] ?? 'string';
    $label = $field['label'] ?? $fieldKey;
@endphp

@if($type === 'textarea')
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <textarea name="{{ $name }}" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded text-sm">{{ old($name, $value ?? '') }}</textarea>
    </div>

@elseif($type === 'url')
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <input type="url" name="{{ $name }}" value="{{ old($name, $value ?? '') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
    </div>

@elseif($type === 'image_url')
    <div x-data="{ url: @js(old($name, $value ?? '')) }">
        <label class="block text-sm mb-1">{{ $label }}</label>
        <input type="url" name="{{ $name }}" x-model="url" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr"
               placeholder="https://...">
        <template x-if="url">
            <div class="mt-2">
                <img :src="url" class="h-24 rounded border border-gray-200" alt="preview"
                     @@error="$el.style.display='none'" @@load="$el.style.display='block'">
            </div>
        </template>
    </div>

@elseif($type === 'responsive_image')
    @php
        // پشتیبانی از هر دو شکل قدیمی و جدید — همیشه به شکل {url, alt} نرمالایز
        $normalized = \Modules\Site\Services\PageSectionService::normalizeResponsiveImage($value);
        $desktopUrl = $normalized['desktop']['url'];
        $desktopAlt = $normalized['desktop']['alt'];
        $mobileUrl = $normalized['mobile']['url'];
        $mobileAlt = $normalized['mobile']['alt'];
    @endphp
    <div class="border border-gray-200 rounded p-3">
        <label class="block text-sm font-semibold mb-2">{{ $label }}</label>
        <p class="text-xs text-gray-500 mb-3">
            برای هر اسلات می‌توانید متن جایگزین (alt) متفاوت تنظیم کنید — حتی اگر URL یکسان باشد.
            این برای SEO و دسترسی‌پذیری مفید است (مثلاً نسخه‌ی موبایل یک crop متفاوت دارد).
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Desktop slot --}}
            <div class="space-y-2 border border-gray-100 rounded p-3 bg-gray-50/40"
                 x-data="{ url: @js(old($name . '.desktop.url', $desktopUrl)) }">
                <label class="block text-xs font-semibold">دسکتاپ</label>
                <input type="url" name="{{ $name }}[desktop][url]" x-model="url" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr bg-white"
                       placeholder="https://...">
                <input type="text" name="{{ $name }}[desktop][alt]"
                       value="{{ old($name . '.desktop.alt', $desktopAlt) }}"
                       maxlength="200"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm bg-white"
                       placeholder="متن جایگزین برای نسخه‌ی دسکتاپ">
                <template x-if="url">
                    <div class="mt-1">
                        <img :src="url" class="h-24 w-full object-contain rounded border border-gray-200 bg-gray-50" alt="desktop preview"
                             @@error="$el.style.display='none'" @@load="$el.style.display='block'">
                    </div>
                </template>
            </div>
            {{-- Mobile slot --}}
            <div class="space-y-2 border border-gray-100 rounded p-3 bg-gray-50/40"
                 x-data="{ url: @js(old($name . '.mobile.url', $mobileUrl)) }">
                <label class="block text-xs font-semibold">موبایل</label>
                <input type="url" name="{{ $name }}[mobile][url]" x-model="url" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr bg-white"
                       placeholder="https://...">
                <input type="text" name="{{ $name }}[mobile][alt]"
                       value="{{ old($name . '.mobile.alt', $mobileAlt) }}"
                       maxlength="200"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm bg-white"
                       placeholder="متن جایگزین برای نسخه‌ی موبایل">
                <template x-if="url">
                    <div class="mt-1">
                        <img :src="url" class="h-24 w-auto rounded border border-gray-200 bg-gray-50" alt="mobile preview"
                             @@error="$el.style.display='none'" @@load="$el.style.display='block'">
                    </div>
                </template>
            </div>
        </div>
    </div>

@elseif($type === 'int')
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <input type="number" name="{{ $name }}" value="{{ old($name, $value ?? '') }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

@elseif($type === 'bool')
    <div>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $value ?? false))>
            <span class="text-sm">{{ $label }}</span>
        </label>
    </div>

@elseif($type === 'select')
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <select name="{{ $name }}" class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
            @foreach(($field['options'] ?? []) as $optVal => $optLabel)
            <option value="{{ $optVal }}" @selected(old($name, $value ?? '') === $optVal)>{{ $optLabel }}</option>
            @endforeach
        </select>
    </div>

@else
    {{-- string (default) --}}
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <input type="text" name="{{ $name }}" value="{{ old($name, $value ?? '') }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>
@endif
