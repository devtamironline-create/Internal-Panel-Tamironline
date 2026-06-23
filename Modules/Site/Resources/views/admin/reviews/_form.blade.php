@php
    $r = $review ?? null;
    $taxonomies = $taxonomies ?? collect();
    $selT = $selectedTaxonomies ?? [];
    $selTcmp = array_map('intval', $selT);
    $devices = $devices ?? collect();
    $selD = $selectedDeviceIds ?? [];
    $selDcmp = array_map('intval', $selD);
    $oldTax = old('taxonomy_ids') ? array_map('intval', old('taxonomy_ids')) : $selTcmp;
    $oldDev = old('device_ids') ? array_map('intval', old('device_ids')) : $selDcmp;
@endphp

@if($errors->any())
<div class="mb-5 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
    <div class="font-bold mb-1">لطفاً خطاهای زیر را برطرف کنید:</div>
    <ul class="list-disc pr-5 space-y-0.5">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div x-data="reviewForm({
        rating: {{ (int) old('rating', $r?->rating ?? 5) }},
        audioUrl: @js(old('audio_url', $r?->audio_url ?? '')),
        duration: @js(old('duration_seconds', $r?->duration_seconds ?? '')),
     })"
     class="space-y-6">

    {{-- ───── ۱) اطلاعات اصلی ───── --}}
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-xs">۱</span>
            اطلاعات اصلی
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نام مشتری <span class="text-red-500">*</span></label>
                <input type="text" name="author_name" value="{{ old('author_name', $r?->author_name) }}"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm" required maxlength="80"
                       placeholder="مثال: علی رضایی">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">موضوع <span class="text-red-500">*</span></label>
                <input type="text" name="topic" value="{{ old('topic', $r?->topic) }}"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm" required maxlength="120"
                       placeholder="مثال: تعمیر {device}">
                <p class="text-xs text-gray-500 mt-1">
                    می‌توانید از placeholder استفاده کنید؛ روی صفحه‌ی هر دستگاه/برند خودکار جایگزین می‌شود:
                    <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{device}</code>
                    <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{brand}</code>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">امتیاز <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-1" role="radiogroup" aria-label="امتیاز">
                    <template x-for="n in 5" :key="n">
                        <button type="button" @click="rating = n"
                                class="text-2xl leading-none transition-transform hover:scale-110"
                                :class="n <= rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'"
                                :aria-label="n + ' ستاره'">★</button>
                    </template>
                    <span class="text-sm text-gray-500 mr-2" x-text="rating + ' از ۵'"></span>
                </div>
                <input type="hidden" name="rating" :value="rating">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $r?->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">عدد کوچک‌تر = بالاتر در لیست.</p>
            </div>
        </div>
    </section>

    {{-- ───── ۲) محتوا (متن و/یا صوت) ───── --}}
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-1 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-xs">۲</span>
            محتوای نظر
        </h3>
        <p class="text-xs text-gray-500 mb-4">می‌تواند <b>متن</b>، <b>فایل صوتی</b>، یا <b>هر دو</b> باشد. حداقل یکی لازم است.</p>

        {{-- متن --}}
        <div class="mb-5">
            <label class="block text-sm font-medium mb-1">متن نظر</label>
            <textarea name="content" rows="4" maxlength="5000"
                      class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm leading-7"
                      placeholder="متن نظر مشتری را اینجا بنویسید...">{{ old('content', $r?->content) }}</textarea>
        </div>

        {{-- صوت --}}
        <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
            <label class="block text-sm font-medium mb-2">فایل صوتی</label>

            <div class="flex flex-wrap items-center gap-2 mb-3">
                <button type="button" @click="pickFile()"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm inline-flex items-center gap-1.5"
                        :disabled="uploading">
                    <span x-show="!uploading">⬆️ آپلود فایل صوتی</span>
                    <span x-show="uploading">در حال آپلود (<span x-text="progress"></span>٪)...</span>
                </button>
                <button type="button" @click="openLibrary()" :disabled="uploading"
                        class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm">📚 انتخاب از مخزن</button>
                <button type="button" x-show="audioUrl" @click="clearAudio()"
                        class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">حذف صوت</button>
            </div>

            {{-- URL (دستی یا پرشده توسط آپلود) --}}
            <input type="url" name="audio_url" x-model="audioUrl" maxlength="500" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm ltr mb-3"
                   placeholder="https://...  (یا با دکمه‌ی آپلود پر کنید)">

            {{-- پیش‌نمایش پخش --}}
            <template x-if="audioUrl">
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800 rounded-lg p-3">
                    <p class="text-xs text-purple-700 dark:text-purple-300 mb-2">پیش‌نمایش پخش:</p>
                    <audio controls preload="metadata" class="w-full" :src="audioUrl"
                           x-on:loadedmetadata="onMeta($event)"
                           x-on:error="playError = true" x-on:loadeddata="playError = false"></audio>
                    <p x-show="playError" class="text-xs text-red-600 mt-2">
                        ⚠️ این فایل پخش نشد. مطمئن شوید لینک، <b>آدرس مستقیم فایل صوتی</b> (mp3/m4a/ogg) است — نه صفحه‌ی اشتراک‌گذاری. بهترین راه: دکمه‌ی «آپلود فایل صوتی».
                    </p>
                </div>
            </template>

            <div class="mt-3 max-w-xs">
                <label class="block text-sm font-medium mb-1">مدت زمان (ثانیه)</label>
                <input type="number" name="duration_seconds" min="1" max="7200" x-model="duration"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-900 rounded-lg text-sm"
                       placeholder="خودکار از فایل خوانده می‌شود">
            </div>
        </div>

        {{-- مودال انتخاب از مخزن (kind=audio) --}}
        <div x-show="libraryOpen" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
             @click.self="libraryOpen = false" @keydown.escape.window="libraryOpen = false">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-5 py-3 border-b dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-bold">انتخاب فایل صوتی از مخزن</h3>
                    <button type="button" @click="libraryOpen = false" class="text-gray-500 text-xl leading-none">✕</button>
                </div>
                <div class="p-3 border-b dark:border-gray-700">
                    <input type="text" x-model.debounce.250ms="search" @input="loadPage(1)" placeholder="جستجو..."
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded text-sm">
                </div>
                <div class="overflow-y-auto p-3 flex-1">
                    <div class="space-y-1">
                        <template x-for="m in items" :key="m.id">
                            <button type="button" @click="pickFromLibrary(m)"
                                    class="w-full text-right flex items-center gap-3 p-2 rounded hover:bg-purple-50 dark:hover:bg-gray-700 border border-transparent hover:border-purple-200">
                                <span class="text-lg">🎵</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm truncate" x-text="m.title || m.original_name || ('فایل #' + m.id)"></span>
                                    <span class="block text-[11px] text-gray-400 truncate ltr" x-text="m.url"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <div x-show="loading" class="text-center py-4 text-gray-400 text-sm">در حال بارگذاری...</div>
                    <div x-show="!loading && items.length === 0" class="text-center py-8 text-gray-400 text-sm">فایل صوتی‌ای در مخزن نیست. از دکمه‌ی «آپلود» استفاده کنید.</div>
                </div>
                <div class="px-5 py-3 border-t dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                    <span>صفحه <span x-text="page"></span> / <span x-text="lastPage"></span></span>
                    <div class="flex gap-2">
                        <button type="button" @click="loadPage(page - 1)" :disabled="page <= 1" class="px-3 py-1 border dark:border-gray-600 rounded disabled:opacity-50">قبلی</button>
                        <button type="button" @click="loadPage(page + 1)" :disabled="page >= lastPage" class="px-3 py-1 border dark:border-gray-600 rounded disabled:opacity-50">بعدی</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ───── ۳) دسته‌بندی و دستگاه‌های مرتبط ───── --}}
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-xs">۳</span>
            دسته‌بندی و ارتباط
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium mb-2">دسته‌بندی‌ها</label>
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-2 max-h-52 overflow-y-auto space-y-0.5">
                    @forelse($taxonomies as $tax)
                        <label class="flex items-start gap-2 p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                            <input type="checkbox" name="taxonomy_ids[]" value="{{ $tax->id }}" class="mt-0.5"
                                   @checked(in_array((int) $tax->id, $oldTax, true))>
                            <span class="text-sm">
                                {{ $tax->name }}
                                <span class="text-xs text-gray-400 font-mono">/{{ $tax->slug }}</span>
                                @unless($tax->is_active)<span class="text-amber-600 text-xs">(غیرفعال)</span>@endunless
                            </span>
                        </label>
                    @empty
                        <p class="text-xs text-gray-400 p-2">دسته‌ای تعریف نشده. <a href="{{ route('site.admin.taxonomies.index', 'testimonial') }}" class="text-blue-600 hover:underline" target="_blank">ایجاد دسته‌بندی</a></p>
                    @endforelse
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">دستگاه‌های مرتبط</label>
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-2 max-h-52 overflow-y-auto space-y-0.5">
                    @forelse($devices as $d)
                        <label class="flex items-start gap-2 p-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                            <input type="checkbox" name="device_ids[]" value="{{ $d->id }}" class="mt-0.5"
                                   @checked(in_array((int) $d->id, $oldDev, true))>
                            <span class="text-sm">{{ $d->name }} <span class="text-xs text-gray-400 font-mono">/{{ $d->slug }}</span></span>
                        </label>
                    @empty
                        <p class="text-xs text-gray-400 p-2">دستگاهی فعال نیست.</p>
                    @endforelse
                </div>
                <p class="text-xs text-gray-500 mt-1.5">اگر هیچ دستگاهی انتخاب نشود، به‌صورت generic در همه‌ی دستگاه‌ها نمایش داده می‌شود.</p>
            </div>
        </div>
    </section>

    {{-- ───── ۴) انتشار ───── --}}
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 inline-flex items-center justify-center text-xs">۴</span>
            تنظیمات انتشار
        </h3>
        <div class="flex flex-wrap items-center gap-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $r?->is_published))>
                <span class="text-sm">منتشر شود (نمایش روی سایت)</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $r?->is_featured))>
                <span class="text-sm">ویژه (نمایش بالای لیست)</span>
            </label>
        </div>
    </section>
