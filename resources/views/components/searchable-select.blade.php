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
    // JSON for in-attribute use. JSON_HEX_APOS turns ' into ' so we
    // can safely sit inside a single-quoted attribute. JSON_UNESCAPED_UNICODE
    // keeps Persian characters readable.
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_APOS;
    $optionsJson = json_encode($list, $jsonFlags);
    $placeholderJson = json_encode($placeholder, $jsonFlags);
    $searchPlaceholderJson = json_encode($searchPlaceholder, $jsonFlags);
    $nameJson = json_encode($name, $jsonFlags);
@endphp

<div x-data='{
        open: false,
        query: "",
        currentValue: "",
        options: {!! $optionsJson !!},
        placeholder: {!! $placeholderJson !!},
        searchPlaceholder: {!! $searchPlaceholderJson !!},
        init() {
            try {
                var v = this.$wire.get({!! $nameJson !!});
                if (v != null && v !== "") this.currentValue = String(v);
            } catch (e) {}
            var self = this;
            this.$watch("open", function(v){
                if (v) { setTimeout(function(){ self.$refs.searchBox && self.$refs.searchBox.focus(); }, 30); }
                else { self.query = ""; }
            });
        },
        norm(s) {
            return String(s == null ? "" : s).replace(/[يﻱ]/g, "ی").replace(/[كﻙ]/g, "ک").toLowerCase().trim();
        },
        selectedLabel() {
            var v = String(this.currentValue == null ? "" : this.currentValue);
            var m = this.options.find(function(o){ return o.value === v; });
            return m ? m.label : "";
        },
        filtered() {
            var q = this.norm(this.query);
            if (!q) return this.options;
            var self = this;
            return this.options.filter(function(o){ return self.norm(o.label).indexOf(q) !== -1; });
        },
        pick(opt) {
            this.currentValue = opt.value;
            this.open = false;
            this.query = "";
            this.$wire.set({!! $nameJson !!}, opt.value, {{ $isLive ? 'false' : 'true' }});
        },
    }'
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}>

    <button type="button"
            @click="open = !open"
            @if($isDisabled) disabled @endif
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-right flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
        <span class="truncate"
              x-text="selectedLabel() || placeholder"
              :class="{ 'text-gray-400': !selectedLabel() }"></span>
        <span class="text-xs text-gray-400 ms-2">▾</span>
    </button>

    <div x-show="open" x-cloak
         class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden">
        <input type="text"
               x-model="query"
               x-ref="searchBox"
               :placeholder="searchPlaceholder"
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
