@php
    $f = $faq ?? null;
    $taxonomies = $taxonomies ?? collect();
    $selT = $selectedTaxonomies ?? [];
    $selTcmp = array_map('intval', $selT);

    // فهرست placeholderهای پشتیبانی‌شده در text/HTML سوال/پاسخ — این‌ها
    // در PageSectionService::applyPlaceholders() بر اساس صفحه‌ای که FAQ
    // در آن render می‌شود جایگزین می‌شوند.
    $placeholders = [
        ['key' => '{device}',       'label' => 'نام کوتاه دستگاه',  'example' => 'لباس‌شویی', 'available' => 'صفحات device و device×brand'],
        ['key' => '{device_label}', 'label' => 'نام کامل دستگاه',   'example' => 'ماشین لباس‌شویی', 'available' => 'صفحات device و device×brand'],
        ['key' => '{device_slug}',  'label' => 'slug دستگاه',       'example' => 'washing-machine', 'available' => 'صفحات device و device×brand'],
        ['key' => '{brand}',        'label' => 'نام برند',          'example' => 'سامسونگ', 'available' => 'صفحات brand و device×brand'],
        ['key' => '{brand_slug}',   'label' => 'slug برند',         'example' => 'samsung', 'available' => 'صفحات brand و device×brand'],
        ['key' => '{page_title}',   'label' => 'تیتر صفحه',         'example' => 'تعمیر لباس‌شویی', 'available' => 'همه‌ی صفحات'],
    ];
@endphp

@if($errors->any())
<div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
    <p class="font-bold mb-2">برخی فیلدها معتبر نیستند:</p>
    <ul class="list-disc pr-4 space-y-0.5">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
</div>
@endif

<div class="space-y-6">

    {{-- ─── Placeholder helper panel ─────────────────── --}}
    <details class="bg-gradient-to-l from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-200 dark:border-blue-800 rounded-xl overflow-hidden">
        <summary class="px-5 py-3 cursor-pointer text-sm font-bold text-blue-900 dark:text-blue-100 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Placeholderهای قابل استفاده در سوال و پاسخ
            <span class="mr-auto text-xs text-blue-600 font-normal">برای دیدن کلیک کنید</span>
        </summary>
        <div class="px-5 pb-4 pt-2">
            <p class="text-xs text-blue-800 dark:text-blue-200 mb-3 leading-relaxed">
                این placeholderها هنگام render در API بر اساس صفحه‌ی فرانت به‌صورت خودکار با مقدار واقعی جایگزین می‌شوند.
                مثلاً سوال «تعمیر {device} چقدر زمان می‌برد؟» در صفحه‌ی <code class="text-xs ltr" dir="ltr">/devices/washing-machine</code>
                به «تعمیر لباس‌شویی چقدر زمان می‌برد؟» تبدیل می‌شود.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2"
                 x-data="{ copied: '' }">
                @foreach($placeholders as $p)
                    <button type="button"
                        @click="navigator.clipboard.writeText('{{ $p['key'] }}'); copied='{{ $p['key'] }}'; setTimeout(()=>copied='', 1500);"
                        class="text-right p-2 rounded-lg bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 hover:border-blue-400 hover:shadow transition cursor-pointer">
                        <div class="flex items-center justify-between mb-1">
                            <code class="text-xs font-bold text-blue-700 dark:text-blue-300 ltr" dir="ltr">{{ $p['key'] }}</code>
                            <span class="text-[10px] text-green-600 font-bold transition-opacity"
                                  x-show="copied === '{{ $p['key'] }}'" x-cloak>کپی شد ✓</span>
                            <span class="text-[10px] text-gray-400" x-show="copied !== '{{ $p['key'] }}'">📋 کلیک = کپی</span>
                        </div>
                        <div class="text-xs text-gray-700 dark:text-gray-200 font-medium">{{ $p['label'] }}</div>
                        <div class="text-[10px] text-gray-500 mt-0.5">مثال: <span class="font-medium">{{ $p['example'] }}</span></div>
                        <div class="text-[10px] text-gray-400 mt-0.5">{{ $p['available'] }}</div>
                    </button>
                @endforeach
            </div>
        </div>
    </details>

    {{-- ─── سوال + پاسخ ─────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">محتوای سوال</h3>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">سوال <span class="text-red-500">*</span></label>
                <input type="text" name="question" value="{{ old('question', $f?->question) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                       required maxlength="255"
                       placeholder="مثال: تعمیر {device} چقدر زمان می‌برد؟">
                <p class="text-xs text-gray-500 mt-1">حداکثر ۲۵۵ کاراکتر. placeholderها در API جایگزین می‌شوند.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">پاسخ <span class="text-red-500">*</span></label>
                <textarea name="answer" rows="7" required maxlength="5000"
                          class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm leading-relaxed focus:ring-2 focus:ring-blue-500"
                          placeholder="پاسخ کامل سوال... می‌توانید از همان placeholderها استفاده کنید.">{{ old('answer', $f?->answer) }}</textarea>
                <p class="text-xs text-gray-500 mt-1">حداکثر ۵٬۰۰۰ کاراکتر. حفظ newlineها در فرانت با CSS <code class="text-xs ltr" dir="ltr">white-space: pre-line</code> توصیه می‌شود.</p>
            </div>
        </div>
    </div>

    {{-- ─── دسته‌بندی‌ها ───────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">دسته‌بندی‌های FAQ</h3>
            <a href="{{ route('site.admin.taxonomies.index', 'faq') }}" target="_blank"
               class="text-xs text-blue-600 hover:underline">مدیریت دسته‌ها ↗</a>
        </div>
        <div class="p-5">
            <p class="text-xs text-gray-500 mb-3">
                این سوال در همه‌ی صفحاتی که این دسته‌بندی‌ها را انتخاب کرده‌اند، نمایش داده می‌شود
                (پنل device/brand/device-brand → بخش «دسته‌بندی سوالات متداول»).
            </p>
            @php
                $taxoItems = $taxonomies->map(fn ($t) => (object) [
                    'id'      => (int) $t->id,
                    'label'   => $t->name,
                    'slug'    => $t->slug,
                    'badge'   => $t->is_active ? null : 'غیرفعال',
                    'badge_color' => 'amber',
                ]);
                $taxoSelected = old('taxonomy_ids', $selT) ?: [];
            @endphp
            @include('crm::partials.multi-picker', [
                'name'        => 'taxonomy_ids',
                'items'       => $taxoItems,
                'selectedIds' => $taxoSelected,
                'columns'     => 'wide',
                'label'       => 'انتخاب دسته‌بندی‌ها',
                'help'        => 'یک سوال می‌تواند در چند دسته باشد. خالی = generic (در همه‌جا قابل انتخاب).',
                'emptyText'   => 'دسته‌بندی FAQ تعریف نشده.',
            ])
        </div>
    </div>

    {{-- ─── تنظیمات انتشار ─────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">انتشار و ترتیب</h3>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">ترتیب نمایش</label>
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $f?->sort_order ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">عدد کوچک‌تر = نمایش بالاتر در لیست FAQ صفحه.</p>
            </div>
            <div class="flex items-end pb-1">
                <label class="inline-flex items-center gap-2 cursor-pointer p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition w-full">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $f?->is_published ?? true))
                           class="w-5 h-5 text-emerald-600 rounded">
                    <span class="text-sm font-medium text-emerald-900 dark:text-emerald-100">منتشر شود (در API و فرانت قابل مشاهده باشد)</span>
                </label>
            </div>
        </div>
    </div>

</div>
