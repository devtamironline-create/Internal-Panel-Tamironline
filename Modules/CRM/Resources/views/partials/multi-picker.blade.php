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
     *
     * انتخاب با «چک‌باکسِ نیتیو» انجام می‌شود (نه @click آلپاین). این عمداً است:
     * در صفحاتی که Livewire هم اجرا می‌شود دو نمونه‌ی Alpine فعال است و هندلرهای
     * @click دوبار اجرا می‌شوند و انتخاب/حذف «خنثی» می‌شد. چک‌باکسِ نیتیو را
     * مرورگر toggle می‌کند و از این باگ مصون است؛ استایل با Tailwind `peer`.
     */
    $name        = $name ?? 'ids';
    $label       = $label ?? 'انتخاب';
    $help        = $help ?? null;
    $emptyText   = $emptyText ?? 'موردی برای نمایش وجود ندارد.';
    $items       = $items ?? collect();
    $selectedIds = array_map('strval', array_values((array) ($selectedIds ?? [])));
    $columns     = $columns ?? 'compact';
    $colsClass   = $columns === 'wide'
        ? 'grid-cols-1 lg:grid-cols-2'
        : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';

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

    // آرایه‌ی سبک برای فیلترِ جستجو در Alpine (فقط برای تشخیصِ «چیزی پیدا نشد»).
    $searchIndex = collect($itemsArr)->map(fn ($i) => [
        'label' => mb_strtolower($i['label']),
        'slug'  => mb_strtolower($i['slug']),
    ])->all();

    $badgeMap = [
        'purple' => 'bg-purple-100 text-purple-700',
        'sky'    => 'bg-sky-100 text-sky-700',
        'amber'  => 'bg-amber-100 text-amber-700',
        'emerald'=> 'bg-emerald-100 text-emerald-700',
        'rose'   => 'bg-rose-100 text-rose-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
        'gray'   => 'bg-gray-100 text-gray-700',
    ];
@endphp

<div x-data="{
        search: '',
        count: 0,
        index: @js($searchIndex),
        matches(label, slug) {
            const q = this.search.trim().toLowerCase();
            if (!q) return true;
            return (label || '').includes(q) || (slug || '').includes(q);
        },
        anyVisible() { return this.index.some(i => this.matches(i.label, i.slug)); },
        recount() { this.count = this.$root.querySelectorAll('.mp-check:checked').length; },
        selectAllVisible() {
            this.$root.querySelectorAll('.mp-card').forEach(card => {
                if (card.style.display !== 'none') {
                    const c = card.querySelector('.mp-check');
                    if (c) c.checked = true;
                }
            });
            this.recount();
        },
        clearAll() {
            this.$root.querySelectorAll('.mp-check').forEach(c => { c.checked = false; });
            this.recount();
        }
     }"
     x-init="recount()"
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
                <span x-text="count"></span> / {{ count($itemsArr) }} انتخاب‌شده
            </span>
            <button type="button" @click="selectAllVisible()" class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-700">انتخاب همه</button>
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

    {{-- گرید کارت‌ها — چک‌باکسِ نیتیو + استایلِ peer --}}
    <div class="grid {{ $colsClass }} gap-2 max-h-[28rem] overflow-y-auto p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900">
        @foreach($itemsArr as $it)
            @php
                $lbl = mb_strtolower($it['label']);
                $slg = mb_strtolower($it['slug']);
            @endphp
            <label class="mp-card relative block cursor-pointer"
                   data-label="{{ $lbl }}" data-slug="{{ $slg }}"
                   x-show="matches($el.dataset.label, $el.dataset.slug)">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $it['id'] }}"
                       class="mp-check peer sr-only" @checked(in_array((string) $it['id'], $selectedIds, true)) @change="recount()">

                {{-- چک‌مارک گوشه (خواهرِ مستقیمِ چک‌باکس تا peer-checked کار کند) --}}
                <span class="absolute top-1.5 left-1.5 z-10 w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-600 text-white text-xs hidden items-center justify-center peer-checked:flex peer-checked:bg-blue-600">✓</span>

                {{-- دکمهٔ حذف (فقط وقتی انتخاب شده) — کلیک روی آن هم چک‌باکس را toggle می‌کند --}}
                <span class="absolute top-1.5 right-1.5 z-10 w-5 h-5 rounded-full bg-rose-100 text-rose-600 text-xs font-bold hidden items-center justify-center peer-checked:flex" title="حذف از انتخاب">✕</span>

                {{-- بدنهٔ کارت --}}
                <div class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-lg transition hover:shadow-md peer-checked:border-blue-500 peer-checked:ring-2 peer-checked:ring-blue-200 dark:peer-checked:ring-blue-800">
                    @if($it['image'])
                        <img src="{{ $it['image'] }}" alt="{{ $it['label'] }}" loading="lazy"
                             class="w-12 h-12 rounded object-contain bg-white border border-gray-100 shrink-0">
                    @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-700 flex items-center justify-center text-blue-700 dark:text-blue-200 font-bold text-sm shrink-0">
                            {{ mb_substr($it['label'] ?: '?', 0, 1) }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if($it['badge'])
                            <span class="inline-block mb-1 px-2 py-0.5 rounded text-[10px] font-bold {{ $badgeMap[$it['badge_color']] ?? $badgeMap['gray'] }}">{{ $it['badge'] }}</span>
                        @endif
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug">{{ $it['label'] }}</div>
                        @if($it['slug'])
                            <div class="text-xs text-gray-500 font-mono truncate ltr mt-0.5" dir="ltr">{{ $it['slug'] }}</div>
                        @endif
                        @if($it['description'])
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed line-clamp-3">{{ $it['description'] }}</p>
                        @endif
                    </div>
                </div>
            </label>
        @endforeach

        {{-- خالی / بدونِ نتیجه --}}
        <div x-show="!anyVisible()" class="col-span-full text-center text-sm text-gray-400 py-6">
            @if(count($itemsArr) === 0)
                <span>{{ $emptyText }}</span>
            @else
                <span>هیچ موردی با عبارت «<span x-text="search"></span>» پیدا نشد.</span>
            @endif
        </div>
    </div>

    <p class="text-[11px] text-gray-400" x-show="count > 0">
        برای حذفِ یک مورد از انتخاب، روی همان کارت (یا ✕ گوشه‌اش) دوباره کلیک کنید.
    </p>
</div>
