@php
    /**
     * Multi-picker قابل استفاده برای انتخاب چندتایی برند/دستگاه/FAQ/Review و موارد مشابه.
     *
     * Props:
     *   $name        — نام فیلد فرم (مثل 'device_ids', 'faq_ids', 'review_ids')
     *   $items       — Collection|array از آیتم‌ها (هر آیتم می‌تواند داشته باشد:
     *                  id, label/name/question, slug, image/logo/thumbnail,
     *                  description (پیش‌نمایش متنی), badge (متن chip), badge_color)
     *   $selectedIds — array از idهای انتخاب‌شده (int یا string ULID)
     *   $label       — متن لیبل بالای picker
     *   $help        — راهنمای زیر لیبل (اختیاری)
     *   $emptyText   — متنی که اگر آیتمی نباشد نمایش داده می‌شود
     *   $columns     — 'compact' (4 ستون، برای آیتم‌های تصویری) |
     *                  'wide'    (2 ستون، برای متن طولانی)
     */
    $name        = $name ?? 'ids';
    $label       = $label ?? 'انتخاب';
    $help        = $help ?? null;
    $emptyText   = $emptyText ?? 'موردی برای نمایش وجود ندارد.';
    $items       = $items ?? collect();
    $selectedIds = array_values((array) ($selectedIds ?? []));
    $columns     = $columns ?? 'compact';
    $colsClass   = $columns === 'wide'
        ? 'grid-cols-1 lg:grid-cols-2'
        : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';

    // نرمال‌سازی آیتم‌ها به آرایه‌ی ساده برای Alpine
    $itemsArr = collect($items)->map(function ($i) {
        $id = is_object($i) ? ($i->id ?? null) : ($i['id'] ?? null);

        return [
            'id'          => $id,
            'label'       => (string) (data_get($i, 'label') ?? data_get($i, 'name') ?? data_get($i, 'question') ?? '—'),
            'slug'        => (string) (data_get($i, 'slug') ?? ''),
            'image'       => data_get($i, 'image') ?? data_get($i, 'logo') ?? data_get($i, 'thumbnail') ?? null,
            'description' => data_get($i, 'description_text'),
            'badge'       => data_get($i, 'badge'),
            'badge_color' => data_get($i, 'badge_color') ?? 'gray',
        ];
    })->values()->all();
@endphp

<div x-data="{
        search: '',
        selected: @js($selectedIds),
        items: @js($itemsArr),
        toggle(id, e) {
            // محافظ در برابر دوبار-اجرا شدن هندلر در صفحاتی که دو نمونه‌ی Alpine
            // فعال است (Livewire + standalone): هر کلیک فقط یک‌بار اعمال شود،
            // وگرنه toggle دوبار اجرا شده و انتخاب «خنثی» می‌شود.
            if (e) { if (e.__mpHandled) return; e.__mpHandled = true; }
            const idx = this.selected.findIndex(x => x == id);
            if (idx === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(idx, 1);
            }
        },
        isSelected(id) { return this.selected.some(x => x == id); },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.items;
            return this.items.filter(i =>
                (i.label || '').toLowerCase().includes(q)
                || (i.slug || '').toLowerCase().includes(q)
                || (i.description || '').toLowerCase().includes(q)
            );
        },
        selectAll() {
            this.filtered.forEach(i => {
                if (!this.isSelected(i.id)) this.selected.push(i.id);
            });
        },
        clearAll() { this.selected = []; },
        badgeClass(color) {
            const map = {
                purple: 'bg-purple-100 text-purple-700',
                sky:    'bg-sky-100 text-sky-700',
                amber:  'bg-amber-100 text-amber-700',
                emerald:'bg-emerald-100 text-emerald-700',
                rose:   'bg-rose-100 text-rose-700',
                indigo: 'bg-indigo-100 text-indigo-700',
                gray:   'bg-gray-100 text-gray-700',
            };
            return map[color] || map.gray;
        }
     }"
     class="space-y-2">

    {{-- لیبل + شمارش و اکشن‌ها --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ $label }}</label>
            @if($help)
                <p class="text-xs text-gray-500 mt-1 [&_a]:text-blue-600 [&_a]:hover:underline">{!! $help !!}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-700">
                <span x-text="selected.length"></span> / {{ count($itemsArr) }} انتخاب‌شده
            </span>
            <button type="button" @click="selectAll()" class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-700">انتخاب همه</button>
            <button type="button" @click="clearAll()" class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-700">پاک‌کردن</button>
        </div>
    </div>

    {{-- جستجو --}}
    <div class="relative">
        <input type="text" x-model.debounce.150ms="search" placeholder="جستجو..."
               class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
        </svg>
    </div>

    {{-- گرید کارت‌ها --}}
    <div class="grid {{ $colsClass }} gap-2 max-h-[28rem] overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900">
        <template x-for="item in filtered" :key="item.id">
            <label
                @click.prevent="toggle(item.id, $event)"
                class="relative flex items-start gap-3 p-3 bg-white dark:bg-gray-800 border-2 rounded-lg cursor-pointer transition hover:shadow-md"
                :class="isSelected(item.id) ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-800' : 'border-gray-200 dark:border-gray-700'">

                {{-- چک‌مارک گوشه --}}
                <span class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full flex items-center justify-center text-white text-xs"
                      :class="isSelected(item.id) ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'">
                    <span x-show="isSelected(item.id)">✓</span>
                </span>

                {{-- تصویر یا placeholder --}}
                <template x-if="item.image">
                    <img :src="item.image" :alt="item.label" loading="lazy"
                         class="w-12 h-12 rounded object-contain bg-white border border-gray-100 shrink-0">
                </template>
                <template x-if="!item.image">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-700 flex items-center justify-center text-blue-700 dark:text-blue-200 font-bold text-sm shrink-0">
                        <span x-text="(item.label || '?').charAt(0)"></span>
                    </div>
                </template>

                {{-- محتوای کارت --}}
                <div class="min-w-0 flex-1">
                    {{-- badge بالای تیتر --}}
                    <template x-if="item.badge">
                        <span class="inline-block mb-1 px-2 py-0.5 rounded text-[10px] font-bold"
                              :class="badgeClass(item.badge_color)" x-text="item.badge"></span>
                    </template>
                    {{-- تیتر --}}
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug" x-text="item.label"></div>
                    {{-- slug --}}
                    <template x-if="item.slug">
                        <div class="text-xs text-gray-500 font-mono truncate ltr mt-0.5" dir="ltr" x-text="item.slug"></div>
                    </template>
                    {{-- description / پیش‌نمایش --}}
                    <template x-if="item.description">
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed line-clamp-3" x-text="item.description"></p>
                    </template>
                </div>
            </label>
        </template>

        {{-- خالی --}}
        <div x-show="filtered.length === 0" class="col-span-full text-center text-sm text-gray-400 py-6">
            <template x-if="items.length === 0">
                <span>{{ $emptyText }}</span>
            </template>
            <template x-if="items.length > 0">
                <span>هیچ موردی با عبارت «<span x-text="search"></span>» پیدا نشد.</span>
            </template>
        </div>
    </div>

    {{-- hidden inputs برای ارسال در form — فقط برای انتخاب‌شده‌ها (DOM-stripped) --}}
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>
</div>
