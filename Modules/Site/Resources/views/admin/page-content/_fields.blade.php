{{--
    Recursive field renderer.

    Required:
      $fields       — array of field definitions
      $values       — current values (associative array)
      $namePrefix   — HTML name prefix, e.g. "sections[layout][payload][header]"
      $references   — references list (faqs, devices, etc.)
--}}
@foreach($fields as $fieldKey => $field)
    @php
        $type       = $field['type'] ?? 'string';
        $name       = "{$namePrefix}[{$fieldKey}]";
        $value      = $values[$fieldKey] ?? null;
        $fieldLabel = $field['label'] ?? $fieldKey;
    @endphp

    {{-- ─── Group (nested fields) ─── --}}
    @if($type === 'group')
        <div class="border border-blue-200 rounded-lg p-4 bg-blue-50/30 dark:bg-blue-900/10">
            <div class="mb-3">
                <h4 class="text-sm font-bold text-blue-700 dark:text-blue-300">{{ $fieldLabel }}</h4>
                @if(!empty($field['description']))
                    <p class="text-xs text-gray-500 mt-1">{{ $field['description'] }}</p>
                @endif
            </div>
            <div class="space-y-4 pr-3 border-r-2 border-blue-200">
                @include('site::admin.page-content._fields', [
                    'fields'     => $field['fields'] ?? [],
                    'values'     => is_array($value) ? $value : [],
                    'namePrefix' => $name,
                    'references' => $references,
                ])
            </div>
        </div>

    {{-- ─── Repeater ─── --}}
    @elseif($type === 'repeater')
        @php
            $items = is_array($value) ? array_values($value) : [];
            if (empty($items)) { $items = [[]]; }
        @endphp
        <div
            x-data="{
                items: @js($items),
                add() { this.items.push({}); },
                remove(i) { this.items.splice(i, 1); if(this.items.length===0) this.add(); },
                moveUp(i) { if(i===0) return; const x = this.items.splice(i,1)[0]; this.items.splice(i-1,0,x); },
                moveDown(i) { if(i===this.items.length-1) return; const x = this.items.splice(i,1)[0]; this.items.splice(i+1,0,x); },
            }"
            class="border border-gray-200 rounded p-3"
        >
            <div class="flex items-center justify-between mb-3">
                <label class="text-sm font-semibold">{{ $fieldLabel }}</label>
                <button type="button" @click="add()" class="text-xs px-2 py-1 bg-blue-600 text-white rounded">+ افزودن آیتم</button>
            </div>

            <template x-for="(item, i) in items" :key="i">
                <div class="border border-gray-100 rounded p-3 mb-2 bg-gray-50 dark:bg-gray-700">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-gray-500" x-text="'آیتم ' + (i+1)"></span>
                        <div class="flex gap-1">
                            <button type="button" @click="moveUp(i)" class="text-xs px-2 py-0.5 bg-gray-100 rounded">↑</button>
                            <button type="button" @click="moveDown(i)" class="text-xs px-2 py-0.5 bg-gray-100 rounded">↓</button>
                            <button type="button" @click="remove(i)" class="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded">حذف</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach(($field['item_fields'] ?? []) as $itemKey => $itemDef)
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
                                        class="w-full px-2 py-1 border border-gray-200 rounded text-sm"
                                    ></textarea>
                                @elseif($itemType === 'url')
                                    <input
                                        type="url" dir="ltr"
                                        :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                        x-model="item[@js($itemKey)]"
                                        class="w-full px-2 py-1 border border-gray-200 rounded text-sm ltr"
                                    >
                                @elseif($itemType === 'int')
                                    <input
                                        type="number"
                                        :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                        x-model="item[@js($itemKey)]"
                                        class="w-full px-2 py-1 border border-gray-200 rounded text-sm"
                                    >
                                @else
                                    <input
                                        type="text"
                                        :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                        x-model="item[@js($itemKey)]"
                                        class="w-full px-2 py-1 border border-gray-200 rounded text-sm"
                                    >
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </template>
        </div>

    {{-- ─── Reference ─── --}}
    @elseif($type === 'reference')
        @php
            $source = $field['source'] ?? 'faqs';
            $list = $references[$source] ?? collect();
            $selectedIds = is_array($value) ? $value : [];
            $selectedIdsCmp = array_map(fn ($v) => (string) $v, $selectedIds);
        @endphp
        <div class="border border-gray-200 rounded p-3">
            <label class="block text-sm font-semibold mb-1">{{ $fieldLabel }}</label>
            <p class="text-xs text-gray-500 mb-2">
                @if($source === 'faqs')
                    از <a href="{{ route('site.admin.faqs.index') }}" target="_blank" class="text-blue-600 hover:underline">مخزن سوالات</a> انتخاب کنید. ترتیب کلیک = ترتیب نمایش.
                @elseif($source === 'testimonials')
                    از <a href="{{ route('site.admin.reviews.index', ['type' => 'audio']) }}" target="_blank" class="text-blue-600 hover:underline">مخزن نظرات</a> انتخاب کنید.
                @elseif($source === 'devices')
                    از <a href="{{ route('crm.devices.index') }}" target="_blank" class="text-blue-600 hover:underline">دستگاه‌های CRM</a> انتخاب کنید.
                @elseif($source === 'brands')
                    از <a href="{{ route('crm.brands.index') }}" target="_blank" class="text-blue-600 hover:underline">برندهای CRM</a> انتخاب کنید.
                @elseif($source === 'faq_categories')
                    دسته‌های انتخاب‌شده در فرانت به‌صورت تب نمایش داده می‌شوند. <a href="{{ route('site.admin.taxonomies.index', 'faq') }}" target="_blank" class="text-blue-600 hover:underline">مدیریت دسته‌ها</a>.
                @elseif($source === 'testimonial_categories')
                    دسته‌های انتخاب‌شده در فرانت به‌صورت تب نمایش داده می‌شوند. <a href="{{ route('site.admin.taxonomies.index', 'testimonial') }}" target="_blank" class="text-blue-600 hover:underline">مدیریت دسته‌ها</a>.
                @endif
            </p>
            <div class="max-h-64 overflow-y-auto space-y-1">
                @forelse($list as $ref)
                    <label class="flex items-start gap-2 p-1 hover:bg-gray-50 rounded">
                        <input type="checkbox"
                               name="{{ $name }}[]"
                               value="{{ $ref->id }}"
                               @checked(in_array((string) $ref->id, $selectedIdsCmp, true))>
                        <span class="text-xs">
                            @if($source === 'faqs')
                                {{ \Illuminate\Support\Str::limit($ref->question, 100) }}
                            @elseif($source === 'testimonials')
                                <span class="font-semibold">{{ $ref->customer_name }}</span> — {{ \Illuminate\Support\Str::limit($ref->topic, 60) }}
                            @elseif($source === 'devices')
                                <span class="font-semibold">{{ $ref->name }}</span>
                                <span class="text-gray-500 font-mono">/{{ $ref->slug }}</span>
                                @if($ref->icon) <span class="text-gray-400">[{{ $ref->icon }}]</span> @endif
                            @elseif($source === 'brands')
                                <span class="font-semibold">{{ $ref->name }}</span>
                                <span class="text-gray-500 font-mono">/{{ $ref->slug }}</span>
                            @elseif($source === 'faq_categories' || $source === 'testimonial_categories')
                                <span class="font-semibold">{{ $ref->name }}</span>
                                <span class="text-gray-500 font-mono">/{{ $ref->slug }}</span>
                            @endif
                            @if(property_exists($ref, 'is_published') && !$ref->is_published) <span class="text-amber-600">(پیش‌نویس)</span> @endif
                            @if(property_exists($ref, 'is_active') && !$ref->is_active) <span class="text-amber-600">(غیرفعال)</span> @endif
                        </span>
                    </label>
                @empty
                    <p class="text-xs text-gray-400">آیتمی موجود نیست.</p>
                @endforelse
            </div>
        </div>

    {{-- ─── Scalar field ─── --}}
    @else
        @include('site::admin.page-content._field', [
            'fieldKey' => $fieldKey,
            'field'    => $field,
            'value'    => $value,
            'name'     => $name,
        ])
    @endif
@endforeach
