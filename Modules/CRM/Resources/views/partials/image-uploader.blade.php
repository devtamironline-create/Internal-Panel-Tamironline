{{--
    Reusable image uploader for CRM admin forms.

    Required:
      $name        — base field name (string)               — used for URL input
      $fileName    — file input name (string)               — e.g. "{$name}_file"
      $label       — visible label
    Optional:
      $value       — current stored URL/path (string|null)
      $placeholder — URL placeholder text
      $help        — small help text under the field

    The parent form MUST set enctype="multipart/form-data".
--}}
@php
    $value       = $value       ?? null;
    $placeholder = $placeholder ?? 'https://example.com/image.png';
    $help        = $help        ?? 'تصویر را آپلود کنید یا آدرس URL را جایگذاری کنید (PNG/JPG/SVG/WebP، حداکثر ۲MB).';
    $alpineId    = 'uploader_' . preg_replace('/[^a-z0-9]/i', '_', $name);
    $currentSrc  = $value ? (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://', '/']) ? $value : asset('storage/' . ltrim($value, '/'))) : null;
@endphp

<div
    x-data="{
        previewUrl: @js($currentSrc),
        urlInput: @js($value ?? ''),
        cleared: false,
        onFile(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            this.previewUrl = URL.createObjectURL(f);
            this.cleared = false;
        },
        onUrl() {
            const v = (this.urlInput || '').trim();
            if (v) { this.previewUrl = v; this.cleared = false; }
        },
        clearAll() {
            this.previewUrl = null;
            this.urlInput = '';
            this.cleared = true;
            const fi = document.getElementById('{{ $alpineId }}_file');
            if (fi) fi.value = '';
        },
    }"
    class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50/40 dark:bg-gray-800/40"
>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">{{ $label }}</label>

    {{-- Preview --}}
    <div class="mb-3 flex items-center gap-3">
        <div class="w-24 h-24 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center bg-white dark:bg-gray-700 overflow-hidden">
            <template x-if="previewUrl">
                <img :src="previewUrl" class="w-full h-full object-contain" alt="preview"
                     @@error="$el.style.opacity=0.3" @@load="$el.style.opacity=1">
            </template>
            <template x-if="!previewUrl">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </template>
        </div>

        <div class="flex flex-col gap-2 flex-1">
            {{-- File picker button --}}
            <label for="{{ $alpineId }}_file" class="cursor-pointer inline-flex items-center justify-center gap-2 px-3 py-2 bg-brand-600 text-white text-sm rounded hover:bg-brand-700 transition w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                انتخاب فایل
            </label>
            <input
                type="file"
                id="{{ $alpineId }}_file"
                name="{{ $fileName }}"
                accept="image/*"
                @change="onFile($event)"
                class="hidden"
            >

            <button type="button" @click="clearAll()" x-show="previewUrl"
                    class="text-xs text-red-600 hover:underline w-fit" x-cloak>
                حذف تصویر
            </button>
        </div>
    </div>

    {{-- URL input --}}
    <div>
        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">یا آدرس URL</label>
        <input
            type="text"
            name="{{ $name }}"
            x-model="urlInput"
            @input.debounce.300ms="onUrl()"
            dir="ltr"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr focus:ring-2 focus:ring-brand-500"
            placeholder="{{ $placeholder }}"
        >
    </div>

    {{-- Cleared marker for backend (so we know admin asked to remove) --}}
    <input type="hidden" name="{{ $name }}_cleared" :value="cleared ? '1' : '0'">

    @if($help)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $help }}</p>
    @endif

    @error($name)<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    @error($fileName)<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>
