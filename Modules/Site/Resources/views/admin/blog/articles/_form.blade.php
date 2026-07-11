@csrf
@php $a = $article ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
    <ul class="list-disc pr-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{--
    هر باکس یک آکوردین است: روی هدر کلیک کنید تا باز/بسته شود (x-collapse).
    کامپوننتِ بازشونده: <x-accordion>-مانند با x-data محلی. باکسِ «محتوا»
    به‌صورت پیش‌فرض باز است تا TinyMCE درست مقداردهی شود.
--}}
@php
    // هدرِ آکوردین — برای DRY بودن، فقط کلاس‌ها این‌جا تعریف شده‌اند.
    $boxClass = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden';
    $headBtn = 'w-full flex items-center justify-between gap-2 px-4 py-3 text-start hover:bg-gray-50 dark:hover:bg-gray-700/40';
@endphp

{{-- چیدمان کلاسیک وردپرس: محتوای اصلی (راست) + ستونِ تنظیماتِ چسبان (چپ) --}}
<div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">

    {{-- ─── ستون اصلی ─── --}}
    <div class="space-y-5 min-w-0">

        {{-- اطلاعات پایه --}}
        <div class="{{ $boxClass }}" x-data="accBox(true)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }}">
                <h4 class="text-sm font-bold">اطلاعات پایه</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-5 pt-0 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">عنوان مقاله <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $a?->title) }}" required maxlength="250"
                               placeholder="عیب‌یابی پکیج ایران رادیاتور | راهنمای جامع و کاربردی"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-base font-bold focus:ring-2 focus:ring-violet-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Slug (خالی = خودکار از عنوان)</label>
                            <input type="text" name="slug" value="{{ old('slug', $a?->slug) }}" maxlength="200" dir="auto"
                                   placeholder="تعمیر-پکیج یا iran-radiator-common-problems"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono">
                            <p class="text-xs text-gray-500 mt-1">
                                فارسی یا انگلیسی مجاز است (حروف، عدد، خط تیره). مثال: <code>تعمیر-پکیج</code>.
                                آدرس در سایت: <code class="ltr" dir="ltr">{{ \Modules\Seo\Models\SeoSetting::siteUrl() }}/blog/{slug}</code>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">زمان مطالعه (دقیقه)</label>
                            <input type="number" name="read_time_minutes" value="{{ old('read_time_minutes', $a?->read_time_minutes) }}" min="1" max="600"
                                   placeholder="12"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">خلاصه (excerpt)</label>
                        <textarea name="excerpt" rows="2" maxlength="600"
                                  placeholder="با راهنمای کامل عیب‌یابی، بدون نیاز به متخصص..."
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('excerpt', $a?->excerpt) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">برای کارت‌های لیست بلاگ نمایش داده می‌شود.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- محتوای مقاله (پیش‌فرض باز — برای TinyMCE) --}}
        <div class="{{ $boxClass }}" x-data="accBox(true)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }}">
                <h4 class="text-sm font-bold">محتوای مقاله</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-5 pt-0">
                    <textarea name="content" rows="24"
                              class="rich-editor w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('content', $a?->content) }}</textarea>
                    <p class="text-xs text-gray-500 mt-2">از TinyMCE استفاده کنید — تیتر، فهرست، تصویر، embed یوتیوب/آپارات، جدول، کد، ...</p>
                </div>
            </div>
        </div>

        {{-- دسته‌بندی — دستگاهِ الصاق‌شده = دستهٔ مقاله --}}
        <div class="{{ $boxClass }}" x-data="accBox(true)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }}">
                <h4 class="text-sm font-bold">دسته‌بندی مقاله (دستگاه = دسته / برند)</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-5 pt-0 space-y-6">
                    <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 px-3 py-2 text-xs text-indigo-800 dark:text-indigo-200 leading-6">
                        دستگاهِ الصاق‌شده به مقاله، <b>دستهٔ آن</b> است. اولین دستگاه = دستهٔ اصلی و مبنایِ مسیرِ سایت
                        (خانه › مقالات › <b>مقالات {دستگاه}</b> › عنوانِ مقاله). فقط تایپ کنید و انتخاب کنید.
                    </div>

                    @include('crm::partials.multi-picker', [
                        'name' => 'device_ids',
                        'items' => $allDevices ?? collect(),
                        'selectedIds' => $selectedDeviceIds ?? [],
                        'label' => 'دستهٔ مقاله — دستگاه',
                        'help' => 'دستگاهِ اصلی (اولین انتخاب) دستهٔ مقاله است. می‌توانید چند دستگاه هم بزنید.',
                        'emptyText' => 'دستگاهی موجود نیست.',
                    ])

                    @include('crm::partials.multi-picker', [
                        'name' => 'brand_ids',
                        'items' => $allBrands ?? collect(),
                        'selectedIds' => $selectedBrandIds ?? [],
                        'label' => 'برند (اختیاری)',
                        'help' => 'اگر مقاله دربارهٔ یک برندِ خاص است، آن را هم بزنید.',
                        'emptyText' => 'برندی موجود نیست.',
                    ])

                    {{-- تاپیک‌ها اختیاری‌اند — دسته‌بندی با دستگاه انجام می‌شود. --}}
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
                        'label' => 'تاپیک‌ها (اختیاری)',
                        'help' => 'اختیاری — برای برچسب/فیلترِ فرعی. برای دسته‌بندی نیازی نیست؛ دسته‌بندی با دستگاه انجام می‌شود.',
                        'emptyText' => 'تاپیکی تعریف نشده (اختیاری).',
                    ])
                </div>
            </div>
        </div>
    </div>

    {{-- ─── سایدبارِ چسبان: انتشار، کاور، سئو ─── --}}
    <div class="space-y-4 lg:sticky lg:top-20">

        {{-- جعبه‌ی انتشار (پیش‌فرض باز) --}}
        <div class="{{ $boxClass }}" x-data="accBox(true)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }} bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-bold">انتشار</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-4 space-y-3">
                    <label class="inline-flex items-center gap-2 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 w-full cursor-pointer">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $a?->is_published ?? false))
                               class="w-5 h-5 text-emerald-600 rounded">
                        <span class="text-sm font-medium text-emerald-900 dark:text-emerald-200">منتشر شود</span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium mb-1">تاریخ انتشار (شمسی)</label>
                        <input type="text" name="published_at_jalali" dir="ltr" autocomplete="off"
                               value="{{ old('published_at_jalali', $a?->published_at ? \Morilog\Jalali\Jalalian::fromDateTime($a->published_at)->format('Y/m/d') : '') }}"
                               placeholder="۱۴۰۵/۰۳/۳۱"
                               class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm ltr">
                        <p class="text-xs text-gray-500 mt-1">خالی + «منتشر شود» = همین حالا.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
                        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $a?->sort_order ?? 0) }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                </div>
            </div>
            {{-- دکمه‌ی ذخیره همیشه بیرونِ آکوردین می‌ماند تا همیشه در دسترس باشد --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-bold hover:bg-violet-700">ذخیره مقاله</button>
                <a href="{{ route('site.admin.blog.articles.index') }}" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm hover:bg-gray-200">انصراف</a>
            </div>
        </div>

        {{-- جعبه‌ی تصویر کاور --}}
        <div class="{{ $boxClass }}" x-data="accBox(true)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }} bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-bold">تصویر کاور</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-4 space-y-3">
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
                        'previewSize' => 'w-full h-32',
                    ])
                    <div>
                        <label class="block text-xs font-medium mb-1">یا URL مستقیم</label>
                        <input type="text" name="cover_image" value="{{ old('cover_image', $a?->cover_image) }}" maxlength="500" dir="ltr"
                               placeholder="https://..."
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">رنگ پس‌زمینه placeholder (hex)</label>
                        <input type="text" name="cover_color" value="{{ old('cover_color', $a?->cover_color) }}" maxlength="9" dir="ltr"
                               placeholder="#ffe4e6"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono ltr">
                    </div>
                </div>
            </div>
        </div>

        {{-- جعبه‌ی سئو --}}
        <div class="{{ $boxClass }}" x-data="accBox(false)">
            <button type="button" @click="toggle($event)" class="{{ $headBtn }} bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-bold">سئو</h4>
                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-collapse>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Meta Title</label>
                        <input type="text" name="meta_title" value="{{ old('meta_title', $a?->meta_title) }}" maxlength="250"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Meta Description</label>
                        <textarea name="meta_description" rows="3" maxlength="500"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('meta_description', $a?->meta_description) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- نوار ذخیره‌ی موبایل --}}
<div class="lg:hidden sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-3 -mx-6 mt-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-2">
    <button type="submit" class="flex-1 px-5 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-bold hover:bg-violet-700">ذخیره مقاله</button>
    <a href="{{ route('site.admin.blog.articles.index') }}" class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</a>
</div>

<script>
    // آکوردینِ مقاوم در برابر دوبار-اجرا: در صفحاتی که Livewire هم Alpine
    // خودش را لود می‌کند، هندلرِ @click دوبار اجرا می‌شود؛ این guard هر کلیک
    // را فقط یک‌بار اعمال می‌کند تا open دوبار toggle (و خنثی) نشود.
    function accBox(open) {
        return {
            open: open,
            toggle(e) {
                if (e) { if (e.__accHandled) return; e.__accHandled = true; }
                this.open = ! this.open;
            },
        };
    }
</script>

