@extends('layouts.admin')

@section('page-title', 'محتوای صفحه — ' . $title)

@section('main')
<div class="p-6">
    <div class="mb-4">
        <a href="{{ route('site.admin.page-content.index') }}" class="text-sm text-blue-600 hover:underline">&larr; بازگشت به فهرست صفحات</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $title }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 font-mono">slug: {{ $slug }}</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
        <ul class="list-disc pr-4">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('site.admin.page-content.update', $slug) }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach($schemaSections as $sectionKey => $section)
            @php
                $sectionValue   = $values[$sectionKey] ?? ['payload' => [], 'is_published' => true];
                $payload        = $sectionValue['payload'] ?? [];
                $isPublished    = $sectionValue['is_published'] ?? true;
                $sectionLabel   = $section['label'] ?? $sectionKey;
                $sectionDesc    = $section['description'] ?? null;
                $publishedName  = "sections[{$sectionKey}][is_published]";
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                {{-- هدر سکشن --}}
                <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-base font-bold text-gray-800 dark:text-white">{{ $sectionLabel }}</h2>
                        <p class="text-xs text-gray-500 mt-1 font-mono">{{ $sectionKey }}</p>
                        @if($sectionDesc)
                            <p class="text-xs text-gray-500 mt-1">{{ $sectionDesc }}</p>
                        @endif
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="{{ $publishedName }}" value="0">
                        <input type="checkbox" name="{{ $publishedName }}" value="1" @checked(old($publishedName, $isPublished))>
                        <span class="text-xs">منتشر شود</span>
                    </label>
                </div>

                {{-- فیلدها --}}
                <div class="p-4 space-y-4">
                    @foreach(($section['fields'] ?? []) as $fieldKey => $field)
                        @php
                            $type      = $field['type'] ?? 'string';
                            $name      = "sections[{$sectionKey}][payload][{$fieldKey}]";
                            $value     = $payload[$fieldKey] ?? null;
                            $fieldLabel = $field['label'] ?? $fieldKey;
                        @endphp

                        {{-- ─── Repeater ─── --}}
                        @if($type === 'repeater')
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
                                // برای منابع int (devices/brands) IDها رو string می‌کنیم تا in_array کار کنه
                                $selectedIdsCmp = array_map(fn ($v) => (string) $v, $selectedIds);
                            @endphp
                            <div class="border border-gray-200 rounded p-3">
                                <label class="block text-sm font-semibold mb-1">{{ $fieldLabel }}</label>
                                <p class="text-xs text-gray-500 mb-2">
                                    @if($source === 'faqs')
                                        از <a href="{{ route('site.admin.faqs.index') }}" target="_blank" class="text-blue-600 hover:underline">مخزن سوالات</a> انتخاب کنید. ترتیب کلیک = ترتیب نمایش.
                                    @elseif($source === 'testimonials')
                                        از <a href="{{ route('site.admin.testimonials.index') }}" target="_blank" class="text-blue-600 hover:underline">مخزن نظرات</a> انتخاب کنید.
                                    @elseif($source === 'devices')
                                        از <a href="{{ route('crm.devices.index') }}" target="_blank" class="text-blue-600 hover:underline">دستگاه‌های CRM</a> انتخاب کنید. اگر هیچ‌کدام انتخاب نشود، همه‌ی دستگاه‌های فعال به‌صورت پیش‌فرض نمایش داده می‌شوند.
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
                                                    @if($ref->tone) <span class="text-gray-400">{{ $ref->tone }}</span> @endif
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
                </div>
            </div>
        @endforeach

        <div class="sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-4 border-t border-gray-200 dark:border-gray-700 -mx-6 flex items-center justify-end gap-2 shadow-lg">
            <a href="{{ route('site.admin.page-content.index') }}" class="px-4 py-2 bg-gray-100 rounded text-sm">انصراف</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded text-sm font-semibold">ذخیره همه سکشن‌ها</button>
        </div>
    </form>
</div>
@endsection
