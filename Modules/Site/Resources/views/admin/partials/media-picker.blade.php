@php
    /**
     * Reusable media picker — هر فرم ادمین می‌تواند include کند:
     *
     *   @include('site::admin.partials.media-picker', [
     *       'name'     => 'cover_media_id',           // اسم input
     *       'current'  => $article->coverMedia,       // Media model یا null (اختیاری)
     *       'kind'     => 'image',                    // image|video|document|... (پیش‌فرض image)
     *       'label'    => 'تصویر کاور',
     *       'previewSize' => 'w-40 h-28',             // tailwind classes برای preview
     *   ])
     *
     * فرم می‌تواند چند picker با name متفاوت داشته باشد — تداخل ندارند.
     * نیاز به Alpine.js دارد (در layout admin بارگذاری شده است).
     */
    $pickerKind   = $kind ?? 'image';
    $pickerLabel  = $label ?? 'انتخاب از مخزن مدیا';
    $previewSize  = $previewSize ?? 'w-40 h-28';
    $componentId  = 'mp_'.\Illuminate\Support\Str::random(8);
    $currentMedia = $current ?? null;
    $currentApi   = $currentMedia ? $currentMedia->toApiArray() : null;
@endphp

<div x-data="mediaPicker_{{ $componentId }}()" class="space-y-2">
    <label class="block text-sm font-medium">{{ $pickerLabel }}</label>
    <input type="hidden" name="{{ $name }}" :value="picked.id ?? ''">

    <div class="flex items-start gap-3">
        <template x-if="picked.url">
            <div class="relative {{ $previewSize }} rounded-lg border border-gray-200 overflow-hidden bg-gray-50">
                <img :src="picked.variants?.thumb?.url ?? picked.url" :alt="picked.alt ?? ''"
                     class="absolute inset-0 w-full h-full object-cover">
                <template x-if="picked.visibility === 'private'">
                    <span class="absolute top-1 right-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500 text-white">خصوصی</span>
                </template>
            </div>
        </template>
        <template x-if="!picked.url">
            <div class="{{ $previewSize }} bg-gray-100 rounded-lg border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">بدون انتخاب</div>
        </template>
        <div class="flex flex-col gap-2">
            <button type="button" @click="openPicker()" class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">انتخاب از مخزن</button>
            <button type="button" x-show="picked.id" @click="clearPick()" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm">پاک‌کردن</button>
            <button type="button" @click="openUpload()" class="px-3 py-1.5 bg-emerald-600 text-white rounded text-sm hover:bg-emerald-700">آپلود سریع</button>
        </div>
    </div>

    {{-- Picker modal --}}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="modalOpen = false" @keydown.escape.window="modalOpen = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h3 class="font-bold">{{ $pickerLabel }}</h3>
                <button type="button" @click="modalOpen = false" class="text-gray-500 text-xl leading-none">✕</button>
            </div>
            <div class="p-3 border-b flex gap-2">
                <input type="text" x-model.debounce.200ms="search" placeholder="جستجو..." class="flex-1 px-3 py-2 border border-gray-300 rounded text-sm">
                <label class="px-3 py-2 bg-emerald-600 text-white rounded text-sm cursor-pointer">
                    + آپلود
                    <input type="file" class="hidden" @change="uploadFile($event.target.files[0])">
                </label>
            </div>
            <div x-show="uploading" class="px-3 py-2 bg-purple-50 text-purple-700 text-xs">در حال آپلود (<span x-text="uploadProgress"></span>٪)...</div>
            <div class="overflow-y-auto p-3 flex-1">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    <template x-for="m in items" :key="m.id">
                        <button type="button" @click="pickItem(m)" class="group text-right relative">
                            <img :src="m.variants?.thumb?.url ?? m.url" :alt="m.alt ?? ''"
                                 class="aspect-square w-full object-cover rounded border border-gray-200 group-hover:border-purple-500">
                            <template x-if="m.visibility === 'private'">
                                <span class="absolute top-1 right-1 px-1 py-0.5 rounded text-[8px] font-bold bg-amber-500 text-white">P</span>
                            </template>
                            <div class="text-[10px] text-gray-500 truncate mt-1" x-text="m.title || ''"></div>
                        </button>
                    </template>
                </div>
                <div x-show="loading" class="text-center py-4 text-gray-400 text-sm">در حال بارگذاری...</div>
                <div x-show="!loading && items.length === 0" class="text-center py-8 text-gray-400 text-sm">موردی موجود نیست.</div>
            </div>
            <div class="px-5 py-3 border-t flex items-center justify-between text-sm text-gray-500">
                <span>صفحه <span x-text="page"></span> / <span x-text="lastPage"></span></span>
                <div class="flex gap-2">
                    <button type="button" @click="loadPage(page - 1)" :disabled="page <= 1" class="px-3 py-1 border rounded disabled:opacity-50">قبلی</button>
                    <button type="button" @click="loadPage(page + 1)" :disabled="page >= lastPage" class="px-3 py-1 border rounded disabled:opacity-50">بعدی</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mediaPicker_{{ $componentId }}() {
    return {
        picked: @js($currentApi ?? ['id' => null, 'url' => null]),
        modalOpen: false,
        items: [], page: 1, lastPage: 1, loading: false,
        search: '',
        uploading: false, uploadProgress: 0,
        init() { this.$watch('search', () => this.loadPage(1)); },
        openPicker() { this.modalOpen = true; this.loadPage(1); },
        clearPick() { this.picked = { id: null, url: null }; },
        async loadPage(page) {
            if (page < 1) return;
            this.loading = true;
            const params = new URLSearchParams({ page: String(page), kind: @js($pickerKind) });
            if (this.search) params.set('q', this.search);
            try {
                const res = await fetch(@js(route('site.admin.media.picker')) + '?' + params);
                const j = await res.json();
                this.items = j.data; this.page = j.meta.page; this.lastPage = j.meta.last_page;
            } finally { this.loading = false; }
        },
        pickItem(m) { this.picked = m; this.modalOpen = false; },
        openUpload() {
            const i = document.createElement('input');
            i.type = 'file';
            i.onchange = () => this.uploadFile(i.files[0]);
            i.click();
        },
        async uploadFile(file) {
            if (!file) return;
            this.uploading = true; this.uploadProgress = 0;
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('_token', '{{ csrf_token() }}');
                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) this.uploadProgress = Math.round(e.loaded / e.total * 100);
                };
                const data = await new Promise((resolve, reject) => {
                    xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve(JSON.parse(xhr.response)) : reject(new Error(xhr.statusText));
                    xhr.onerror = () => reject(new Error('network'));
                    xhr.open('POST', @js(route('site.admin.media.upload')));
                    xhr.send(fd);
                });
                this.picked = data.media;
                this.modalOpen = false;
            } catch (e) { alert('خطا در آپلود: ' + e.message); }
            finally { this.uploading = false; }
        }
    };
}
</script>
