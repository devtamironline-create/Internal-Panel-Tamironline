@php
    $allFaqs           = $allFaqs           ?? collect();
    $selectedFaqIds    = $selectedFaqIds    ?? [];
    $allReviews        = $allReviews        ?? collect();
    $selectedReviewIds = $selectedReviewIds ?? [];
@endphp
@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام دستگاه <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $device->name ?? '') }}" required
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (در صورت خالی، خودکار از نام ساخته می‌شود)</label>
        <input type="text" name="slug" value="{{ old('slug', $device->slug ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
        @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کلید آیکن (Lucide)</label>
        <input type="text" name="icon" value="{{ old('icon', $device->icon ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr"
               placeholder="washing-machine">
        <p class="text-xs text-gray-500 mt-1">نام آیکن Lucide به‌صورت kebab-case (مثال: washing-machine, refrigerator, snowflake).</p>
        @error('icon')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تم رنگ (Tone)</label>
        <select name="tone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
            <option value="">—</option>
            @foreach(['tone-blue','tone-green','tone-cyan','tone-sky','tone-orange','tone-amber','tone-rose','tone-violet','tone-emerald'] as $t)
                <option value="{{ $t }}" @selected(old('tone', $device->tone ?? '') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        @error('tone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $device->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('sort_order')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        @include('crm::partials.image-uploader', [
            'name'        => 'thumbnail',
            'fileName'    => 'thumbnail_file',
            'label'       => 'تصویر بندانگشتی دستگاه',
            'value'       => old('thumbnail', $device->thumbnail ?? null),
            'placeholder' => 'https://cdn.example.com/devices/washing-machine.png',
            'help'        => 'یک تصویر کوچک از دستگاه. ابعاد پیشنهادی: ۴۰۰×۴۰۰ پیکسل (مربعی، PNG شفاف یا WebP).',
        ])
    </div>

    <div class="flex items-end gap-6">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $device->is_featured ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">ویژه (نمایش پیش‌فرض در Hero)</span>
        </label>
    </div>
</div>

