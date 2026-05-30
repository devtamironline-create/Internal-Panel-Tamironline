@csrf
@php $b = $banner ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div x-data="bannerMediaPicker({
        pickerUrl: '{{ route('site.admin.media.picker') }}',
        initialDesktop: @js(($b && $b->media) ? $b->media->toApiArray() : null),
        initialMobile:  @js(($b && $b->mediaMobile) ? $b->mediaMobile->toApiArray() : null),
        existingImageUrl: @js($b->image_url ?? null),
     })" class="space-y-6">

    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">زون نمایش <span class="text-red-500">*</span></label>
                <select name="zone_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">— انتخاب کنید —</option>
                    @foreach($zones as $z)
                        <option value="{{ $z->id }}" @selected(old('zone_id', $b->zone_id ?? null) == $z->id)>
                            {{ $z->name }} ({{ $z->slug }})
                            @if($z->recommended_width) — {{ $z->recommended_width }}×{{ $z->recommended_height }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $b?->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">عنوان <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $b?->title) }}" required maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">زیرتیتر</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $b?->subtitle) }}" maxlength="300"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
    </div>

    {{-- Images --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
        <h3 class="text-sm font-bold">تصاویر بنر</h3>

        <div>
            <label class="block text-sm font-medium mb-1">تصویر دسکتاپ</label>
            <input type="hidden" name="media_id" :value="desktop.id ?? ''">
            <div class="flex items-start gap-3">
                <template x-if="desktop.url">
                    <img :src="desktop.url" class="w-32 h-20 object-cover rounded border border-gray-200">
                </template>
                <template x-if="!desktop.url">
                    <div class="w-32 h-20 bg-gray-100 rounded border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">بدون تصویر</div>
                </template>
                <div class="flex flex-col gap-2">
                    <button type="button" @click="openPicker('desktop')" class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm">انتخاب از مخزن</button>
                    <button type="button" @click="clearSlot('desktop')" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">پاک‌کردن</button>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">یا URL مستقیم وارد کنید (اگر مخزن انتخاب شود اولویت دارد):</p>
            <input type="url" name="image_url" x-model="manualUrl" maxlength="500" dir="ltr"
                   class="w-full mt-1 px-3 py-2 border border-gray-300 rounded text-sm font-mono ltr"
                   placeholder="https://...">
        </div>

        <div class="pt-3 border-t border-gray-100">
            <label class="block text-sm font-medium mb-1">تصویر موبایل (اختیاری)</label>
            <input type="hidden" name="media_id_mobile" :value="mobile.id ?? ''">
            <div class="flex items-start gap-3">
                <template x-if="mobile.url">
                    <img :src="mobile.url" class="w-20 h-28 object-cover rounded border border-gray-200">
                </template>
                <template x-if="!mobile.url">
                    <div class="w-20 h-28 bg-gray-100 rounded border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">بدون</div>
                </template>
                <div class="flex flex-col gap-2">
                    <button type="button" @click="openPicker('mobile')" class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm">انتخاب از مخزن</button>
                    <button type="button" @click="clearSlot('mobile')" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">پاک‌کردن</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Link + schedule --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">URL هدف</label>
            <input type="text" name="link_url" value="{{ old('link_url', $b?->link_url) }}" maxlength="500" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">متن دکمه</label>
            <input type="text" name="link_label" value="{{ old('link_label', $b?->link_label) }}" maxlength="80"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">شروع نمایش</label>
            <input type="datetime-local" name="starts_at"
                   value="{{ old('starts_at', $b?->starts_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">پایان نمایش</label>
            <input type="datetime-local" name="ends_at"
                   value="{{ old('ends_at', $b?->ends_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div class="sm:col-span-2 flex items-center">
            <label class="inline-flex items-center gap-2 p-3 rounded bg-emerald-50 border border-emerald-200 cursor-pointer w-full">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $b?->is_published ?? true))>
                <span class="text-sm font-medium">منتشر شود (پس از ذخیره در ≤۱ ثانیه در فرانت ظاهر می‌شود)</span>
            </label>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium">ذخیره بنر</button>
        <a href="{{ route('site.admin.banners.index') }}" class="px-5 py-2.5 bg-gray-100 rounded-lg text-sm">انصراف</a>
    </div>

    {{-- Picker Modal --}}
    <div x-show="pickerOpen" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="pickerOpen = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold">انتخاب از مخزن مدیا</h3>
                <button type="button" @click="pickerOpen = false" class="text-gray-500">✕</button>
            </div>
            <div class="p-3 border-b border-gray-100">
                <input type="text" x-model.debounce.200ms="search" placeholder="جستجو..." class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
            </div>
            <div class="overflow-y-auto p-3 flex-1">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    <template x-for="m in pickerItems" :key="m.id">
                        <button type="button" @click="pickItem(m)" class="text-right">
                            <img :src="m.variants?.thumb?.url ?? m.url" :alt="m.alt ?? ''"
                                 class="aspect-square w-full object-cover rounded border border-gray-200 hover:border-purple-500 hover:shadow">
                            <div class="text-[10px] text-gray-500 truncate mt-1" x-text="m.title || ''"></div>
                        </button>
                    </template>
                </div>
                <div x-show="pickerLoading" class="text-center py-4 text-gray-400 text-sm">در حال بارگذاری...</div>
            </div>
            <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-between text-sm text-gray-500">
                <span>صفحه <span x-text="pickerPage"></span> / <span x-text="pickerLastPage"></span></span>
                <div class="flex gap-2">
                    <button type="button" @click="pickerLoadPage(pickerPage - 1)" :disabled="pickerPage <= 1" class="px-3 py-1 border rounded disabled:opacity-50">قبلی</button>
                    <button type="button" @click="pickerLoadPage(pickerPage + 1)" :disabled="pickerPage >= pickerLastPage" class="px-3 py-1 border rounded disabled:opacity-50">بعدی</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function bannerMediaPicker(config) {
    return {
        desktop: config.initialDesktop ?? { id: null, url: null },
        mobile:  config.initialMobile  ?? { id: null, url: null },
        manualUrl: config.existingImageUrl ?? '',
        pickerOpen: false, pickerTarget: null,
        pickerItems: [], pickerPage: 1, pickerLastPage: 1, pickerLoading: false,
        search: '',
        init() {
            this.$watch('search', () => this.pickerLoadPage(1));
        },
        openPicker(target) {
            this.pickerTarget = target;
            this.pickerOpen = true;
            this.pickerLoadPage(1);
        },
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
            if (this.pickerTarget === 'desktop') {
                this.desktop = { id: m.id, url: m.url };
                this.manualUrl = m.url;
            } else {
                this.mobile = { id: m.id, url: m.url };
            }
            this.pickerOpen = false;
        },
        clearSlot(slot) {
            if (slot === 'desktop') { this.desktop = { id: null, url: null }; this.manualUrl = ''; }
            else { this.mobile = { id: null, url: null }; }
        }
    };
}
</script>
