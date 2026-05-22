@php
    $t = $testimonial ?? null;
    $taxonomies = $taxonomies ?? collect();
    $selT = $selectedTaxonomies ?? [];
    $selTcmp = array_map('intval', $selT);
@endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
    <ul class="list-disc pr-4">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm mb-1">نام مشتری <span class="text-red-500">*</span></label>
        <input type="text" name="customer_name" value="{{ old('customer_name', $t?->customer_name) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="80">
    </div>

    <div>
        <label class="block text-sm mb-1">موضوع <span class="text-red-500">*</span></label>
        <input type="text" name="topic" value="{{ old('topic', $t?->topic) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="120"
               placeholder="مثال: تعمیر {device}">
        <p class="text-xs text-gray-500 mt-1">Placeholder قابل استفاده: <code class="bg-gray-100 px-1 rounded">{device}</code>، <code class="bg-gray-100 px-1 rounded">{device_slug}</code>.</p>
    </div>

    <div>
        <label class="block text-sm mb-1">امتیاز (۱ تا ۵) <span class="text-red-500">*</span></label>
        <input type="number" name="rating" min="1" max="5" value="{{ old('rating', $t?->rating ?? 5) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required>
    </div>

    <div>
        <label class="block text-sm mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $t?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm mb-1">لینک فایل صوتی</label>
        <input type="url" name="audio_url" value="{{ old('audio_url', $t?->audio_url) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" maxlength="500" dir="ltr"
               placeholder="https://...">
    </div>

    <div>
        <label class="block text-sm mb-1">مدت زمان (ثانیه)</label>
        <input type="number" name="duration_seconds" min="1" max="7200" value="{{ old('duration_seconds', $t?->duration_seconds) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm mb-2">دسته‌بندی‌ها</label>
        <div class="border border-gray-200 rounded p-3 max-h-48 overflow-y-auto">
            @forelse($taxonomies as $tax)
                <label class="flex items-start gap-2 p-1 hover:bg-gray-50 rounded">
                    <input type="checkbox" name="taxonomy_ids[]" value="{{ $tax->id }}"
                           @checked(in_array((int) $tax->id, old('taxonomy_ids') ? array_map('intval', old('taxonomy_ids')) : $selTcmp, true))>
                    <span class="text-sm">
                        {{ $tax->name }}
                        <span class="text-xs text-gray-500 font-mono">/{{ $tax->slug }}</span>
                        @unless($tax->is_active)<span class="text-amber-600 text-xs">(غیرفعال)</span>@endunless
                    </span>
                </label>
            @empty
                <p class="text-xs text-gray-400">دسته‌ای تعریف نشده. <a href="{{ route('site.admin.taxonomies.index', 'testimonial') }}" class="text-blue-600 hover:underline" target="_blank">ایجاد دسته‌بندی</a></p>
            @endforelse
        </div>
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $t?->is_published))>
            <span class="text-sm">منتشر شود</span>
        </label>
    </div>
</div>
