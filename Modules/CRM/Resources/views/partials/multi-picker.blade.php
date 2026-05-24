@php
    /**
     * Multi-picker قابل استفاده برای انتخاب چندتایی برند/دستگاه/... با UI کارتی.
     *
     * Props:
     *   $name        — نام فیلد فرم (مثل 'device_ids' یا 'brand_ids')
     *   $items       — Collection|array از آیتم‌ها (هر آیتم باید id, label, slug, image داشته باشد)
     *   $selectedIds — array<int> از idهای انتخاب‌شده
     *   $label       — متن لیبل بالای picker
     *   $help        — راهنمای زیر لیبل (اختیاری)
     *   $emptyText   — متنی که اگر آیتمی نباشد نمایش داده می‌شود
     *
     * هر آیتم به این شکل نرمالایز می‌شود:
     *   ['id' => int, 'label' => 'name', 'slug' => 'kebab', 'image' => 'url|null']
     */
    $name        = $name ?? 'ids';
    $label       = $label ?? 'انتخاب';
    $help        = $help ?? null;
    $emptyText   = $emptyText ?? 'موردی برای نمایش وجود ندارد.';
    $items       = $items ?? collect();
    $selectedIds = array_values(array_map('intval', (array) ($selectedIds ?? [])));

    // نرمال‌سازی آیتم‌ها به آرایه‌ی ساده برای Alpine
    $itemsArr = collect($items)->map(fn ($i) => [
        'id'    => (int) ($i->id ?? 0),
        'label' => (string) ($i->label ?? $i->name ?? '—'),
        'slug'  => (string) ($i->slug ?? ''),
        'image' => $i->image ?? $i->logo ?? $i->thumbnail ?? null,
    ])->values()->all();
@endphp

<div x-data="{
        search: '',
        selected: @js($selectedIds),
        items: @js($itemsArr),
        toggle(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(x => x !== id);
            } else {
                this.selected.push(id);
            }
        },
        isSelected(id) { return this.selected.includes(id); },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.items;
            return this.items.filter(i =>
                i.label.toLowerCase().includes(q) || i.slug.toLowerCase().includes(q)
            );
        },
        selectAll() { this.selected = this.items.map(i => i.id); },
        clearAll() { this.selected = []; },
     }"
     class="space-y-2">

    {{-- لیبل + شمارش و اکشن‌ها --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ $label }}</label>
            @if($help)
                <p class="text-xs text-gray-500 mt-1">{{ $help }}</p>
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
        <input type="text" x-model.debounce.150ms="search" placeholder="جستجو در نام یا slug..."
               class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
        </svg>
    </div>

    {{-- گرید کارت‌ها --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 max-h-96 overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900">
        <template x-for="item in filtered" :key="item.id">
            <label
                @click.prevent="toggle(item.id)"
                class="relative flex items-center gap-3 p-3 bg-white dark:bg-gray-800 border-2 rounded-lg cursor-pointer transition hover:shadow-md"
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
                    <div class="w-12 h-12 rounded bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center text-gray-400 text-xs shrink-0">
                        <span x-text="item.label.charAt(0)"></span>
                    </div>
                </template>

                {{-- نام + slug --}}
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="item.label"></div>
                    <div class="text-xs text-gray-500 font-mono truncate ltr" dir="ltr" x-text="item.slug"></div>
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
