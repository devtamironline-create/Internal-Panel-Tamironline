{{--
    Reusable Alpine-powered JSON repeater for CRM admin forms.

    Required:
      $name        — base field name (e.g. "stats", "issues")
      $items       — array of current items (already array, not JSON string)
      $label       — visible label
      $item_fields — assoc array of sub-field config:
                     ['key' => ['label' => 'Label', 'type' => 'string'|'textarea']]
    Optional:
      $help        — help text under the field
      $min         — minimum visible items (default 1)
--}}
@php
    $items = is_array($items ?? null) ? array_values($items) : [];
    if (empty($items)) { $items = [[]]; }
    $help = $help ?? null;
    $alpineId = 'rep_' . preg_replace('/[^a-z0-9]/i', '_', $name);
@endphp

<div
    x-data="{
        items: @js($items),
        add() { this.items.push({}); },
        remove(i) { this.items.splice(i, 1); if(this.items.length===0) this.add(); },
        moveUp(i) { if(i===0) return; const x = this.items.splice(i,1)[0]; this.items.splice(i-1,0,x); },
        moveDown(i) { if(i===this.items.length-1) return; const x = this.items.splice(i,1)[0]; this.items.splice(i+1,0,x); },
    }"
    class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 bg-gray-50/40 dark:bg-gray-800/40"
>
    <div class="flex items-center justify-between mb-3">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $label }}</label>
        <button type="button" @click="add()"
                class="text-xs px-2.5 py-1 bg-brand-600 text-white rounded hover:bg-brand-700">
            + افزودن آیتم
        </button>
    </div>

    @if($help)
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $help }}</p>
    @endif

    <template x-for="(item, i) in items" :key="i">
        <div class="border border-gray-100 dark:border-gray-700 rounded p-3 mb-2 bg-white dark:bg-gray-700">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-500" x-text="'آیتم ' + (i+1)"></span>
                <div class="flex gap-1">
                    <button type="button" @click="moveUp(i)" class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-600 rounded">↑</button>
                    <button type="button" @click="moveDown(i)" class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-600 rounded">↓</button>
                    <button type="button" @click="remove(i)" class="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded hover:bg-red-200">حذف</button>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($item_fields as $itemKey => $itemDef)
                    @php
                        $itemType  = $itemDef['type'] ?? 'string';
                        $itemLabel = $itemDef['label'] ?? $itemKey;
                    @endphp
                    <div :class="@js($itemType === 'textarea' ? 'sm:col-span-2' : '')">
                        <label class="block text-xs mb-1">{{ $itemLabel }}</label>
                        @if($itemType === 'textarea')
                            <textarea
                                :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                x-model="item[@js($itemKey)]"
                                rows="2"
                                class="w-full px-2 py-1 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm"
                            ></textarea>
                        @else
                            <input
                                type="text"
                                :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                x-model="item[@js($itemKey)]"
                                class="w-full px-2 py-1 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm"
                            >
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </template>
</div>
