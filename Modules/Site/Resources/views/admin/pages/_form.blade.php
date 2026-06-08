@php
    $p = $page ?? null;
    $selT = $selectedTestimonials ?? [];
    $selF = $selectedFaqs ?? [];
@endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
    <ul class="list-disc pr-4">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- اطلاعات اصلی --}}
    <div class="md:col-span-2 space-y-4">
        <div>
            <label class="block text-sm mb-1">عنوان صفحه <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $p?->title) }}"
                   class="w-full px-3 py-2 border border-gray-200 rounded text-sm" required maxlength="200">
        </div>

        <div>
            <label class="block text-sm mb-1">اسلاگ <span class="text-red-500">*</span></label>
            <input type="text" name="slug" value="{{ old('slug', $p?->slug) }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr" required maxlength="120"
                   placeholder="about-us">
            <p class="text-xs text-gray-500 mt-1">حروف کوچک انگلیسی، عدد و خط تیره.</p>
        </div>

        <div>
            <label class="block text-sm mb-1">محتوای صفحه</label>
            <textarea name="content" rows="10" class="w-full px-3 py-2 border border-gray-200 rounded text-sm">{{ old('content', $p?->content) }}</textarea>
        </div>

        <details class="border border-gray-200 rounded">
            <summary class="px-3 py-2 cursor-pointer text-sm font-semibold">سئو (Meta)</summary>
            <div class="p-3 space-y-3">
                <div>
                    <label class="block text-sm mb-1">Meta Title</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title', $p?->meta_title) }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded text-sm" maxlength="200">
                </div>
                <div>
                    <label class="block text-sm mb-1">Meta Description</label>
                    <textarea name="meta_description" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded text-sm" maxlength="500">{{ old('meta_description', $p?->meta_description) }}</textarea>
                </div>
            </div>
        </details>
    </div>

    {{-- ستون کناری --}}
    <div class="space-y-4">
        <div class="bg-gray-50 rounded p-3">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $p?->is_published))>
                <span class="text-sm font-semibold">منتشر شود</span>
            </label>
        </div>

        <div class="border border-gray-200 rounded p-3">
            <h3 class="text-sm font-semibold mb-2">انتخاب نظرات مشتریان</h3>
            <p class="text-xs text-gray-500 mb-2">ترتیب انتخاب = ترتیب نمایش.</p>
            <div class="max-h-64 overflow-y-auto space-y-1">
                @forelse(($testimonials ?? []) as $t)
                <label class="flex items-start gap-2 p-1 hover:bg-gray-50 rounded">
                    <input type="checkbox" name="testimonial_ids[]" value="{{ $t->id }}"
                           @checked(in_array($t->id, old('testimonial_ids', $selT), true))>
                    <span class="text-xs">
                        <span class="font-semibold">{{ $t->customer_name }}</span>
                        @unless($t->is_published) <span class="text-amber-600">(پیش‌نویس)</span> @endunless
                        <br><span class="text-gray-500">{{ \Illuminate\Support\Str::limit($t->topic, 60) }}</span>
                    </span>
                </label>
                @empty
                <p class="text-xs text-gray-400">نظری موجود نیست.</p>
                @endforelse
            </div>
        </div>

        <div class="border border-gray-200 rounded p-3">
            <h3 class="text-sm font-semibold mb-2">انتخاب سوالات متداول</h3>
            <p class="text-xs text-gray-500 mb-2">ترتیب انتخاب = ترتیب نمایش.</p>
            <div class="max-h-64 overflow-y-auto space-y-1">
                @forelse(($faqs ?? []) as $f)
                <label class="flex items-start gap-2 p-1 hover:bg-gray-50 rounded">
                    <input type="checkbox" name="faq_ids[]" value="{{ $f->id }}"
                           @checked(in_array($f->id, old('faq_ids', $selF), true))>
                    <span class="text-xs">
                        {{ \Illuminate\Support\Str::limit($f->question, 80) }}
                        @unless($f->is_published) <span class="text-amber-600">(پیش‌نویس)</span> @endunless
                    </span>
                </label>
                @empty
                <p class="text-xs text-gray-400">سوالی موجود نیست.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
