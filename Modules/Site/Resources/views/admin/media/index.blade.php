@extends('layouts.admin')
@section('page-title', 'مخزن مدیا')

@section('main')
<div class="p-6"
     x-data="mediaLibrary({
         uploadUrl: '{{ route('site.admin.media.upload') }}',
         csrfToken: '{{ csrf_token() }}',
     })">

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between flex-wrap gap-3 p-5 bg-gradient-to-l from-purple-50 to-pink-50 dark:from-purple-900/30 dark:to-pink-900/30 rounded-xl border border-purple-200 dark:border-purple-800">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold">مخزن مدیا</h1>
                <p class="text-sm text-gray-600 mt-0.5">مرکزی برای همه‌ی تصاویر/ویدیو/سند سایت. فایل تکراری خودکار dedup می‌شود.</p>
            </div>
        </div>
        <label class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 cursor-pointer inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            افزودن فایل
            <input type="file" multiple class="hidden" @change="uploadFiles($event.target.files)">
        </label>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

    {{-- تنظیماتِ بهینه‌سازی/سخت‌گیریِ آپلودِ تصویر (فقط آپلودهای جدید) --}}
    @if(auth()->user()?->can('manage-site-media') || auth()->user()?->can('manage-site') || auth()->user()?->can('manage-permissions'))
    <div class="bg-white border border-gray-200 rounded-xl p-4 mb-5" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-2 text-right">
            <span class="text-sm font-bold text-gray-800">⚙️ بهینه‌سازی و سخت‌گیریِ آپلودِ تصویر (سئو)</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <form x-show="open" x-collapse method="POST" action="{{ route('site.admin.media.settings') }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">تبدیلِ خودکار به WebP</label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="convert_webp" value="0">
                    <input type="checkbox" name="convert_webp" value="1" @checked(($optimizeSettings['convert_webp'] ?? '1') === '1') class="w-4 h-4 accent-purple-600">
                    <span>jpg/png موقعِ آپلود به WebP تبدیل شود</span>
                </label>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">بیشینه‌ی عرضِ تصویر (پیکسل — ۰ = بدونِ محدودیت)</label>
                <input type="number" name="max_width" value="{{ $optimizeSettings['max_width'] ?? '1920' }}" min="0" max="10000"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" dir="ltr">
                <p class="text-[11px] text-gray-400 mt-1">تصویرِ بزرگ‌تر خودکار کوچک می‌شود.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">سقفِ حجمِ نهایی (KB — ۰ = بدونِ محدودیت)</label>
                <input type="number" name="max_kb" value="{{ $optimizeSettings['max_kb'] ?? '0' }}" min="0" max="51200"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" dir="ltr">
                <p class="text-[11px] text-gray-400 mt-1">پس از بهینه‌سازی؛ بزرگ‌تر = ردِ آپلود.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">فرمت‌های مجازِ ورودی (خالی = همه)</label>
                <input type="text" name="allowed_types" value="{{ $optimizeSettings['allowed_types'] ?? '' }}" placeholder="مثلاً: webp یا jpg,png,webp"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" dir="ltr">
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex items-center gap-3">
                <button class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">ذخیره‌ی تنظیمات</button>
                <span class="text-[11px] text-gray-500">فقط روی آپلودهای «جدید» اعمال می‌شود — فایل‌های موجودِ مخزن و لینک‌هایشان دست نمی‌خورند.</span>
            </div>
        </form>
    </div>
    @endif

    {{-- Stats + filters --}}
    @php
        $kinds = [
            null => ['همه', $counts['all'], 'gray'],
            'image' => ['تصاویر', $counts['image'], 'purple'],
            'video' => ['ویدیو', $counts['video'], 'red'],
            'document' => ['سند', $counts['document'], 'blue'],
            'audio' => ['صوت', $counts['audio'], 'amber'],
        ];
    @endphp
    <div class="bg-white border border-gray-200 rounded-xl p-3 mb-5 space-y-2">
        <div class="flex flex-wrap gap-2">
            @php $curKind = request('kind'); @endphp
            @foreach($kinds as $k => [$lbl, $cnt, $color])
                <a href="{{ route('site.admin.media.index', array_filter(['kind' => $k, 'q' => request('q')])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $curKind === $k ? "bg-{$color}-600 text-white" : "bg-{$color}-50 text-{$color}-700" }}">
                    {{ $lbl }} ({{ $cnt }})
                </a>
            @endforeach
        </div>
        <form method="GET" class="flex gap-2 items-center pt-2 border-t border-gray-100">
            @if(request('kind'))<input type="hidden" name="kind" value="{{ request('kind') }}">@endif
            <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو در نام/عنوان/alt..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <select name="tag" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">همه تگ‌ها</option>
                @foreach($tags as $t)<option value="{{ $t->slug }}" @selected(request('tag') === $t->slug)>{{ $t->name }}</option>@endforeach
            </select>
            <button class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm">فیلتر</button>
        </form>
    </div>

    {{-- Upload progress (live) --}}
    <div x-show="uploads.length > 0" x-cloak class="mb-4 bg-white border border-gray-200 rounded-xl p-4 space-y-2">
        <h3 class="text-sm font-bold">آپلود در حال انجام</h3>
        <template x-for="u in uploads" :key="u.id">
            <div class="flex items-center gap-3">
                <span class="text-xs font-mono shrink-0 w-40 truncate" x-text="u.name"></span>
                <div class="flex-1 h-2 bg-gray-200 rounded overflow-hidden">
                    <div class="h-full bg-purple-500 transition-all" :style="`width: ${u.progress}%`"></div>
                </div>
                <span class="text-xs text-gray-500 shrink-0" x-text="u.status"></span>
            </div>
        </template>
    </div>

    {{-- Drop zone --}}
    <div @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
         @drop.prevent="dragOver = false; uploadFiles($event.dataTransfer.files)"
         :class="dragOver ? 'border-purple-500 bg-purple-50' : 'border-gray-300'"
         class="border-2 border-dashed rounded-xl p-6 mb-5 text-center text-gray-500 text-sm transition">
        فایل‌ها را برای آپلود اینجا بکشید یا روی «افزودن فایل» کلیک کنید
    </div>

    {{-- Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        @forelse($media as $m)
            <a href="{{ route('site.admin.media.show', $m->id) }}"
               class="group relative bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md hover:border-purple-300 transition">
                <div class="aspect-square bg-gradient-to-br from-gray-100 to-gray-200 relative">
                    @if($m->kind === 'image')
                        <img src="{{ $m->variantUrl('thumb') }}" alt="{{ $m->alt }}" loading="lazy"
                             class="absolute inset-0 w-full h-full object-cover">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center text-gray-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                    @endif
                    <span class="absolute top-1 left-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-black/60 text-white">{{ $m->extension }}</span>
                </div>
                <div class="p-2">
                    <div class="text-xs truncate font-medium" title="{{ $m->filename }}">{{ $m->title ?? $m->filename }}</div>
                    <div class="text-[10px] text-gray-500 mt-0.5">
                        @if($m->width){{ $m->width }}×{{ $m->height }}@endif
                        · {{ number_format($m->size_bytes / 1024, 1) }}KB
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-16 text-gray-400">فایلی موجود نیست. آپلود کنید.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $media->links() }}</div>
</div>

<script>
function mediaLibrary(config) {
    return {
        dragOver: false,
        uploads: [],
        nextId: 1,
        async uploadFiles(fileList) {
            const files = Array.from(fileList);
            let anySuccess = false;
            for (const file of files) {
                const id = this.nextId++;
                const entry = { id, name: file.name, progress: 0, status: 'در حال آپلود...' };
                this.uploads.push(entry);
                try {
                    const fd = new FormData();
                    fd.append('file', file);
                    fd.append('_token', config.csrfToken);
                    const xhr = new XMLHttpRequest();
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) entry.progress = Math.round(e.loaded / e.total * 100);
                    };
                    await new Promise((resolve, reject) => {
                        xhr.onload = () => (xhr.status >= 200 && xhr.status < 300)
                            ? resolve(xhr.responseText)
                            : reject({ status: xhr.status, body: xhr.responseText });
                        xhr.onerror = () => reject({ status: 0, body: '' });
                        xhr.open('POST', config.uploadUrl);
                        // مهم: تا Laravel خطای اعتبارسنجی را JSON برگرداند (نه ریدایرکتِ
                        // back که XHR آن را ۲۰۰ می‌بیند و اشتباهاً «موفق» می‌پندارد).
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.send(fd);
                    });
                    entry.status = 'انجام شد ✓';
                    entry.progress = 100;
                    anySuccess = true;
                } catch (err) {
                    entry.status = this.uploadError(err);
                }
            }
            // فقط در صورتِ موفقیت ریلود کن تا گرید به‌روز شود؛ در خطا، پیام بماند.
            if (anySuccess) {
                setTimeout(() => window.location.reload(), 1000);
            }
        },
        uploadError(err) {
            if (err && err.status === 413) return 'حجمِ فایل از حدِ مجازِ سرور بیشتر است (post_max_size).';
            if (err && err.body) {
                try {
                    const j = JSON.parse(err.body);
                    if (j && j.message) return 'خطا: ' + j.message;
                } catch (_) { /* پاسخِ غیرِ JSON (مثلاً صفحه‌ی خطای PHP) */ }
            }
            if (err && err.status === 0) return 'خطای شبکه یا قطعِ آپلود.';
            return 'خطا در آپلود (کد ' + ((err && err.status) || '?') + ').';
        }
    };
}
</script>
@endsection
