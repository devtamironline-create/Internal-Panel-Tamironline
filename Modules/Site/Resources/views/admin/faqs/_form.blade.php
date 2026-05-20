@php
    $f = $faq ?? null;
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

<div class="space-y-4">
    <div>
        <label class="block text-sm mb-1">سوال <span class="text-red-500">*</span></label>
        <input type="text" name="question" value="{{ old('question', $f?->question) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="255"
               placeholder="مثال: تعمیر {device} چقدر زمان می‌برد؟">
        <p class="text-xs text-gray-500 mt-1">
            می‌توانید از Placeholder استفاده کنید: <code class="bg-gray-100 px-1 rounded">{device}</code>،
            <code class="bg-gray-100 px-1 rounded">{device_slug}</code>،
            <code class="bg-gray-100 px-1 rounded">{page_title}</code> — این‌ها در API بر اساس صفحه‌ی فرانت جایگزین می‌شوند.
        </p>
    </div>

    <div>
        <label class="block text-sm mb-1">پاسخ <span class="text-red-500">*</span></label>
        <textarea name="answer" rows="6" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="5000">{{ old('answer', $f?->answer) }}</textarea>
    </div>

    <div>
        <label class="block text-sm mb-2">دسته‌بندی‌ها</label>
        <div class="border border-gray-200 rounded p-3 max-h-48 overflow-y-auto">
            @forelse($taxonomies as $t)
                <label class="flex items-start gap-2 p-1 hover:bg-gray-50 rounded">
                    <input type="checkbox" name="taxonomy_ids[]" value="{{ $t->id }}"
                           @checked(in_array((int) $t->id, old('taxonomy_ids') ? array_map('intval', old('taxonomy_ids')) : $selTcmp, true))>
                    <span class="text-sm">
                        {{ $t->name }}
                        <span class="text-xs text-gray-500 font-mono">/{{ $t->slug }}</span>
                        @unless($t->is_active)<span class="text-amber-600 text-xs">(غیرفعال)</span>@endunless
                    </span>
                </label>
            @empty
                <p class="text-xs text-gray-400">دسته‌ای تعریف نشده. <a href="{{ route('site.admin.taxonomies.index', 'faq') }}" class="text-blue-600 hover:underline" target="_blank">ایجاد دسته‌بندی</a></p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm mb-1">ترتیب نمایش</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $f?->sort_order ?? 0) }}"
                   class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $f?->is_published ?? true))>
                <span class="text-sm">منتشر شود</span>
            </label>
        </div>
    </div>
</div>
