@props([
    'name',
    'options' => [],
    'placeholder' => '— انتخاب کنید —',
    'searchPlaceholder' => 'جستجو...',
    'disabled' => false,
    'live' => false,
])

@php
    $list = collect($options)->map(function ($v, $k) {
        if (is_array($v) && isset($v['value'])) {
            return ['value' => (string) $v['value'], 'label' => (string) ($v['label'] ?? $v['value'])];
        }
        return ['value' => (string) $k, 'label' => (string) $v];
    })->values()->toArray();
    $isLive = filter_var($live, FILTER_VALIDATE_BOOLEAN);
    $isDisabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);

    // Encode payload for Alpine via base64 — completely avoids any HTML
    // attribute quoting issues with Persian text + special chars.
    $b64 = base64_encode(json_encode([
        'name' => (string) $name,
        'options' => $list,
        'placeholder' => (string) $placeholder,
        'searchPlaceholder' => (string) $searchPlaceholder,
        'live' => $isLive,
    ], JSON_UNESCAPED_UNICODE));
@endphp

<div x-data="searchableSelectComponent('{{ $b64 }}')"
     @click.outside="open = false"
     {{ $attributes->merge(['class' => 'relative']) }}>

    <button type="button"
            @click="toggle()"
            @if($isDisabled) disabled @endif
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-right flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
        <span class="truncate"
              x-text="selectedLabel() || cfg.placeholder"
              :class="{ 'text-gray-400': !selectedLabel() }"></span>
        <span class="text-xs text-gray-400 ms-2">▾</span>
    </button>

    <div x-show="open" x-cloak
         class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden">
        <input type="text"
               x-model="query"
               x-ref="searchBox"
               :placeholder="cfg.searchPlaceholder"
               class="w-full px-3 py-2 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 text-sm focus:outline-none">
        <ul class="overflow-y-auto max-h-56">
            <template x-for="opt in filtered()" :key="opt.value">
                <li @click="pick(opt)"
                    x-text="opt.label"
                    :class="{ 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 font-medium': opt.value === currentValue }"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"></li>
            </template>
            <li x-show="filtered().length === 0" class="px-3 py-2 text-sm text-gray-400">یافت نشد</li>
        </ul>
    </div>
</div>

@once
@push('scripts')
<script>
window.searchableSelectComponent = function (b64) {
    var cfg;
    try {
        cfg = JSON.parse(decodeURIComponent(escape(atob(b64))));
    } catch (e) {
        cfg = { name: '', options: [], placeholder: '', searchPlaceholder: '', live: false };
    }
    return {
        cfg: cfg,
        open: false,
        query: '',
        currentValue: '',
        init() {
            var self = this;
            try {
                if (this.$wire && typeof this.$wire.get === 'function') {
                    var v = this.$wire.get(this.cfg.name);
                    if (v != null) self.currentValue = String(v);
                }
            } catch (e) {}
            this.$watch('open', function (v) {
                if (v) {
                    setTimeout(function () { self.$refs.searchBox && self.$refs.searchBox.focus(); }, 30);
                } else {
                    self.query = '';
                }
            });
        },
        toggle() { this.open = !this.open; },
        norm(s) {
            return String(s == null ? '' : s).replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim();
        },
        selectedLabel() {
            var v = String(this.currentValue == null ? '' : this.currentValue);
            var m = (this.cfg.options || []).find(function (o) { return o.value === v; });
            return m ? m.label : '';
        },
        filtered() {
            var q = this.norm(this.query);
            var opts = this.cfg.options || [];
            if (!q) return opts;
            var self = this;
            return opts.filter(function (o) { return self.norm(o.label).indexOf(q) !== -1; });
        },
        pick(opt) {
            this.currentValue = opt.value;
            this.open = false;
            this.query = '';
            try {
                if (this.$wire && typeof this.$wire.set === 'function') {
                    this.$wire.set(this.cfg.name, opt.value, !this.cfg.live);
                }
            } catch (e) {}
        },
    };
};
</script>
@endpush
@endonce
