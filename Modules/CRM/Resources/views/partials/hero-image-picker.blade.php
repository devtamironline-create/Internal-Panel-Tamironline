{{--
    Hero image picker — دسکتاپ + موبایل با URL و alt مجزا.
    داده‌ی ذخیره‌شده در DB به شکل {desktop:{url,alt}, mobile:{url,alt}}.

    Required vars:
      $name           — اسم فیلد (معمولاً 'hero_image')
      $current        — مقدار فعلی (array | null) — از $entity->hero_image
      $entityKind     — 'برند' یا 'دستگاه' (برای متن fallback)
      $templateRoute  — لینک به الگوی template برای edit fallback
--}}
@php
    $current = $current ?? [];
    $desktopUrl = $current['desktop']['url'] ?? '';
    $desktopAlt = $current['desktop']['alt'] ?? '';
    $mobileUrl  = $current['mobile']['url']  ?? '';
    $mobileAlt  = $current['mobile']['alt']  ?? '';
    $compId = 'heroimg_' . substr(md5($name . uniqid()), 0, 6);
@endphp

<div x-data="{{ $compId }}({
        pickerUrl: '{{ route('site.admin.media.picker') }}',
        uploadUrl: '{{ route('site.admin.media.upload') }}',
        csrfToken: '{{ csrf_token() }}',
        initial: {
            desktop: { url: @js($desktopUrl), alt: @js($desktopAlt) },
            mobile:  { url: @js($mobileUrl),  alt: @js($mobileAlt) },
        },
     })"
     class="space-y-4">

    <p class="text-xs text-gray-500">
        اگر این دو slot را پر کنید، تصویر Hero این {{ $entityKind }} override می‌شود.
        خالی بگذارید تا از <a href="{{ $templateRoute }}" target="_blank" class="text-blue-600 hover:underline">الگوی template</a> استفاده شود.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Desktop slot --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3 bg-gray-50/40 dark:bg-gray-900/30">
            <div class="flex items-center justify-between">
                <h5 class="text-xs font-bold">دسکتاپ</h5>
                <span class="text-[10px] text-gray-500">≈ ۱۶:۹ یا ۱۲۰۰×۶۰۰</span>
            </div>

            <input type="hidden" name="{{ $name }}[desktop][url]" :value="slots.desktop.url ?? ''" />

            <div class="relative w-full bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6),linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6)] [background-size:16px_16px] [background-position:0_0,8px_8px] rounded-lg border border-gray-200 overflow-hidden flex items-center justify-center"
                 style="aspect-ratio: 16/9;">
                <template x-if="slots.desktop.url">
                    <img :src="slots.desktop.url" class="max-w-full max-h-full object-contain">
                </template>
                <template x-if="!slots.desktop.url">
                    <span class="text-xs text-gray-400">بدون تصویر</span>
                </template>
                <div x-show="uploading.desktop" x-cloak
                     class="absolute inset-0 bg-white/80 flex items-center justify-center text-xs text-purple-700 font-bold">
                    آپلود...
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openPicker('desktop')"
                        class="px-2 py-1 bg-purple-600 text-white rounded text-xs">انتخاب از مخزن</button>
                <button type="button" @click="$refs.uploadDesktop.click()" :disabled="uploading.desktop"
                        class="px-2 py-1 bg-emerald-600 text-white rounded text-xs disabled:opacity-50">آپلود</button>
                <button type="button" @click="clearSlot('desktop')" x-show="slots.desktop.url"
                        class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-xs">پاک‌کردن</button>
                <input type="file" x-ref="uploadDesktop" accept="image/*" class="hidden"
                       @change="uploadFile('desktop', $event)">
            </div>

            <div>
                <label class="block text-[11px] text-gray-600 dark:text-gray-300 mb-1">متن جایگزین (alt) — دسکتاپ</label>
                <input type="text" name="{{ $name }}[desktop][alt]" x-model="slots.desktop.alt"
                       maxlength="200"
                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs"
                       placeholder="متن جایگزین برای نسخه‌ی دسکتاپ">
            </div>
        </div>

        {{-- Mobile slot --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3 bg-gray-50/40 dark:bg-gray-900/30">
            <div class="flex items-center justify-between">
                <h5 class="text-xs font-bold">موبایل</h5>
                <span class="text-[10px] text-gray-500">عمودی یا مربع، ≈ ۷۵۰×۹۰۰</span>
            </div>

            <input type="hidden" name="{{ $name }}[mobile][url]" :value="slots.mobile.url ?? ''" />

            <div class="relative w-full bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6),linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6)] [background-size:16px_16px] [background-position:0_0,8px_8px] rounded-lg border border-gray-200 overflow-hidden flex items-center justify-center mx-auto"
                 style="aspect-ratio: 3/4; max-width: 180px;">
                <template x-if="slots.mobile.url">
                    <img :src="slots.mobile.url" class="max-w-full max-h-full object-contain">
                </template>
                <template x-if="!slots.mobile.url">
                    <span class="text-xs text-gray-400">بدون تصویر</span>
                </template>
                <div x-show="uploading.mobile" x-cloak
                     class="absolute inset-0 bg-white/80 flex items-center justify-center text-xs text-purple-700 font-bold">
                    آپلود...
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openPicker('mobile')"
                        class="px-2 py-1 bg-purple-600 text-white rounded text-xs">انتخاب از مخزن</button>
                <button type="button" @click="$refs.uploadMobile.click()" :disabled="uploading.mobile"
                        class="px-2 py-1 bg-emerald-600 text-white rounded text-xs disabled:opacity-50">آپلود</button>
                <button type="button" @click="clearSlot('mobile')" x-show="slots.mobile.url"
                        class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-xs">پاک‌کردن</button>
                <input type="file" x-ref="uploadMobile" accept="image/*" class="hidden"
                       @change="uploadFile('mobile', $event)">
            </div>

            <div>
                <label class="block text-[11px] text-gray-600 dark:text-gray-300 mb-1">متن جایگزین (alt) — موبایل</label>
                <input type="text" name="{{ $name }}[mobile][alt]" x-model="slots.mobile.alt"
                       maxlength="200"
                       class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs"
                       placeholder="متن جایگزین برای نسخه‌ی موبایل">
            </div>
        </div>
    </div>

    {{-- Picker Modal --}}
    <div x-show="pickerOpen" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="pickerOpen = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-bold">انتخاب از مخزن مدیا</h3>
                <button type="button" @click="pickerOpen = false" class="text-gray-500">✕</button>
            </div>
            <div class="p-3 border-b border-gray-100 dark:border-gray-700">
                <input type="text" x-model.debounce.200ms="search" placeholder="جستجو..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
            </div>
            <div class="overflow-y-auto p-3 flex-1">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    <template x-for="m in pickerItems" :key="m.id">
                        <button type="button" @click="pickItem(m)" class="text-right">
                            <img :src="m.variants?.thumb?.url ?? m.url" :alt="m.alt ?? ''"
                                 class="aspect-square w-full object-cover rounded border border-gray-200 dark:border-gray-600 hover:border-purple-500">
                            <div class="text-[10px] text-gray-500 truncate mt-1" x-text="m.title || ''"></div>
                        </button>
                    </template>
                </div>
                <div x-show="pickerLoading" class="text-center py-4 text-gray-400 text-sm">در حال بارگذاری...</div>
            </div>
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                <span>صفحه <span x-text="pickerPage"></span> / <span x-text="pickerLastPage"></span></span>
                <div class="flex gap-2">
                    <button type="button" @click="pickerLoadPage(pickerPage - 1)" :disabled="pickerPage <= 1"
                            class="px-3 py-1 border rounded disabled:opacity-50">قبلی</button>
                    <button type="button" @click="pickerLoadPage(pickerPage + 1)" :disabled="pickerPage >= pickerLastPage"
                            class="px-3 py-1 border rounded disabled:opacity-50">بعدی</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function {{ $compId }}(config) {
    return {
        slots: { desktop: config.initial.desktop, mobile: config.initial.mobile },
        uploading: { desktop: false, mobile: false },
        pickerOpen: false, pickerTarget: null,
        pickerItems: [], pickerPage: 1, pickerLastPage: 1, pickerLoading: false,
        search: '',
        init() { this.$watch('search', () => this.pickerLoadPage(1)); },
        openPicker(target) { this.pickerTarget = target; this.pickerOpen = true; this.pickerLoadPage(1); },
        async pickerLoadPage(page) {
            if (page < 1) return;
            this.pickerLoading = true;
            const params = new URLSearchParams({ page: String(page), kind: 'image' });
            if (this.search) params.set('q', this.search);
            try {
                const res = await fetch(config.pickerUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                this.pickerItems = json.data;
                this.pickerPage = json.meta.page;
                this.pickerLastPage = json.meta.last_page;
            } finally { this.pickerLoading = false; }
        },
        pickItem(m) {
            this.slots[this.pickerTarget].url = m.url;
            if (!this.slots[this.pickerTarget].alt) this.slots[this.pickerTarget].alt = m.alt || m.title || '';
            this.pickerOpen = false;
        },
        clearSlot(slot) { this.slots[slot].url = ''; },
        async uploadFile(slot, event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.uploading[slot] = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('visibility', 'public');
                const res = await fetch(config.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': config.csrfToken, 'Accept': 'application/json' },
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok || !json.ok || !json.media) {
                    alert(json?.message || 'آپلود ناموفق بود');
                    return;
                }
                this.slots[slot].url = json.media.url;
                if (!this.slots[slot].alt) this.slots[slot].alt = json.media.alt || json.media.title || '';
            } catch (err) {
                alert('خطای آپلود: ' + (err?.message || err));
            } finally {
                this.uploading[slot] = false;
                event.target.value = '';
            }
        },
    };
}
</script>
