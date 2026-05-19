@php $b = $banner ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
    <ul class="list-disc pr-4">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label class="block text-sm mb-1">عنوان <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ old('title', $b?->title) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="200">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm mb-1">زیرعنوان</label>
        <input type="text" name="subtitle" value="{{ old('subtitle', $b?->subtitle) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" maxlength="300">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm mb-1">لینک تصویر <span class="text-red-500">*</span></label>
        <input type="url" name="image_url" value="{{ old('image_url', $b?->image_url) }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" required maxlength="500">
    </div>

    <div>
        <label class="block text-sm mb-1">لینک هدف</label>
        <input type="url" name="link_url" value="{{ old('link_url', $b?->link_url) }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" maxlength="500">
    </div>

    <div>
        <label class="block text-sm mb-1">متن دکمه</label>
        <input type="text" name="link_label" value="{{ old('link_label', $b?->link_label) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm" maxlength="80">
    </div>

    <div>
        <label class="block text-sm mb-1">موقعیت <span class="text-red-500">*</span></label>
        <input type="text" name="position" value="{{ old('position', $b?->position ?? 'home_hero') }}" dir="ltr"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" required maxlength="40"
               placeholder="home_hero">
        <p class="text-xs text-gray-500 mt-1">شناسه موقعیت در سایت — مثل home_hero یا home_secondary.</p>
    </div>

    <div>
        <label class="block text-sm mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $b?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div>
        <label class="block text-sm mb-1">شروع نمایش</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $b?->starts_at?->format('Y-m-d\TH:i')) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div>
        <label class="block text-sm mb-1">پایان نمایش</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $b?->ends_at?->format('Y-m-d\TH:i')) }}"
               class="w-full px-3 py-2 border border-gray-200 rounded text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $b?->is_published ?? true))>
            <span class="text-sm">منتشر شود</span>
        </label>
    </div>
</div>
