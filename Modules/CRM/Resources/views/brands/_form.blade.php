@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نام برند <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $brand->name ?? '') }}" required
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Slug (در صورت خالی، خودکار از نام ساخته می‌شود)</label>
        <input type="text" name="slug" value="{{ old('slug', $brand->slug ?? '') }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500" dir="ltr">
        @error('slug')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        @php
            $currentLogoMedia = ! empty($brand->logo_media_id ?? null)
                ? \Modules\Site\Models\Media\Media::with('variants')->find($brand->logo_media_id)
                : null;
        @endphp
        @include('site::admin.partials.media-picker', [
            'name' => 'logo_media_id',
            'current' => $currentLogoMedia,
            'kind' => 'image',
            'label' => 'لوگوی برند (ابعاد ۴۰۰×۴۰۰، PNG شفاف یا SVG)',
            'previewSize' => 'w-32 h-32',
        ])
        <p class="text-xs text-gray-500 mt-1">یا URL مستقیم:</p>
        <input type="text" name="logo" value="{{ old('logo', $brand->logo ?? '') }}" dir="ltr"
               class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono ltr">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب نمایش</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $brand->sort_order ?? 0) }}"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-500">
        @error('sort_order')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end gap-6">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">فعال</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured ?? false))
                   class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
            <span class="text-sm text-gray-700 dark:text-gray-200">برند ویژه (نمایش در صفحه‌ی اصلی سایت)</span>
        </label>
    </div>
</div>

{{-- ───────────────────────── رنگ‌های هویت برند ───────────────────────── --}}
<div class="mt-8 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
    <h3 class="text-base font-bold mb-1">رنگ‌های هویت برند</h3>
    <p class="text-xs text-gray-500 mb-4">
        این رنگ‌ها در فرانت برای پس‌زمینه‌ی Hero، دکمه‌ها و عناصر تأکیدی استفاده می‌شوند.
        اگر خالی بگذارید، رنگ پیش‌فرض قالب اعمال می‌شود.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
         x-data="{
            tone: @js(old('tone', $brand->tone ?? '')),
            bg:   @js(old('bg', $brand->bg ?? '')),
            normalize(v) {
                if (!v) return '';
                v = v.trim();
                if (v[0] !== '#') v = '#' + v;
                return v;
            },
         }">
        <div>
            <label class="block text-sm font-medium mb-1">رنگ اصلی برند (accent)</label>
            <div class="flex items-center gap-2">
                <input type="color"
                       :value="(tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#1428A0'"
                       @input="tone = $event.target.value"
                       class="h-10 w-14 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-transparent">
                <input type="text" name="tone" x-model="tone" :value="tone"
                       placeholder="#1428A0" dir="ltr" maxlength="9"
                       @blur="tone = normalize(tone)"
                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
            </div>
            <p class="text-xs text-gray-500 mt-1">رنگ غالب — مثال سامسونگ <span dir="ltr">#1428A0</span></p>
            @error('tone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">رنگ پس‌زمینه ملایم</label>
            <div class="flex items-center gap-2">
                <input type="color"
                       :value="(bg && /^#[0-9a-fA-F]{6}$/.test(bg)) ? bg : '#EEF2FF'"
                       @input="bg = $event.target.value"
                       class="h-10 w-14 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-transparent">
                <input type="text" name="bg" x-model="bg" :value="bg"
                       placeholder="#EEF2FF" dir="ltr" maxlength="9"
                       @blur="bg = normalize(bg)"
                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
            </div>
            <p class="text-xs text-gray-500 mt-1">پس‌زمینه‌ی کارت‌ها و سکشن‌های فرعی</p>
            @error('bg')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- پیش‌نمایش زنده --}}
        <div class="sm:col-span-2">
            <p class="text-xs text-gray-500 mb-2">پیش‌نمایش زنده:</p>
            <div class="rounded-xl p-6 border border-gray-200 dark:border-gray-700 transition-colors"
                 :style="{ backgroundColor: (bg && /^#[0-9a-fA-F]{6}$/.test(bg)) ? bg : '#f9fafb' }">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="px-4 py-2 rounded-lg text-white font-bold text-sm"
                          :style="{ backgroundColor: (tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#374151' }">
                        {{ $brand->name ?? 'نام برند' }}
                    </span>
                    <span class="text-sm font-medium"
                          :style="{ color: (tone && /^#[0-9a-fA-F]{6}$/.test(tone)) ? tone : '#374151' }">
                        پیش‌نمایش متن تأکیدی
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ───────────────────────── تصویر Hero اختصاصی این برند ───────────────────────── --}}
<div class="mt-8 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
    <h3 class="text-base font-bold mb-1">تصویر Hero اختصاصی این برند</h3>
    <p class="text-xs text-gray-500 mb-4">
        دو تصویر برای دسکتاپ و موبایل، هرکدام با متن جایگزین (alt) مستقل. این تصاویر فقط در صفحه‌ی همین برند نمایش داده می‌شوند و
        پیش‌فرض الگوی <code>brand</code> را override می‌کنند.
    </p>
    @include('crm::partials.hero-image-picker', [
        'name' => 'hero_image',
        'current' => old('hero_image', $brand->hero_image ?? null),
        'entityKind' => 'برند',
        'templateRoute' => route('site.admin.page-content.edit', 'brand'),
    ])
