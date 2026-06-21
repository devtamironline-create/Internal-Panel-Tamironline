@csrf
@php $a = $article ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">عنوان مقاله <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $a?->title) }}" required maxlength="250"
                   placeholder="عیب‌یابی پکیج ایران رادیاتور | راهنمای جامع و کاربردی"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Slug (خالی = خودکار از عنوان)</label>
                <input type="text" name="slug" value="{{ old('slug', $a?->slug) }}" maxlength="200" dir="ltr"
                       placeholder="iran-radiator-common-problems-and-fixes"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
                <p class="text-xs text-gray-500 mt-1">URL: <code class="ltr" dir="ltr">/blog/{slug}</code></p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">زمان مطالعه (دقیقه)</label>
                <input type="number" name="read_time_minutes" value="{{ old('read_time_minutes', $a?->read_time_minutes) }}" min="1" max="600"
                       placeholder="12"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">خلاصه (excerpt)</label>
            <textarea name="excerpt" rows="2" maxlength="600"
                      placeholder="با راهنمای کامل عیب‌یابی پکیج ایران رادیاتور، بدون نیاز به متخصص..."
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('excerpt', $a?->excerpt) }}</textarea>
            <p class="text-xs text-gray-500 mt-1">برای کارت‌های لیست بلاگ نمایش داده می‌شود.</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <label class="block text-sm font-medium mb-2">محتوای مقاله</label>
        <textarea name="content" rows="20"
                  class="rich-editor w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('content', $a?->content) }}</textarea>
        <p class="text-xs text-gray-500 mt-2">از TinyMCE استفاده کنید — تیتر، فهرست، تصویر، embed یوتیوب/آپارات، جدول، کد، ...</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
        <h4 class="text-sm font-bold">تصویر کاور</h4>
        @php
            $currentCoverMedia = ! empty($a?->cover_media_id)
                ? \Modules\Site\Models\Media\Media::with('variants')->find($a->cover_media_id)
                : null;
        @endphp
        @include('site::admin.partials.media-picker', [
            'name' => 'cover_media_id',
            'current' => $currentCoverMedia,
            'kind' => 'image',
            'label' => 'انتخاب از مخزن مدیا',
            'previewSize' => 'w-48 h-32',
        ])
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1">یا URL مستقیم (در صورت عدم انتخاب از مخزن)</label>
                <input type="text" name="cover_image" value="{{ old('cover_image', $a?->cover_image) }}" maxlength="500" dir="ltr"
                       placeholder="https://..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">رنگ پس‌زمینه برای SVG placeholder (hex)</label>
                <input type="text" name="cover_color" value="{{ old('cover_color', $a?->cover_color) }}" maxlength="9" dir="ltr"
                       placeholder="#ffe4e6"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
                <p class="text-xs text-gray-500 mt-1">اگر کاور خالی باشد، فرانت SVG placeholder با این رنگ می‌سازد.</p>
            </div>
        </div>
    </div>

    {{-- ─── طبقه‌بندی: تاپیک + دستگاه + برند ─── --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-6">
        <h4 class="text-sm font-bold">طبقه‌بندی</h4>

        @php
            $topicItems = ($allTopics ?? collect())->map(fn ($t) => (object) [
                'id' => (int) $t->id,
                'label' => $t->name,
                'slug' => $t->slug,
                'badge' => 'تاپیک',
                'badge_color' => 'rose',
            ]);
        @endphp
        @include('crm::partials.multi-picker', [
            'name' => 'topic_ids',
            'items' => $topicItems,
            'selectedIds' => $selectedTopicIds ?? [],
            'columns' => 'wide',
            'label' => 'تاپیک‌ها (عیب‌یابی، نگهداری، ...)',
            'help' => 'حداقل یک تاپیک انتخاب کنید — تاپیک اول به‌عنوان badge کارت لیست نمایش داده می‌شود.',
            'emptyText' => 'تاپیکی تعریف نشده. ابتدا از منوی تاپیک‌های بلاگ تاپیک ایجاد کنید.',
        ])

        @include('crm::partials.multi-picker', [
            'name' => 'device_ids',
            'items' => $allDevices ?? collect(),
            'selectedIds' => $selectedDeviceIds ?? [],
            'label' => 'دستگاه‌های مرتبط',
            'help' => 'دستگاه‌هایی که این مقاله درباره‌شان است.',
            'emptyText' => 'دستگاهی فعال نیست.',
        ])

        @include('crm::partials.multi-picker', [
            'name' => 'brand_ids',
            'items' => $allBrands ?? collect(),
            'selectedIds' => $selectedBrandIds ?? [],
            'label' => 'برندهای مرتبط',
            'help' => 'برندهایی که این مقاله درباره‌شان است.',
            'emptyText' => 'برندی فعال نیست.',
        ])
    </div>

    {{-- ─── انتشار + SEO ─── --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">تاریخ انتشار (شمسی)</label>
            <input type="text" name="published_at_jalali" dir="ltr" autocomplete="off"
                   value="{{ old('published_at_jalali', $a?->published_at ? \Morilog\Jalali\Jalalian::fromDateTime($a->published_at)->format('Y/m/d') : '') }}"
                   placeholder="۱۴۰۵/۰۳/۳۱"
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 rounded-lg text-sm ltr">
            <p class="text-xs text-gray-500 mt-1">تاریخ را شمسی انتخاب کنید. خالی + «منتشر شود» = همین حالا.</p>
        </div>
        <div class="flex items-end pb-1">
            <label class="inline-flex items-center gap-2 p-3 rounded-lg bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 w-full">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $a?->is_published ?? false))
                       class="w-5 h-5 text-emerald-600 rounded">
                <span class="text-sm font-medium text-emerald-900">منتشر شود</span>
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $a?->sort_order ?? 0) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div></div>
        <div>
            <label class="block text-sm font-medium mb-1">Meta Title</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $a?->meta_title) }}" maxlength="250"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Meta Description</label>
            <textarea name="meta_description" rows="2" maxlength="500"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('meta_description', $a?->meta_description) }}</textarea>
        </div>
    </div>
</div>

<div class="flex items-center gap-3 mt-6 sticky bottom-0 bg-white/95 backdrop-blur p-3 -mx-3 border-t border-gray-200">
    <button type="submit" class="px-5 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">ذخیره مقاله</button>
    <a href="{{ route('site.admin.blog.articles.index') }}" class="px-5 py-2.5 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
</div>
