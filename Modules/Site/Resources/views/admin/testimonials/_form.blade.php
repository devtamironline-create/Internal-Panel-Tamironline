@php $t = $testimonial ?? null; @endphp

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
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="120">
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
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $t?->is_published))>
            <span class="text-sm">منتشر شود</span>
        </label>
    </div>
</div>