</div>

<script>
function reviewForm(init) {
    return {
        rating: init.rating || 5,
        audioUrl: init.audioUrl || '',
        duration: init.duration || '',
        playError: false,
        uploading: false,
        progress: 0,
        // library modal
        libraryOpen: false, items: [], page: 1, lastPage: 1, loading: false, search: '',

        onMeta(e) {
            this.playError = false;
            const d = Math.round(e.target.duration || 0);
            if (d > 0 && (!this.duration || Number(this.duration) <= 0)) {
                this.duration = d;
            }
        },
        clearAudio() { this.audioUrl = ''; this.playError = false; },

        pickFile() {
            const i = document.createElement('input');
            i.type = 'file';
            i.accept = 'audio/*';
            i.onchange = () => { if (i.files[0]) this.uploadFile(i.files[0]); };
            i.click();
        },
        async uploadFile(file) {
            this.uploading = true; this.progress = 0;
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('_token', '{{ csrf_token() }}');
                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100); };
                const data = await new Promise((resolve, reject) => {
                    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve(JSON.parse(xhr.response)) : reject(new Error('upload failed'));
                    xhr.onerror = () => reject(new Error('network'));
                    xhr.open('POST', @js(route('site.admin.media.upload')));
                    xhr.send(fd);
                });
                this.audioUrl = data.media.url;
                this.duration = '';
                this.playError = false;
            } catch (e) { alert('خطا در آپلود فایل صوتی: ' + e.message); }
            finally { this.uploading = false; }
        },

        openLibrary() { this.libraryOpen = true; this.loadPage(1); },
        async loadPage(p) {
            if (p < 1) return;
            this.loading = true;
            const params = new URLSearchParams({ page: String(p), kind: 'audio' });
            if (this.search) params.set('q', this.search);
            try {
                const res = await fetch(@js(route('site.admin.media.picker')) + '?' + params);
                const j = await res.json();
                this.items = j.data; this.page = j.meta.page; this.lastPage = j.meta.last_page;
            } finally { this.loading = false; }
        },
        pickFromLibrary(m) { this.audioUrl = m.url; this.duration = ''; this.playError = false; this.libraryOpen = false; },
    };
}
</script>