{{-- ───────────────────────── CMS Override Fields (Flat - single section) ───────────────────────── --}}
<div class="mt-8 space-y-6">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">محتوای CMS — صفحه‌ی detail دستگاه</h3>
        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
            هر فیلدی که خالی بگذارید، فرانت از <strong>fixture پیش‌فرض</strong> خود استفاده می‌کند.
        </p>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 space-y-6">

        {{-- ─── Section toggles (فعال/غیرفعال کردن سکشن‌های صفحه) ─── --}}
        @php
            $secs = old('sections_enabled', $device->sections_enabled ?? []);
            $secEnabled = fn(string $k, bool $default = true) => array_key_exists($k, (array) $secs)
                ? (bool) $secs[$k] : $default;
        @endphp
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
            <h4 class="text-sm font-bold text-emerald-900 dark:text-emerald-200 mb-2">سکشن‌های فعال در این صفحه</h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                @foreach([
                    'hero'          => 'Hero',
                    'steps'         => 'مراحل خدمات',
                    'live_activity' => 'Live Activity',
                    'content'       => 'محتوای کامل',
                    'faq'           => 'سوالات متداول',
                    'brands'        => 'برندها',
                    'testimonials'  => 'نظرات مشتریان',
                ] as $key => $label)
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="sections_enabled[{{ $key }}]" value="0">
                        <input type="checkbox" name="sections_enabled[{{ $key }}]" value="1"
                               @checked($secEnabled($key, true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- نام‌ها و توضیح --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نام کوتاه (short_name)</label>
                <input type="text" name="short_name" value="{{ old('short_name', $device->short_name ?? '') }}" maxlength="80"
                       placeholder="لباسشویی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">نام سرویس (service_name)</label>
                <input type="text" name="service_name" value="{{ old('service_name', $device->service_name ?? '') }}" maxlength="160"
                       placeholder="تعمیر لباسشویی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>

            {{-- عنوان تکنسین — مخفی از دید ادمین (با CSS) ولی هنوز submit می‌شود --}}
            <div class="sm:col-span-2" style="display:none">
                <label class="block text-sm font-medium mb-1">عنوان تکنسین</label>
                <input type="text" name="technician_name" value="{{ old('technician_name', $device->technician_name ?? '') }}" maxlength="160"
                       placeholder="تکنسین لباسشویی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Badge / بالای تیتر (eyebrow)</label>
                <input type="text" name="eyebrow" value="{{ old('eyebrow', $device->eyebrow ?? '') }}" maxlength="120"
                       placeholder="سرویس تخصصی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">کپشن (متن کوتاه زیر زیرتیتر)</label>
                <input type="text" name="caption" value="{{ old('caption', $device->caption ?? '') }}" maxlength="500"
                       placeholder="بیش از ۱۰ سال تجربه..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">زیرتیتر (subtitle)</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $device->subtitle ?? '') }}" maxlength="500"
                       placeholder="در محل، با گارانتی، بدون پیش‌پرداخت"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">محتوای کامل صفحه (سکشن content)</label>
                <textarea name="description" rows="10" maxlength="10000"
                          class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $device->description ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">محتوای متنی غنی برای سکشن «محتوای کامل». خروجی به‌صورت HTML در فرانت render می‌شود.</p>
            </div>
        </div>

        {{-- ─── دکمه‌های CTA (Hero) ─── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <h4 class="text-sm font-bold mb-3">دکمه‌های اصلی Hero</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                    <p class="text-xs font-bold text-gray-600">دکمه ثبت سفارش (Primary)</p>
                    <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $device->cta_primary_label ?? '') }}" maxlength="60"
                           placeholder="ثبت سفارش"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                    <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $device->cta_primary_url ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="/order یا https://..."
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $device->cta_primary_icon ?? '') }}" maxlength="60" dir="ltr"
                           placeholder="shopping-cart"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <p class="text-xs text-gray-500">آیکن Lucide به kebab-case.</p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                    <p class="text-xs font-bold text-gray-600">دکمه تماس فوری (Secondary)</p>
                    <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $device->cta_secondary_label ?? '') }}" maxlength="60"
                           placeholder="تماس فوری"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                    <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $device->cta_secondary_url ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="tel:02112345678"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $device->cta_secondary_icon ?? '') }}" maxlength="60" dir="ltr"
                           placeholder="phone"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <p class="text-xs text-gray-500">اگر خالی بگذارید، از مقدار سراسری استفاده می‌شود.</p>
                </div>
            </div>
        </div>

        {{-- ─── تصاویر مراحل خدمات (override on global) ─── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <h4 class="text-sm font-bold mb-1">تصاویر سکشن مراحل دریافت خدمات</h4>
            <p class="text-xs text-gray-500 mb-3">
                مقدار پیش‌فرض سراسری از
                <a href="{{ route('site.admin.page-content.edit', 'device') }}" target="_blank" class="text-blue-600 hover:underline">صفحه‌ی الگوی device</a>
                خوانده می‌شود. اگر این فیلدها را پر کنید، در صفحه‌ی این دستگاه override می‌شود.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                    <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $device->steps_image_desktop ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="https://...desktop.png"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                    <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $device->steps_image_mobile ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="https://...mobile.png"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
            </div>
        </div>

        {{-- قیمت و رنگ‌ها --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium mb-1">قیمت شروع (ریال)</label>
                <input type="number" name="starting_price" value="{{ old('starting_price', $device->starting_price ?? '') }}" min="0"
                       placeholder="1500000"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ accent (hex)</label>
                <input type="text" name="accent" value="{{ old('accent', $device->accent ?? '') }}" placeholder="#3B82F6" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('accent')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه (hex)</label>
                <input type="text" name="bg" value="{{ old('bg', $device->bg ?? '') }}" placeholder="#EFF6FF" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                @error('bg')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- FAQ از بانک — انتخاب چندتایی از /admin/site/faqs --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <label class="block text-sm font-medium mb-2">سوالات متداول این دستگاه</label>
            <p class="text-xs text-gray-500 mb-2">
                از <a href="{{ route('site.admin.faqs.index') }}" target="_blank" class="text-blue-600 hover:underline">بانک FAQ</a>
                سوالاتی را که می‌خواهید روی صفحه‌ی این دستگاه نشان داده شوند انتخاب کنید. ترتیب علامت‌زدن = ترتیب نمایش.
            </p>
            @php $selFaqs = old('faq_ids', $selectedFaqIds); @endphp
            <div class="border border-gray-200 dark:border-gray-700 rounded p-3 max-h-64 overflow-y-auto bg-gray-50 dark:bg-gray-900">
                @forelse($allFaqs as $f)
                    <label class="flex items-start gap-2 p-1 hover:bg-white dark:hover:bg-gray-800 rounded">
                        <input type="checkbox" name="faq_ids[]" value="{{ $f->id }}"
                               @checked(in_array($f->id, $selFaqs, true))
                               class="mt-1">
                        <span class="text-sm">{{ $f->question }}</span>
                    </label>
                @empty
                    <p class="text-xs text-gray-400">FAQ منتشرشده‌ای موجود نیست. <a href="{{ route('site.admin.faqs.create') }}" target="_blank" class="text-blue-600 hover:underline">ایجاد سوال جدید</a></p>
                @endforelse
            </div>
        </div>

        {{-- Reviews از بانک — انتخاب چندتایی از /admin/site/reviews --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <label class="block text-sm font-medium mb-2">دیدگاه‌های نمایش‌داده‌شده در این دستگاه</label>
            <p class="text-xs text-gray-500 mb-2">
                از <a href="{{ route('site.admin.reviews.index') }}" target="_blank" class="text-blue-600 hover:underline">بانک نظرات</a>
                دیدگاه‌هایی را که می‌خواهید روی این دستگاه نمایش داده شوند انتخاب کنید (شامل توصیه‌نامه‌های صوتی و نظرات متنی تأییدشده).
            </p>
            @php $selReviews = old('review_ids', $selectedReviewIds); @endphp
            <div class="border border-gray-200 dark:border-gray-700 rounded p-3 max-h-64 overflow-y-auto bg-gray-50 dark:bg-gray-900">
                @forelse($allReviews as $r)
                    <label class="flex items-start gap-2 p-1 hover:bg-white dark:hover:bg-gray-800 rounded">
                        <input type="checkbox" name="review_ids[]" value="{{ $r->id }}"
                               @checked(in_array($r->id, $selReviews, true))
                               class="mt-1">
                        <span class="text-sm">
                            @if($r->type === \Modules\Site\Models\Review::TYPE_AUDIO)
                                <span class="px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">صوتی</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-xs bg-sky-100 text-sky-700">متنی</span>
                            @endif
                            <strong>{{ $r->author_name }}</strong> — {{ str_repeat('★', (int) $r->rating) }}
                            @if($r->topic) — {{ $r->topic }} @endif
                            @if($r->content) — <span class="text-gray-500">{{ \Illuminate\Support\Str::limit($r->content, 80) }}</span> @endif
                        </span>
                    </label>
                @empty
                    <p class="text-xs text-gray-400">دیدگاه تأییدشده‌ای موجود نیست. <a href="{{ route('site.admin.reviews.create') }}" target="_blank" class="text-blue-600 hover:underline">ایجاد توصیه‌نامه</a></p>
                @endforelse
            </div>
        </div>

        {{-- مشکلات رایج — مخفی از دید ادمین (با CSS) --}}
        <div style="display:none">
            @include('crm::partials.json-repeater', [
                'name'  => 'issues',
                'label' => 'مشکلات رایج این دستگاه',
                'items' => old('issues', $device->issues ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان مشکل', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                ],
            ])
        </div>

        {{-- مراحل سرویس — مخفی از دید ادمین (با CSS) --}}
        <div style="display:none">
            @include('crm::partials.json-repeater', [
                'name'  => 'service_steps',
                'label' => 'مراحل کاری سفارش تا تحویل',
                'items' => old('service_steps', $device->service_steps ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                    'icon'        => ['label' => 'آیکون (lucide name)', 'type' => 'string'],
                ],
            ])
        </div>

        {{-- گارانتی و پشتیبانی — مخفی از دید ادمین (با CSS) --}}
        <div class="space-y-4" style="display:none">
            <div>
                <label class="block text-sm font-medium mb-1">متن گارانتی</label>
                <textarea name="warranty_text" rows="3" maxlength="3000"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('warranty_text', $device->warranty_text ?? '') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">اطلاعات پشتیبانی</label>
                <textarea name="support_info" rows="3" maxlength="3000"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('support_info', $device->support_info ?? '') }}</textarea>
            </div>
        </div>

        {{-- سئو --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $device->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $device->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="flex items-center gap-3 mt-6 sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-3">
    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
    <a href="{{ route('crm.devices.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
</div>
