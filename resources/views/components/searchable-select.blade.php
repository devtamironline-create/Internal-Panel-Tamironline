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
@endphp

{{-- Livewire-friendly searchable select. No native <select> involved.
     value <-> $wire.{name} via x-modelable + wire:model. --}}
<div x-data='{
        open: false,
        query: "",
        value: "",
        options: @json($list),
        placeholder: @json($placeholder),
        searchPlaceholder: @json($searchPlaceholder),
        norm(s) { return String(s == null ? "" : s).replace(/[يﻱ]/g, "ی").replace(/[كﻙ]/g, "ک").toLowerCase().trim(); },
        selectedLabel() {
            var v = String(this.value == null ? "" : this.value);
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
            this.value = opt.value;
            this.open = false;
            this.query = "";
        },
    }'
    x-modelable="value"
    wire:model{{ $isLive ? '.live' : '' }}="{{ $name }}"
    x-init='$watch("open", function(v){ if (!v) { query = ""; } })'
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}>

    <button type="button"
            @click="open = !open"
            @if($isDisabled) disabled @endif
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-right flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
        <span class="truncate"
              x-text="selectedLabel() || placeholder"
              :class="!selectedLabel() && 'text-gray-400'"></span>
        <span class="text-xs text-gray-400 ms-2">▾</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden">
        <input type="text"
               x-model="query"
               x-init='$watch("open", function(v){ if (v) { setTimeout(function(){ $el.focus(); }, 30); } })'
               :placeholder="searchPlaceholder"
               class="w-full px-3 py-2 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 text-sm focus:outline-none">
        <ul class="overflow-y-auto max-h-56">
            <template x-for="opt in filtered()" :key="opt.value">
                <li @click="pick(opt)"
                    x-text="opt.label"
                    :class="opt.value === String(value == null ? '' : value) && 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 font-medium'"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"></li>
            </template>
            <li x-show="filtered().length === 0" class="px-3 py-2 text-sm text-gray-400">یافت نشد</li>
        </ul>
    </div>
</div>
