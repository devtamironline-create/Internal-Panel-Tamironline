@props([
    'name',                          // نام property در کامپوننت Livewire (مثل provinceId, cityId)
    'options' => [],                 // ['value' => 'label'] یا [['value'=>x,'label'=>y], ...]
    'placeholder' => '— انتخاب کنید —',
    'searchPlaceholder' => 'جستجو...',
    'disabled' => false,
    'live' => false,                 // true = wire:model.live (cascade triggers)
])

@php
    $list = collect($options)->map(function ($v, $k) {
        if (is_array($v) && isset($v['value'])) {
            return ['value' => (string) $v['value'], 'label' => (string) ($v['label'] ?? $v['value'])];
        }
        return ['value' => (string) $k, 'label' => (string) $v];
    })->values();
@endphp

{{-- Livewire-friendly searchable select. Avoids any wrapping of native
     <select> so Livewire morph stays happy. State is read/written via
     $wire to the Livewire property named $name. For wire:model.live
     style cascading, pass live="true". --}}
<div x-data="{
        open: false,
        query: '',
        options: @js($list),
        norm(s) { return String(s ?? '').replace(/[يﻱ]/g, 'ی').replace(/[كﻙ]/g, 'ک').toLowerCase().trim(); },
        currentValue() { return String($wire.get('{{ $name }}') ?? ''); },
        selectedLabel() {
            const v = this.currentValue();
            const m = this.options.find(o => o.value === v);
            return m ? m.label : '';
        },
        filtered() {
            const q = this.norm(this.query);
            return q ? this.options.filter(o => this.norm(o.label).includes(q)) : this.options;
        },
        pick(opt) {
            $wire.set('{{ $name }}', opt.value, {{ $live ? 'false' : 'true' }});
            this.open = false;
            this.query = '';
        },
    }"
    x-init="$watch('open', v => { if (!v) query = ''; })"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}>

    <button type="button"
            @click="open = !open"
            @if($disabled) disabled @endif
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm text-right flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
        <span class="truncate"
              x-text="selectedLabel() || @js($placeholder)"
              :class="!selectedLabel() && 'text-gray-400'"></span>
        <span class="text-xs text-gray-400 ms-2">▾</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden">
        <input type="text"
               x-model="query"
               x-init="$watch('open', v => v && setTimeout(() => $el.focus(), 30))"
               placeholder="{{ $searchPlaceholder }}"
               class="w-full px-3 py-2 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 text-sm focus:outline-none">
        <ul class="overflow-y-auto max-h-56">
            <template x-for="opt in filtered()" :key="opt.value">
                <li @click="pick(opt)"
                    x-text="opt.label"
                    :class="opt.value === currentValue() && 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 font-medium'"
                    class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"></li>
            </template>
            <li x-show="filtered().length === 0" class="px-3 py-2 text-sm text-gray-400">یافت نشد</li>
        </ul>
    </div>
</div>