</div>

{{-- ───────────────────────── CMS Override Fields (Flat - single section) ───────────────────────── --}}
<div class="mt-8 space-y-6">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">محتوای CMS — صفحه‌ی detail برند</h3>
        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
            هر فیلدی که خالی بگذارید، فرانت از <strong>مقدار سراسری</strong> (تعریف‌شده در
            <a href="{{ route('site.admin.page-content.edit', 'brand') }}" target="_blank" class="text-blue-600 hover:underline">الگوی brand</a>)
            استفاده می‌کند.
        </p>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 space-y-6">

        {{-- ─── Section toggles ─── --}}
        @php
            $secs = old('sections_enabled', $brand->sections_enabled ?? []);
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
                    'devices'       => 'دستگاه‌ها',
                    'testimonials'  => 'نظرات مشتریان',
                    'videos'        => 'ویدیوها',
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
                <label class="block text-sm font-medium mb-1">شعار / Tagline (یک خط)</label>
                <input type="text" name="tagline" value="{{ old('tagline', $brand->tagline ?? '') }}" maxlength="1000"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Badge / بالای تیتر (eyebrow)</label>
                <input type="text" name="eyebrow" value="{{ old('eyebrow', $brand->eyebrow ?? '') }}" maxlength="120"
                       placeholder="نمایندگی رسمی"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">زیرتیتر (subtitle)</label>
                <input type="text" name="subtitle" value="{{ old('subtitle', $brand->subtitle ?? '') }}" maxlength="500"
                       placeholder="تعمیر تخصصی محصولات این برند..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">کپشن (متن کوتاه)</label>
                <input type="text" name="caption" value="{{ old('caption', $brand->caption ?? '') }}" maxlength="500"
                       placeholder="با بیش از ۱۰ سال تجربه..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">محتوای کامل صفحه (سکشن content)</label>
                <textarea name="description" rows="10"
                          class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('description', $brand->description ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">محتوای متنی غنی برای سکشن «محتوای کامل». خروجی به‌صورت HTML در فرانت render می‌شود.</p>
            </div>
        </div>

        {{-- CTA --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <h4 class="text-sm font-bold mb-3">دکمه‌های اصلی Hero</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                    <p class="text-xs font-bold text-gray-600">دکمه ثبت سفارش (Primary)</p>
                    <input type="text" name="cta_primary_label" value="{{ old('cta_primary_label', $brand->cta_primary_label ?? '') }}" maxlength="60"
                           placeholder="ثبت سفارش"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                    <input type="text" name="cta_primary_url" value="{{ old('cta_primary_url', $brand->cta_primary_url ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="/order یا https://..."
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <input type="text" name="cta_primary_icon" value="{{ old('cta_primary_icon', $brand->cta_primary_icon ?? '') }}" maxlength="60" dir="ltr"
                           placeholder="shopping-cart"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-2 bg-gray-50 dark:bg-gray-900">
                    <p class="text-xs font-bold text-gray-600">دکمه تماس فوری (Secondary)</p>
                    <input type="text" name="cta_secondary_label" value="{{ old('cta_secondary_label', $brand->cta_secondary_label ?? '') }}" maxlength="60"
                           placeholder="تماس فوری"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm">
                    <input type="text" name="cta_secondary_url" value="{{ old('cta_secondary_url', $brand->cta_secondary_url ?? '') }}" maxlength="500" dir="ltr"
                           placeholder="tel:02112345678"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                    <input type="text" name="cta_secondary_icon" value="{{ old('cta_secondary_icon', $brand->cta_secondary_icon ?? '') }}" maxlength="60" dir="ltr"
                           placeholder="phone"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
            </div>
        </div>

        {{-- تصاویر مراحل خدمات --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <h4 class="text-sm font-bold mb-1">تصاویر سکشن مراحل دریافت خدمات</h4>
            <p class="text-xs text-gray-500 mb-3">
                مقدار پیش‌فرض سراسری از
                <a href="{{ route('site.admin.page-content.edit', 'brand') }}" target="_blank" class="text-blue-600 hover:underline">الگوی brand</a>
                خوانده می‌شود. اگر این فیلدها را پر کنید، در صفحه‌ی این برند override می‌شود.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1">URL تصویر دسکتاپ</label>
                    <input type="text" name="steps_image_desktop" value="{{ old('steps_image_desktop', $brand->steps_image_desktop ?? '') }}" maxlength="500" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">URL تصویر موبایل</label>
                    <input type="text" name="steps_image_mobile" value="{{ old('steps_image_mobile', $brand->steps_image_mobile ?? '') }}" maxlength="500" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:bg-gray-700 rounded text-sm font-mono ltr">
                </div>
            </div>
        </div>

        {{-- ───── دستگاه‌های پشتیبانی‌شده توسط این برند ───── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            @include('crm::partials.multi-picker', [
                'name'        => 'device_ids',
                'items'       => $allDevices ?? collect(),
                'selectedIds' => $selectedDeviceIds ?? [],
                'label'       => 'دستگاه‌های قابل پشتیبانی توسط این برند',
                'help'        => 'دستگاه‌هایی را که این برند تعمیر می‌کند انتخاب کنید. در صفحه‌ی برند به‌جای سکشن «برندها»، این لیست نمایش داده می‌شود. اگر هیچ‌کدام انتخاب نشود، همه‌ی دستگاه‌های فعال نمایش داده می‌شوند.',
                'emptyText'   => 'دستگاه فعالی موجود نیست.',
            ])
        </div>

        {{-- ───── دسته‌بندی FAQ ───── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            @php
                $faqCatItems = ($allFaqCategories ?? collect())->map(fn ($c) => (object) [
                    'id' => (int) $c->id,
                    'label' => $c->name,
                    'slug' => $c->slug,
                    'description_text' => 'شامل ' . ($c->faqs_count ?? 0) . ' سوال منتشرشده.',
                    'badge' => 'دسته‌بندی',
                    'badge_color' => 'emerald',
                ]);
            @endphp
            @include('crm::partials.multi-picker', [
                'name'        => 'faq_category_ids',
                'items'       => $faqCatItems,
                'selectedIds' => $selectedFaqCategoryIds ?? [],
                'columns'     => 'wide',
                'label'       => 'دسته‌بندی سوالات متداول',
                'help'        => 'با انتخاب هر دسته‌بندی، تمام سوالات منتشرشده‌ی آن به‌صورت خودکار در صفحه‌ی این برند نمایش داده می‌شوند. ' .
                                 'مدیریت دسته‌ها: ' .
                                 '<a href="' . route('site.admin.taxonomies.index', 'faq') . '" target="_blank">/admin/site/taxonomies/faq</a>',
                'emptyText'   => 'دسته‌بندی فعالی برای FAQ تعریف نشده.',
            ])
        </div>

        {{-- ───── FAQ منفرد ───── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            @php
                $faqItems = ($allFaqs ?? collect())->map(fn ($f) => (object) [
                    'id' => $f->id,
                    'label' => $f->question,
                    'description_text' => \Illuminate\Support\Str::limit(strip_tags((string) ($f->answer ?? '')), 180),
                    'badge' => 'FAQ',
                    'badge_color' => 'indigo',
                ]);
            @endphp
            @include('crm::partials.multi-picker', [
                'name'        => 'faq_ids',
                'items'       => $faqItems,
                'selectedIds' => $selectedFaqIds ?? [],
                'columns'     => 'wide',
                'label'       => 'سوالات متداول این برند',
                'help'        => 'از بانک FAQ سوالاتی که می‌خواهید روی صفحه‌ی این برند نشان داده شوند انتخاب کنید. ' .
                                 'مدیریت بانک: ' .
                                 '<a href="' . route('site.admin.faqs.index') . '" target="_blank">/admin/site/faqs</a>',
                'emptyText'   => 'هنوز سوال منتشرشده‌ای در بانک FAQ نیست.',
            ])
        </div>

        {{-- ───── Reviews ───── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            @php
                $reviewItems = ($allReviews ?? collect())->map(function ($r) {
                    $isAudio = $r->type === \Modules\Site\Models\Review::TYPE_AUDIO;
                    $stars = str_repeat('★', (int) $r->rating) . str_repeat('☆', max(0, 5 - (int) $r->rating));
                    $body = $isAudio
                        ? ($r->topic ? $r->topic . ' (' . $stars . ')' : $stars)
                        : ($r->content ? $stars . ' — ' . \Illuminate\Support\Str::limit(strip_tags((string) $r->content), 180) : $stars);

                    return (object) [
                        'id' => $r->id,
                        'label' => $r->author_name,
                        'description_text' => $body,
                        'badge' => $isAudio ? 'صوتی' : 'متنی',
                        'badge_color' => $isAudio ? 'purple' : 'sky',
                    ];
                });
            @endphp
            @include('crm::partials.multi-picker', [
                'name'        => 'review_ids',
                'items'       => $reviewItems,
                'selectedIds' => $selectedReviewIds ?? [],
                'columns'     => 'wide',
                'label'       => 'دیدگاه‌های نمایش‌داده‌شده در این برند',
                'help'        => 'از بانک نظرات (شامل توصیه‌نامه‌های صوتی و نظرات متنی تأییدشده) انتخاب کنید. ' .
                                 'مدیریت بانک: ' .
                                 '<a href="' . route('site.admin.reviews.index') . '" target="_blank">/admin/site/reviews</a>',
                'emptyText'   => 'دیدگاه تأییدشده‌ای در بانک نیست.',
            ])
        </div>

        {{-- ───── فیلدهای legacy / مخفی (با CSS) — هنوز submit می‌شوند ───── --}}
        <div style="display:none">
            @include('crm::partials.json-repeater', [
                'name'  => 'stats',
                'label' => 'کارت‌های آمار (legacy)',
                'items' => old('stats', $brand->stats ?? []),
                'item_fields' => [
                    'value' => ['label' => 'مقدار', 'type' => 'string'],
                    'label' => ['label' => 'برچسب', 'type' => 'string'],
                ],
            ])
            @include('crm::partials.json-repeater', [
                'name'  => 'issues',
                'label' => 'مشکلات (legacy)',
                'items' => old('issues', $brand->issues ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                    'icon'        => ['label' => 'آیکن', 'type' => 'string'],
                ],
            ])
            @include('crm::partials.json-repeater', [
                'name'  => 'why_us',
                'label' => 'چرا ما (legacy)',
                'items' => old('why_us', $brand->why_us ?? []),
                'item_fields' => [
                    'title'       => ['label' => 'عنوان', 'type' => 'string'],
                    'description' => ['label' => 'توضیح', 'type' => 'textarea'],
                    'icon'        => ['label' => 'آیکن', 'type' => 'string'],
                ],
            ])
            @include('crm::partials.json-repeater', [
                'name'  => 'faq',
                'label' => 'سوالات inline (legacy)',
                'items' => old('faq', $brand->faq ?? []),
                'item_fields' => [
                    'question' => ['label' => 'سوال', 'type' => 'string'],
                    'answer'   => ['label' => 'پاسخ', 'type' => 'textarea'],
                ],
            ])
            <textarea name="warranty_text" rows="3">{{ old('warranty_text', $brand->warranty_text ?? '') }}</textarea>
            <textarea name="support_info" rows="3">{{ old('support_info', $brand->support_info ?? '') }}</textarea>
        </div>

        {{-- ───────── ویدیوهای اختصاصی این برند (override template) ───────── --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
            <div class="mb-2">
                <h4 class="text-sm font-semibold">ویدیوهای اختصاصی این برند</h4>
                <p class="text-xs text-gray-500 mt-1">
                    اگر خالی بگذارید، ویدیوهای پیش‌فرض تعریف‌شده در
                    <a href="{{ route('site.admin.page-content.edit', 'brand') }}" target="_blank" class="text-blue-600 hover:underline">الگوی brand</a>
                    نمایش داده می‌شوند. هر آیتم می‌تواند aparat_id یا youtube_id یا URL مستقیم mp4 داشته باشد (اولویت با aparat).
                </p>
            </div>
            @include('crm::partials.json-repeater', [
                'name' => 'videos',
                'label' => 'لیست ویدیوها',
                'items' => old('videos', $brand->videos ?? []),
                'item_fields' => [
                    'title' => ['label' => 'عنوان ویدیو', 'type' => 'string'],
                    'aparat_id' => ['label' => 'Aparat ID', 'type' => 'string'],
                    'youtube_id' => ['label' => 'YouTube ID', 'type' => 'string'],
                    'video_url' => ['label' => 'URL مستقیم (mp4)', 'type' => 'string'],
                    'description' => ['label' => 'توضیح کوتاه', 'type' => 'textarea'],
                    'poster_url' => ['label' => 'تصویر cover (URL)', 'type' => 'string'],
                ],
            ])
        </div>

        {{-- سئو --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $brand->meta_title ?? '') }}" maxlength="200"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Meta Description</label>
                <textarea name="meta_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $brand->meta_description ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

