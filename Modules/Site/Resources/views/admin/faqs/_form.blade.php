@php $f = $faq ?? null; @endphp

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
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="255">
    </div>

    <div>
        <label class="block text-sm mb-1">پاسخ <span class="text-red-500">*</span></label>
        <textarea name="answer" rows="6" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="5000">{{ old('answer', $f?->answer) }}</textarea>
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
