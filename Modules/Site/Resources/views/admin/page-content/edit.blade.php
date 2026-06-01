@extends('layouts.admin')

@section('page-title', 'محتوای صفحه — ' . $title)

@section('main')
<div x-data="pageContentEditor()" class="min-h-screen bg-gray-50 dark:bg-gray-900">

    {{-- ─── Sticky header ─── --}}
    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.page-content.index') }}" class="hover:text-blue-600">فهرست صفحات</a>
                    <span>›</span>
                    <span class="font-mono">{{ $slug }}</span>
                </div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ $title }}</h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="expandAll()"
                        class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">
                    باز کردن همه
                </button>
                <button type="button" @click="collapseAll()"
                        class="px-3 py-2 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg">
                    بستن همه
                </button>
                <button type="submit" form="page-content-form"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    ذخیره همه سکشن‌ها
                </button>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm">
                <p class="font-semibold mb-1">{{ $errors->count() }} خطا در فرم وجود دارد:</p>
                <ul class="list-disc pr-4 space-y-1">
                    @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">

            {{-- ─── TOC sidebar ─── --}}
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                            <h3 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide">سکشن‌های صفحه</h3>
                        </div>
                        <nav class="p-2 max-h-[calc(100vh-12rem)] overflow-y-auto">
                            @foreach($schemaSections as $sectionKey => $section)
                                @php
                                    $sectionValue = $values[$sectionKey] ?? ['payload' => [], 'is_published' => true];
                                    $hasData = ! empty(array_filter((array) ($sectionValue['payload'] ?? []), fn ($v) => $v !== null && $v !== '' && $v !== []));
                                    $isPublishedFlag = $sectionValue['is_published'] ?? true;
                                @endphp
                                <a href="#section-{{ $sectionKey }}"
                                   :class="activeSection === '{{ $sectionKey }}' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-500' : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-700'"
                                   class="block px-3 py-2 rounded-lg text-sm border-r-2 transition-colors mb-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium truncate">{{ $section['label'] ?? $sectionKey }}</div>
                                            <div class="text-[10px] text-gray-500 font-mono mt-0.5">{{ $sectionKey }}</div>
                                        </div>
                                        <div class="flex flex-col items-end gap-1 shrink-0">
                                            @if($hasData)
                                                <span class="w-2 h-2 rounded-full bg-emerald-500" title="پر شده"></span>
                                            @else
                                                <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600" title="خالی"></span>
                                            @endif
                                            @if(! $isPublishedFlag)
                                                <span class="text-[9px] px-1 rounded bg-amber-100 text-amber-700">draft</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </nav>
                        <div class="px-3 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-[10px] text-gray-500 flex items-center gap-3">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> پر شده</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300"></span> خالی</span>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- ─── Main form ─── --}}
            <form id="page-content-form" method="POST" action="{{ route('site.admin.page-content.update', $slug) }}" class="space-y-5">
                @csrf @method('PUT')

                @foreach($schemaSections as $sectionKey => $section)
                    @php
                        $sectionValue = $values[$sectionKey] ?? ['payload' => [], 'is_published' => true];
                        $payload = $sectionValue['payload'] ?? [];
                        $isPublished = $sectionValue['is_published'] ?? true;
                        $sectionLabel = $section['label'] ?? $sectionKey;
                        $sectionDesc = $section['description'] ?? null;
                        $publishedName = "sections[{$sectionKey}][is_published]";
                        $hasData = ! empty(array_filter((array) $payload, fn ($v) => $v !== null && $v !== '' && $v !== []));
                        $fieldCount = count($section['fields'] ?? []);
                    @endphp

                    <section id="section-{{ $sectionKey }}" data-section="{{ $sectionKey }}"
                             x-data="{ open: collapsed['{{ $sectionKey }}'] !== true }"
                             class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden scroll-mt-24">

                        {{-- Section header --}}
                        <header class="flex items-center justify-between gap-3 px-5 py-4 bg-gradient-to-l from-blue-50 to-transparent dark:from-blue-900/20 border-b border-gray-100 dark:border-gray-700">
                            <button type="button" @click="open = !open"
                                    class="flex-1 flex items-center gap-3 min-w-0 text-right">
                                <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0"
                                     :class="open ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $sectionLabel }}</h2>
                                        @if($hasData)
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">پر شده</span>
                                        @else
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">خالی</span>
                                        @endif
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 font-mono">{{ $fieldCount }} فیلد</span>
                                    </div>
                                    <p class="text-xs text-gray-500 font-mono mt-0.5">{{ $sectionKey }}</p>
                                </div>
                            </button>

                            {{-- Published toggle as a switch --}}
                            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                                <input type="hidden" name="{{ $publishedName }}" value="0">
                                <input type="checkbox" name="{{ $publishedName }}" value="1"
                                       @checked(old($publishedName, $isPublished))
                                       class="peer sr-only">
                                <span class="relative w-10 h-5 bg-gray-200 dark:bg-gray-600 rounded-full peer-checked:bg-emerald-500 transition-colors">
                                    <span class="absolute top-0.5 right-0.5 peer-checked:right-[1.375rem] transition-all w-4 h-4 bg-white rounded-full shadow"></span>
                                </span>
                                <span class="text-xs text-gray-600 dark:text-gray-300">انتشار</span>
                            </label>
                        </header>

                        {{-- Section description --}}
                        @if($sectionDesc)
                            <div x-show="open" x-collapse class="px-5 pt-3 -mb-2">
                                <p class="text-xs text-gray-500 bg-amber-50 dark:bg-amber-900/20 border-r-2 border-amber-400 px-3 py-2 rounded">{{ $sectionDesc }}</p>
                            </div>
                        @endif

                        {{-- Fields body --}}
                        <div x-show="open" x-collapse class="p-5 space-y-4">
                            @include('site::admin.page-content._fields', [
                                'fields' => $section['fields'] ?? [],
                                'values' => is_array($payload) ? $payload : [],
                                'namePrefix' => "sections[{$sectionKey}][payload]",
                                'references' => $references,
                            ])
                        </div>
                    </section>
                @endforeach

                {{-- Bottom save bar (mobile-friendly) --}}
                <div class="lg:hidden sticky bottom-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur p-4 -mx-4 border-t border-gray-200 dark:border-gray-700 shadow-lg flex items-center gap-2">
                    <a href="{{ route('site.admin.page-content.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">انصراف</a>
                    <button type="submit" class="flex-1 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold">ذخیره همه</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function pageContentEditor() {
    return {
        activeSection: '',
        collapsed: {},   // {sectionKey: true} = بسته
        observer: null,
        init() {
            // remember collapsed state per page
            try {
                const saved = localStorage.getItem('page-content-collapsed-{{ $slug }}');
                if (saved) this.collapsed = JSON.parse(saved);
            } catch (e) {}

            // scroll spy — highlight TOC link based on visible section
            this.$nextTick(() => {
                const sections = document.querySelectorAll('section[data-section]');
                if (!sections.length) return;
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.activeSection = entry.target.dataset.section;
                        }
                    });
                }, { rootMargin: '-100px 0px -60% 0px', threshold: 0 });
                sections.forEach((s) => this.observer.observe(s));
            });
        },
        expandAll() {
            this.collapsed = {};
            this.persist();
            document.querySelectorAll('section[data-section]').forEach((s) => {
                const alpine = s._x_dataStack?.[0];
                if (alpine) alpine.open = true;
            });
        },
        collapseAll() {
            document.querySelectorAll('section[data-section]').forEach((s) => {
                this.collapsed[s.dataset.section] = true;
                const alpine = s._x_dataStack?.[0];
                if (alpine) alpine.open = false;
            });
            this.persist();
        },
        persist() {
            try {
                localStorage.setItem('page-content-collapsed-{{ $slug }}', JSON.stringify(this.collapsed));
            } catch (e) {}
        },
    };
}
</script>
@endsection
