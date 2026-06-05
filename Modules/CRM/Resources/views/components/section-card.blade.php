@props([
    'sectionKey' => '',
    'title' => '',
    'description' => null,
    'icon' => null,        // emoji یا متن کوتاه برای نشانگر
    'count' => null,       // عدد (مثلاً تعداد آیتم‌های انتخاب‌شده) — اختیاری
])
<section id="section-{{ $sectionKey }}" data-section="{{ $sectionKey }}"
         x-data="{
            open: (window.entityFormCollapsed && window.entityFormCollapsed['{{ $sectionKey }}']) !== true,
            toggle() {
                this.open = !this.open;
                if (!window.entityFormCollapsed) window.entityFormCollapsed = {};
                window.entityFormCollapsed['{{ $sectionKey }}'] = !this.open;
                try { localStorage.setItem('entity-form-collapsed', JSON.stringify(window.entityFormCollapsed)); } catch(e){}
            },
         }"
         x-on:expand-all.window="open = true; if (window.entityFormCollapsed) delete window.entityFormCollapsed['{{ $sectionKey }}']; try { localStorage.setItem('entity-form-collapsed', JSON.stringify(window.entityFormCollapsed || {})); } catch(e){}"
         x-on:collapse-all.window="open = false; if (!window.entityFormCollapsed) window.entityFormCollapsed = {}; window.entityFormCollapsed['{{ $sectionKey }}'] = true; try { localStorage.setItem('entity-form-collapsed', JSON.stringify(window.entityFormCollapsed)); } catch(e){}"
         class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden scroll-mt-24">

    <header @click="toggle()"
            class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer bg-gradient-to-l from-blue-50 to-transparent dark:from-blue-900/20 border-b border-gray-100 dark:border-gray-700 hover:from-blue-100 dark:hover:from-blue-900/30 transition-colors">
        <div class="flex-1 flex items-center gap-3 min-w-0">
            <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0"
                 :class="open ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            @if($icon)
                <span class="text-xl">{{ $icon }}</span>
            @endif
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
                    @isset($count)
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300 font-mono">{{ $count }}</span>
                    @endisset
                </div>
                @if($description)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $description }}</p>
                @endif
            </div>
        </div>
    </header>

    <div x-show="open" x-collapse class="p-5 space-y-4">
        {{ $slot }}
    </div>
</section>
