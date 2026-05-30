@csrf
@php $b = $banner ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div x-data="bannerMediaPicker({
        pickerUrl: '{{ route('site.admin.media.picker') }}',
        uploadUrl: '{{ route('site.admin.media.upload') }}',
        csrfToken: '{{ csrf_token() }}',
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
    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-6">
        <h3 class="text-sm font-bold">تصاویر بنر</h3>

        {{-- Desktop slot --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium">تصویر دسکتاپ</label>
                <span class="text-xs text-gray-500">پیشنهادی: ۱۲۰۰×۳۰۰ یا متناسب با زون</span>
            </div>
            <input type="hidden" name="media_id" :value="desktop.id ?? ''">

            {{-- Preview --}}
            <div class="relative w-full bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6),linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6)] [background-size:16px_16px] [background-position:0_0,8px_8px] rounded-lg border border-gray-200 overflow-hidden flex items-center justify-center"
                 style="aspect-ratio: 4/1; max-height: 240px;">
                <template x-if="desktop.url">
                    <img :src="desktop.url" :alt="desktop.alt ?? ''" class="max-w-full max-h-full object-contain">
                </template>
                <template x-if="!desktop.url">
                    <div class="text-xs text-gray-400 py-10">پیش‌نمایش — تصویری انتخاب نشده</div>
                </template>
                <div x-show="uploading.desktop" x-cloak
                     class="absolute inset-0 bg-white/80 flex items-center justify-center text-sm text-purple-700 font-semibold">
                    در حال آپلود...
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-2">
                <button type="button" @click="openPicker('desktop')"
                        class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm">انتخاب از مخزن</button>
                <button type="button" @click="$refs.uploadDesktop.click()" :disabled="uploading.desktop"
                        class="px-3 py-1.5 bg-emerald-600 text-white rounded text-sm disabled:opacity-50">آپلود تصویر جدید</button>
                <button type="button" @click="clearSlot('desktop')" x-show="desktop.url"
                        class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">پاک‌کردن</button>
                <input type="file" x-ref="uploadDesktop" accept="image/*" class="hidden"
                       @change="uploadFile('desktop', $event)">
            </div>

            <p class="text-xs text-gray-500 mt-3">یا URL مستقیم (اگر تصویری از مخزن انتخاب شود، URL نادیده گرفته می‌شود):</p>
            <input type="url" name="image_url" x-model="manualUrl" maxlength="500" dir="ltr"
                   class="w-full mt-1 px-3 py-2 border border-gray-300 rounded text-sm font-mono ltr"
                   placeholder="https://...">
        </div>

        {{-- Mobile slot --}}
        <div class="pt-5 border-t border-gray-100">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium">تصویر موبایل (اختیاری)</label>
                <span class="text-xs text-gray-500">عمودی یا مربع برای موبایل بهتر است</span>
            </div>
            <input type="hidden" name="media_id_mobile" :value="mobile.id ?? ''">

            <div class="relative w-48 bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6),linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6)] [background-size:16px_16px] [background-position:0_0,8px_8px] rounded-lg border border-gray-200 overflow-hidden flex items-center justify-center mx-auto sm:mx-0"
                 style="aspect-ratio: 3/4; max-height: 280px;">
                <template x-if="mobile.url">
                    <img :src="mobile.url" :alt="mobile.alt ?? ''" class="max-w-full max-h-full object-contain">
                </template>
                <template x-if="!mobile.url">
                    <div class="text-xs text-gray-400 px-2 text-center">پیش‌نمایش موبایل</div>
                </template>
                <div x-show="uploading.mobile" x-cloak
                     class="absolute inset-0 bg-white/80 flex items-center justify-center text-sm text-purple-700 font-semibold">
                    در حال آپلود...
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-2">
                <button type="button" @click="openPicker('mobile')"
                        class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm">انتخاب از مخزن</button>
                <button type="button" @click="$refs.uploadMobile.click()" :disabled="uploading.mobile"
                        class="px-3 py-1.5 bg-emerald-600 text-white rounded text-sm disabled:opacity-50">آپلود تصویر جدید</button>
                <button type="button" @click="clearSlot('mobile')" x-show="mobile.url"
                        class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">پاک‌کردن</button>
                <input type="file" x-ref="uploadMobile" accept="image/*" class="hidden"
                       @change="uploadFile('mobile', $event)">
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
        @php
            $startJalali = $b?->starts_at ? \Morilog\Jalali\Jalalian::fromCarbon($b->starts_at)->format('Y/m/d H:i') : null;
            $endsJalali = $b?->ends_at ? \Morilog\Jalali\Jalalian::fromCarbon($b->ends_at)->format('Y/m/d H:i') : null;
        @endphp
        <div>
            <label class="block text-sm font-medium mb-1">شروع نمایش (شمسی)</label>
            <input type="text" name="starts_at"
                   value="{{ old('starts_at', $startJalali) }}"
                   placeholder="مثال: 1405/03/10 09:00"
                   dir="ltr"
                   class="jalali-datetimepicker w-full px-3 py-2 border border-gray-300 rounded-lg text-sm cursor-pointer bg-white ltr">
            <p class="text-xs text-gray-500 mt-1">خالی = همین حالا شروع شود</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">پایان نمایش (شمسی)</label>
            <input type="text" name="ends_at"
                   value="{{ old('ends_at', $endsJalali) }}"
                   placeholder="مثال: 1405/04/20 23:59"
                   dir="ltr"
                   class="jalali-datetimepicker w-full px-3 py-2 border border-gray-300 rounded-lg text-sm cursor-pointer bg-white ltr">
            <p class="text-xs text-gray-500 mt-1">خالی = بدون انقضا</p>
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
        desktop: config.initialDesktop ?? { id: null, url: null, alt: null },
        mobile:  config.initialMobile  ?? { id: null, url: null, alt: null },
        manualUrl: config.existingImageUrl ?? '',
        uploading: { desktop: false, mobile: false },
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
            this.setSlot(this.pickerTarget, m);
            this.pickerOpen = false;
        },
        setSlot(slot, media) {
            const data = { id: media.id, url: media.url, alt: media.alt ?? null };
            if (slot === 'desktop') {
                this.desktop = data;
                this.manualUrl = media.url;
            } else {
                this.mobile = data;
            }
        },
        clearSlot(slot) {
            if (slot === 'desktop') { this.desktop = { id: null, url: null, alt: null }; this.manualUrl = ''; }
            else { this.mobile = { id: null, url: null, alt: null }; }
        },
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
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok || !json.ok || !json.media) {
                    const msg = json?.message || 'آپلود ناموفق بود';
                    alert(msg);
                    return;
                }
                this.setSlot(slot, json.media);
            } catch (err) {
                alert('آپلود با خطا مواجه شد: ' + (err?.message || err));
            } finally {
                this.uploading[slot] = false;
                event.target.value = '';
            }
        },
    };
}

// Persian (Jalali) datetime picker — استفاده از همان lib سراسری
// تفاوت با .jalali-datepicker این است که timePicker فعال است.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.$ === 'undefined' || typeof window.$.fn.persianDatepicker === 'undefined') {
        return;
    }
    window.$('.jalali-datetimepicker').each(function () {
        var $i = window.$(this);
        if ($i.data('jdtp-init')) return;
        $i.data('jdtp-init', true);
        $i.persianDatepicker({
            format: 'YYYY/MM/DD HH:mm',
            initialValue: false,
            autoClose: true,
            responsive: true,
            position: 'auto',
            calendar: { persian: { locale: 'fa', showHint: true, leapYearMode: 'algorithmic' } },
            timePicker: {
                enabled: true,
                meridiem: { enabled: false },
                second: { enabled: false },
            },
            toolbox: {
                enabled: true,
                calendarSwitch: { enabled: false },
                todayButton: { enabled: true, text: { fa: 'امروز' } },
                submitButton: { enabled: true, text: { fa: 'تایید' } },
            },
            onSelect: function () {
                $i.trigger('change');
                $i[0].dispatchEvent(new Event('input', { bubbles: true }));
            },
        });
    });
});
</script>
