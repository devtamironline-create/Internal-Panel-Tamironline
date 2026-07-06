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

@elseif($type === 'richtext')
    {{-- ادیتور TinyMCE — کلاس rich-editor در layouts.admin به‌صورت سراسری init می‌شود --}}
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <textarea name="{{ $name }}" rows="12" class="rich-editor w-full px-3 py-2 border border-gray-200 rounded text-sm">{{ old($name, $value ?? '') }}</textarea>
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

@elseif($type === 'hero_visual')
    @php
        $normalized = \Modules\Site\Services\PageSectionService::normalizeHeroVisual($value);
        $desktopLeftUrl = $normalized['desktop_left']['url'];
        $desktopLeftAlt = $normalized['desktop_left']['alt'];
        $desktopRightUrl = $normalized['desktop_right']['url'];
        $desktopRightAlt = $normalized['desktop_right']['alt'];
        $mobileUrl = $normalized['mobile']['url'];
        $mobileAlt = $normalized['mobile']['alt'];
    @endphp
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
        <label class="block text-sm font-semibold mb-3">{{ $label }}</label>
        @include('site::admin.partials.hero-visual-picker', [
            'name' => $name,
            'desktopLeftUrl' => $desktopLeftUrl,
            'desktopLeftAlt' => $desktopLeftAlt,
            'desktopRightUrl' => $desktopRightUrl,
            'desktopRightAlt' => $desktopRightAlt,
            'mobileUrl' => $mobileUrl,
            'mobileAlt' => $mobileAlt,
        ])
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
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
        <label class="block text-sm font-semibold mb-3">{{ $label }}</label>
        @include('site::admin.partials.responsive-image-picker', [
            'name' => $name,
            'desktopUrl' => $desktopUrl,
            'desktopAlt' => $desktopAlt,
            'mobileUrl' => $mobileUrl,
            'mobileAlt' => $mobileAlt,
        ])
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

@elseif($type === 'string_list')
    @php $listValue = is_array($value) ? implode("\n", $value) : (is_string($value) ? $value : ''); @endphp
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <textarea name="{{ $name }}" rows="5" placeholder="هر مورد را در یک خط بنویسید"
                  class="w-full px-3 py-2 border border-gray-200 rounded text-sm">{{ old($name, $listValue) }}</textarea>
        <p class="text-xs text-gray-400 mt-1">هر خط = یک آیتم مستقل (خروجی به‌صورت لیست رشته‌ای).</p>
    </div>

@else
    {{-- string (default) --}}
    <div>
        <label class="block text-sm mb-1">{{ $label }}</label>
        <input type="text" name="{{ $name }}" value="{{ old($name, $value ?? '') }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>
@endif
