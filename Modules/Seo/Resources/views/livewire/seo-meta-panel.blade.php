@php
    $titleLen = mb_strlen((string) ($this->preview['title'] ?? ''));
    $descLen = mb_strlen((string) ($this->preview['description'] ?? ''));
    $titleColor = $titleLen === 0 ? 'text-gray-400' : ($titleLen <= 60 ? 'text-emerald-600' : 'text-rose-600');
    $descColor = $descLen === 0 ? 'text-gray-400' : ($descLen <= 160 ? 'text-emerald-600' : 'text-rose-600');
@endphp

<div class="border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 p-4 space-y-4" dir="rtl">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-gray-800 dark:text-gray-100">تنظیمات سئو</h3>
        <span class="text-[11px] text-gray-400">نوع: {{ $type }}</span>
    </div>

    @if($saved)
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm p-2">{{ $saved }}</div>
    @endif

    {{-- پیش‌نمایش SERP --}}
    <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30 p-3">
        <p class="text-[11px] text-gray-400 mb-2">پیش‌نمایش نتایج گوگل</p>
        <div dir="ltr" class="text-right">
            <div class="text-emerald-700 text-xs truncate">{{ $this->preview['canonical'] ?? '' }}</div>
            <div class="text-blue-700 dark:text-blue-400 text-lg leading-6 truncate">{{ $this->preview['title'] ?? '' }}</div>
            <div class="text-gray-600 dark:text-gray-300 text-sm leading-5 line-clamp-2">{{ $this->preview['description'] ?? '' }}</div>
        </div>
        <div class="mt-1 text-[11px] text-gray-400">robots: <span class="font-mono">{{ $this->preview['robots'] ?? '' }}</span></div>
    </div>

    {{-- امتیاز و چک‌لیست سئو --}}
    @php
        $a = $this->analysis;
        $sc = (int) ($a['score'] ?? 0);
        $scClass = $sc >= 80 ? 'bg-emerald-100 text-emerald-700' : ($sc >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
        $dot = ['good' => 'bg-emerald-500', 'ok' => 'bg-amber-500', 'bad' => 'bg-rose-500'];
    @endphp
    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">امتیاز سئو</span>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $scClass }}">{{ $sc }}/100</span>
        </div>
        <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1">
            @foreach($a['checks'] as $c)
                <li class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $dot[$c['status']] ?? 'bg-gray-400' }}"></span>
                    <span>{{ $c['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- عنوان سئو --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">عنوان سئو</label>
            <span class="text-[11px] {{ $titleColor }}">{{ $titleLen }}/60</span>
        </div>
        <input type="text" wire:model.live.debounce.500ms="title"
               placeholder="مثلاً: %title% %sep% %sitename%"
               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        <p class="text-[11px] text-gray-400 mt-1">متغیرها: %title% %sitename% %sep% %excerpt% %date% %currentyear%</p>
    </div>

    {{-- توضیحات متا --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">توضیحات متا</label>
            <span class="text-[11px] {{ $descColor }}">{{ $descLen }}/160</span>
        </div>
        <textarea wire:model.live.debounce.500ms="description" rows="2"
                  placeholder="مثلاً: %excerpt%"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
    </div>

    {{-- کلمات کلیدی و canonical --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">کلمات کلیدی کانونی (با ، جدا کنید)</label>
            <input type="text" wire:model="focusKeywords"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Canonical (اختیاری)</label>
            <input type="text" wire:model="canonical" dir="ltr"
                   placeholder="خودکار از روی آدرس صفحه"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
    </div>

    {{-- Robots --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">دستورات Robots</label>
        <div class="flex flex-wrap gap-3 text-sm">
            @foreach([
                'robots_noindex' => 'noindex',
                'robots_nofollow' => 'nofollow',
                'robots_noarchive' => 'noarchive',
                'robots_noimageindex' => 'noimageindex',
                'robots_nosnippet' => 'nosnippet',
                'robots_notranslate' => 'notranslate',
            ] as $field => $label)
                <label class="inline-flex items-center gap-1.5">
                    <input type="checkbox" wire:model.live="{{ $field }}" class="rounded">
                    <span class="text-gray-600 dark:text-gray-300 font-mono text-xs">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <div class="mt-2">
            <label class="text-xs text-gray-500">max-image-preview:</label>
            <select wire:model.live="robots_max_image_preview" class="text-xs px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
                <option value="">—</option>
                <option value="none">none</option>
                <option value="standard">standard</option>
                <option value="large">large</option>
            </select>
        </div>
    </div>

    {{-- Open Graph --}}
    <details class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
        <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">شبکه‌های اجتماعی (Open Graph)</summary>
        <div class="mt-3 space-y-2">
            <input type="text" wire:model="og_title" placeholder="عنوان OG (پیش‌فرض: عنوان سئو)"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <textarea wire:model="og_description" rows="2" placeholder="توضیح OG (پیش‌فرض: توضیحات متا)"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
            <input type="text" wire:model="og_image" dir="ltr" placeholder="آدرس تصویر OG"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
    </details>

    {{-- Twitter --}}
    <details class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
        <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">Twitter Card</summary>
        <div class="mt-3 space-y-2">
            <select wire:model="twitter_card" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="summary">summary</option>
                <option value="summary_large_image">summary_large_image</option>
                <option value="app">app</option>
                <option value="player">player</option>
            </select>
            <input type="text" wire:model="twitter_title" placeholder="عنوان توییتر (پیش‌فرض: OG)"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <textarea wire:model="twitter_description" rows="2" placeholder="توضیح توییتر"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
            <input type="text" wire:model="twitter_image" dir="ltr" placeholder="آدرس تصویر توییتر"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
    </details>

    {{-- پیشرفته --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان بردکرامب</label>
            <input type="text" wire:model="breadcrumb_title"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
        <label class="inline-flex items-center gap-2 mt-6">
            <input type="checkbox" wire:model="is_pillar" class="rounded">
            <span class="text-sm text-gray-600 dark:text-gray-300">محتوای ستون (Pillar)</span>
        </label>
    </div>

    <div class="flex justify-end">
        <button type="button" wire:click="save"
                class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
            ذخیرهٔ سئو
        </button>
    </div>
</div>
