{{--
    Reusable Alpine-powered JSON repeater for CRM admin forms.

    Required:
      $name        — base field name (e.g. "stats", "issues")
      $items       — array of current items (already array, not JSON string)
      $label       — visible label
      $item_fields — assoc array of sub-field config:
                     ['key' => ['label' => 'Label', 'type' => 'string'|'textarea'|'image']]
    Optional:
      $help        — help text under the field
      $min         — minimum visible items (default 1)

    Field types:
      - string   : single-line text input
      - textarea : multi-line textarea (spans full row)
      - image    : media picker (انتخاب از مخزن / آپلود / پاک) + preview
--}}
@php
    $items = is_array($items ?? null) ? array_values($items) : [];
    if (empty($items)) { $items = [[]]; }
    $help = $help ?? null;
@endphp

<div
    x-data="{
        items: @js($items),
        pickerUrl: '{{ route('site.admin.media.picker') }}',
        uploadUrl: '{{ route('site.admin.media.upload') }}',
        csrfToken: '{{ csrf_token() }}',
        pickerOpen: false,
        pickerTarget: null,
        pickerItems: [],
        pickerPage: 1,
        pickerLastPage: 1,
        pickerLoading: false,
        search: '',
        uploadTarget: null,
        add() { this.items.push({}); },
        remove(i) { this.items.splice(i, 1); if(this.items.length===0) this.add(); },
        moveUp(i) { if(i===0) return; const x = this.items.splice(i,1)[0]; this.items.splice(i-1,0,x); },
        moveDown(i) { if(i===this.items.length-1) return; const x = this.items.splice(i,1)[0]; this.items.splice(i+1,0,x); },
        openPicker(i, fieldKey) {
            this.pickerTarget = { i, fieldKey };
            this.pickerOpen = true;
            this.pickerLoadPage(1);
        },
        async pickerLoadPage(page) {
            if (page < 1) return;
            this.pickerLoading = true;
            const params = new URLSearchParams({ page: String(page), kind: 'image' });
            if (this.search) params.set('q', this.search);
            try {
                const res = await fetch(this.pickerUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                this.pickerItems = json.data;
                this.pickerPage = json.meta.page;
                this.pickerLastPage = json.meta.last_page;
            } finally { this.pickerLoading = false; }
        },
        pickItem(m) {
            if (this.pickerTarget) {
                this.items[this.pickerTarget.i][this.pickerTarget.fieldKey] = m.url;
            }
            this.pickerOpen = false;
        },
        triggerUpload(i, fieldKey) {
            this.uploadTarget = { i, fieldKey };
            this.$refs.repeaterUpload.click();
        },
        async handleUpload(event) {
            if (!this.uploadTarget) return;
            const { i, fieldKey } = this.uploadTarget;
            const file = event.target.files?.[0];
            if (!file) { this.uploadTarget = null; return; }
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('visibility', 'public');
                const res = await fetch(this.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok || !json.ok || !json.media) {
                    alert(json?.message || 'آپلود ناموفق بود');
                } else {
                    this.items[i][fieldKey] = json.media.url;
                }
            } catch (err) {
                alert('خطای آپلود: ' + (err?.message || err));
            } finally {
                this.uploadTarget = null;
                event.target.value = '';
            }
        },
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

    {{-- Hidden file input for upload — shared by all items --}}
    <input type="file" x-ref="repeaterUpload" accept="image/*" class="hidden" @change="handleUpload($event)">

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
                    <div :class="@js(in_array($itemType, ['textarea', 'image']) ? 'sm:col-span-2' : '')">
                        <label class="block text-xs mb-1">{{ $itemLabel }}</label>
                        @if($itemType === 'textarea')
                            <textarea
                                :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                x-model="item[@js($itemKey)]"
                                rows="2"
                                class="w-full px-2 py-1 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm"
                            ></textarea>
                        @elseif($itemType === 'image')
                            <input type="hidden" :name="`{{ $name }}[${i}][{{ $itemKey }}]`" :value="item[@js($itemKey)] ?? ''">
                            <div class="flex items-start gap-3 p-2 border border-gray-200 dark:border-gray-600 rounded bg-white dark:bg-gray-700">
                                <div class="w-24 h-16 bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6),linear-gradient(45deg,#f3f4f6_25%,transparent_25%,transparent_75%,#f3f4f6_75%,#f3f4f6)] [background-size:8px_8px] [background-position:0_0,4px_4px] rounded border border-gray-200 overflow-hidden flex items-center justify-center shrink-0">
                                    <template x-if="item[@js($itemKey)]">
                                        <img :src="item[@js($itemKey)]" class="max-w-full max-h-full object-contain">
                                    </template>
                                    <template x-if="!item[@js($itemKey)]">
                                        <span class="text-[10px] text-gray-400">بدون تصویر</span>
                                    </template>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <button type="button" @click="openPicker(i, @js($itemKey))"
                                            class="px-2 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs">انتخاب از مخزن</button>
                                    <button type="button" @click="triggerUpload(i, @js($itemKey))"
                                            class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs">آپلود</button>
                                    <button type="button" @click="item[@js($itemKey)] = ''"
                                            x-show="item[@js($itemKey)]"
                                            class="px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded text-xs">پاک</button>
                                </div>
                            </div>
                        @elseif($itemType === 'date')
                            <input
                                type="date"
                                :name="`{{ $name }}[${i}][{{ $itemKey }}]`"
                                x-model="item[@js($itemKey)]"
                                dir="ltr"
                                class="w-full px-2 py-1 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm ltr"
                            >
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

    {{-- Picker Modal — shared by all items --}}
    <div x-show="pickerOpen" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="pickerOpen = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-bold">انتخاب از مخزن مدیا</h3>
                <button type="button" @click="pickerOpen = false" class="text-gray-500">✕</button>
            </div>
            <div class="p-3 border-b border-gray-100 dark:border-gray-700">
                <input type="text" x-model.debounce.200ms="search" @input="pickerLoadPage(1)" placeholder="جستجو..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
            </div>
            <div class="overflow-y-auto p-3 flex-1">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    <template x-for="m in pickerItems" :key="m.id">
                        <button type="button" @click="pickItem(m)" class="text-right">
                            <img :src="m.variants?.thumb?.url ?? m.url" :alt="m.alt ?? ''"
                                 class="aspect-square w-full object-cover rounded border border-gray-200 dark:border-gray-600 hover:border-purple-500">
                            <div class="text-[10px] text-gray-500 truncate mt-1" x-text="m.title || ''"></div>
                        </button>
                    </template>
                </div>
                <div x-show="pickerLoading" class="text-center py-4 text-gray-400 text-sm">در حال بارگذاری...</div>
            </div>
            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
                <span>صفحه <span x-text="pickerPage"></span> / <span x-text="pickerLastPage"></span></span>
                <div class="flex gap-2">
                    <button type="button" @click="pickerLoadPage(pickerPage - 1)" :disabled="pickerPage <= 1"
                            class="px-3 py-1 border rounded disabled:opacity-50">قبلی</button>
                    <button type="button" @click="pickerLoadPage(pickerPage + 1)" :disabled="pickerPage >= pickerLastPage"
                            class="px-3 py-1 border rounded disabled:opacity-50">بعدی</button>
                </div>
            </div>
        </div>
    </div>
</div>
