@csrf
@php $e = $expert ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $er)<li>{{ $er }}</li>@endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">نام <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $e?->name) }}" required maxlength="120"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Slug (خالی = خودکار)</label>
        <input type="text" name="slug" value="{{ old('slug', $e?->slug) }}" maxlength="120" dir="ltr"
               placeholder="ali-mohammadi"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">عنوان تخصصی</label>
        <input type="text" name="title" value="{{ old('title', $e?->title) }}" maxlength="200"
               placeholder="کارشناس تعمیر لباس‌شویی سامسونگ"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">آواتار کارشناس</label>
        @php
            $currentAvatarMedia = ! empty($e?->avatar_media_id)
                ? \Modules\Site\Models\Media\Media::with('variants')->find($e->avatar_media_id)
                : null;
        @endphp
        @include('site::admin.partials.media-picker', [
            'name' => 'avatar_media_id',
            'current' => $currentAvatarMedia,
            'kind' => 'image',
            'label' => 'انتخاب از مخزن مدیا',
            'previewSize' => 'w-24 h-24',
        ])
        <label class="block text-xs font-medium mt-3 mb-1">یا URL مستقیم</label>
        <input type="text" name="avatar" value="{{ old('avatar', $e?->avatar) }}" maxlength="500" dir="ltr"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">بیو</label>
        <textarea name="bio" rows="3" maxlength="5000"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('bio', $e?->bio) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">امتیاز (0–5)</label>
        <input type="number" step="0.1" name="rating" min="0" max="5" value="{{ old('rating', $e?->rating ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">ترتیب</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $e?->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="flex items-end pb-1 sm:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $e?->is_active ?? true))
                   class="w-4 h-4 text-blue-600 rounded">
            <span class="text-sm">فعال</span>
        </label>
    </div>
</div>

<div class="flex gap-3 mt-6">
    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">ذخیره</button>
    <a href="{{ route('site.admin.forum.experts.index') }}" class="px-5 py-2.5 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
</div>
