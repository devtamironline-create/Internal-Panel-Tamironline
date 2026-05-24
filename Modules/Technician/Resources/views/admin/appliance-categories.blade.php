@extends('layouts.admin')
@section('page-title', 'مدیریت دستگاه‌ها')

@section('main')
<div class="space-y-6" x-data="applianceCategoryEditor()">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">مدیریت دسته‌بندی دستگاه‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">دستگاه‌ها را به صورت درختی (والد و زیرمجموعه) مدیریت کنید — در فرم ثبت‌نام تکنسین به همین شکل گروه‌بندی می‌شوند.</p>
        </div>
        <a href="{{ route('technician.admin.settings') }}"
           class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            بازگشت به تنظیمات
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm leading-7">
        @foreach($errors->all() as $err)
            <div>• {{ $err }}</div>
        @endforeach
    </div>
    @endif

    {{-- فرم افزودن --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-bold text-gray-800 mb-3">افزودن دسته یا زیرمجموعه</h2>
        <form method="POST" action="{{ route('technician.admin.appliance-categories.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">نام</label>
                <input type="text" name="name" placeholder="مثلاً: ماشین لباسشویی" required value="{{ old('name') }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">والد (اختیاری)</label>
                <select name="parent_id"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    <option value="">— ریشه (دسته اصلی) —</option>
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}" {{ old('parent_id') == $root->id ? 'selected' : '' }}>
                            {{ $root->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">برای ساخت زیرمجموعه، والد را انتخاب کنید.</p>
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="w-full px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    افزودن
                </button>
            </div>
        </form>
    </div>

    {{-- درخت دسته‌ها --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">
                درخت دسته‌ها ({{ $roots->count() }} ریشه، {{ $roots->sum(fn($r) => $r->children->count()) }} زیرمجموعه)
            </h2>
        </div>

        @if($roots->isEmpty())
            <div class="p-8 text-center text-gray-400 text-sm">
                هنوز دسته‌ای اضافه نشده است.
            </div>
        @else
            <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 text-xs text-amber-700">
                💡 برای تغییر ترتیب، روی آیکن ⋮⋮ سمت راست هر دسته بکشید و رها کنید.
                <span id="reorder-status" class="ms-2 font-medium"></span>
            </div>
            <div class="divide-y divide-gray-100" id="roots-sortable" data-reorder-url="{{ route('technician.admin.appliance-categories.reorder') }}">
                @foreach($roots as $root)
                    {{-- ریشه --}}
                    <div data-id="{{ $root->id }}">
                        <div class="px-5 py-3 flex items-center justify-between bg-blue-50/40 hover:bg-blue-50 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="drag-handle cursor-move text-gray-400 hover:text-gray-700 select-none" title="کشیدن برای جابجایی">⋮⋮</span>
                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2"/>
                                </svg>
                                <span class="text-xs text-gray-400 w-6 text-center">{{ $root->sort_order }}</span>
                                <span class="text-sm font-bold text-gray-900 truncate">{{ $root->name }}</span>
                                @if(!$root->is_active)
                                    <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">غیرفعال</span>
                                @endif
                                @if($root->children->count())
                                    <span class="text-[10px] px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-medium">
                                        {{ $root->children->count() }} زیرمجموعه
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @include('technician::admin.partials.appliance-category-actions', ['category' => $root])
                            </div>
                        </div>

                        {{-- زیرمجموعه‌ها --}}
                        @if($root->children->count())
                            <div class="bg-gray-50/50 border-t border-gray-100 children-sortable" data-parent-id="{{ $root->id }}">
                                @foreach($root->children as $child)
                                    <div data-id="{{ $child->id }}" class="ps-12 pe-5 py-2.5 flex items-center justify-between hover:bg-white transition-colors border-b border-gray-100 last:border-b-0">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="drag-handle cursor-move text-gray-300 hover:text-gray-600 select-none" title="کشیدن برای جابجایی">⋮⋮</span>
                                            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            <span class="text-[10px] text-gray-300 w-5 text-center">{{ $child->sort_order }}</span>
                                            <span class="text-sm text-gray-700 truncate">{{ $child->name }}</span>
                                            @if(!$child->is_active)
                                                <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">غیرفعال</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @include('technician::admin.partials.appliance-category-actions', ['category' => $child])
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ─── Modal ویرایش (نام و والد) ─────────────────────────────────── --}}
    <div x-show="open" x-cloak @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @click.self="close()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5"
             x-transition.scale.duration.150ms>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">ویرایش دسته</h3>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form :action="`/admin/technician/appliance-categories/${id}`" method="POST" class="space-y-3">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs text-gray-500 mb-1">نام</label>
                    <input type="text" name="name" x-model="name" required maxlength="255"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">والد</label>
                    <select name="parent_id" x-model="parent_id" :disabled="hasChildren"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none disabled:bg-gray-50 disabled:text-gray-400">
                        <option value="">— ریشه (دسته اصلی) —</option>
                        @foreach($roots as $root)
                            <option value="{{ $root->id }}" x-bind:disabled="{{ $root->id }} === id">
                                {{ $root->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1" x-show="!hasChildren">
                        برای تبدیل این دسته به زیرمجموعهٔ یک ریشه، والد را انتخاب کن. برای تبدیلش به ریشه، خالی بگذار.
                    </p>
                    <p class="text-[10px] text-amber-600 mt-1" x-show="hasChildren">
                        این دسته خودش زیرمجموعه دارد، پس فقط می‌توان نامش را ویرایش کرد (نمی‌توان زیرمجموعهٔ کس دیگری شود).
                    </p>
                </div>

                <input type="hidden" name="is_active" :value="is_active">

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="close()"
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                        انصراف
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        ذخیره
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function applianceCategoryEditor() {
    return {
        open: false,
        id: null,
        name: '',
        parent_id: '',
        hasChildren: false,
        is_active: 1,
        openEdit(id, name, parentId, childrenCount, isActive) {
            this.id = id;
            this.name = name;
            this.parent_id = parentId === null ? '' : String(parentId);
            this.hasChildren = childrenCount > 0;
            this.is_active = isActive;
            this.open = true;
        },
        close() {
            this.open = false;
        },
    };
}

// ─── Drag & Drop reordering ─────────────────────────────────────
// SortableJS از CDN — اگر قبلاً bundle نشده، lazy load می‌کنیم.
(function () {
    function init() {
        if (typeof Sortable === 'undefined') return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        const statusEl = document.getElementById('reorder-status');

        function showStatus(msg, isError) {
            if (!statusEl) return;
            statusEl.textContent = msg;
            statusEl.style.color = isError ? '#b91c1c' : '#15803d';
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
        }

        function send(container) {
            const url = document.getElementById('roots-sortable').dataset.reorderUrl;
            const ids = Array.from(container.children)
                .map(el => el.dataset.id)
                .filter(Boolean);
            if (ids.length === 0) return;

            showStatus('در حال ذخیره...', false);
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ids }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    showStatus('✓ ترتیب جدید ذخیره شد', false);
                } else {
                    showStatus('✗ خطا در ذخیره', true);
                }
            })
            .catch(() => showStatus('✗ خطا در اتصال', true));
        }

        // ریشه‌ها
        const rootsEl = document.getElementById('roots-sortable');
        if (rootsEl) {
            Sortable.create(rootsEl, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-50',
                onEnd: () => send(rootsEl),
            });
        }

        // زیرمجموعه‌ها (یکی به ازای هر parent)
        document.querySelectorAll('.children-sortable').forEach(el => {
            Sortable.create(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-50',
                onEnd: () => send(el),
            });
        });
    }

    // SortableJS از CDN لود شود اگر موجود نباشد
    if (typeof Sortable === 'undefined') {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
        s.onload = init;
        document.head.appendChild(s);
    } else {
        init();
    }
})();
</script>
@endsection
