@extends('layouts.admin')

@section('page-title', 'قالب‌های سئو')

@section('main')
<div class="p-6 max-w-3xl mx-auto" dir="rtl" x-data="seoTemplates()">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">قالب‌های عنوان و توضیح سئو</h1>
    <p class="text-sm text-gray-500 mb-4">قالب هر نوع صفحه را با متغیرها بسازید. اولویت اعمال:
        <span class="font-bold">مقدارِ دستیِ هر صفحه ← این قالبِ نوع ← قالبِ سراسری ← پیش‌فرضِ کد.</span>
    </p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- راهنمای متغیرها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-1">متغیرهای قابل استفاده</h2>
        <p class="text-xs text-gray-500 mb-3">روی هر متغیر کلیک کنید تا در آخرین فیلدی که انتخاب کرده‌اید درج شود. هم نحو
            <code class="font-mono ltr">{name}</code> و هم <code class="font-mono ltr">%name%</code> پشتیبانی می‌شوند.</p>
        <div class="flex flex-wrap gap-2">
            @foreach($variables as $key => $label)
                <button type="button" @click="insertToken('{{ '{'.$key.'}' }}')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-brand-100 dark:hover:bg-gray-600 text-xs transition">
                    <code class="font-mono ltr text-brand-600 dark:text-brand-300">{{ '{'.$key.'}' }}</code>
                    <span class="text-gray-500 dark:text-gray-300">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('seo.admin.templates.update') }}" class="space-y-5">
        @csrf @method('PUT')

        @foreach($rows as $type => $row)
            <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
                <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">
                    {{ $row['label'] }}
                    <code class="font-mono ltr text-xs text-gray-400">{{ $type }}</code>
                </legend>

                <label class="block text-sm">قالب عنوان
                    <input type="text" name="templates[{{ $type }}][title]"
                           x-ref="title_{{ $type }}"
                           @focus="setActive($refs.title_{{ $type }})"
                           @input="preview($refs.title_{{ $type }}, $refs.titlePrev_{{ $type }})"
                           value="{{ old('templates.'.$type.'.title', $row['title']) }}"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <p class="text-xs text-gray-400">پیش‌نمایش: <span class="text-gray-600 dark:text-gray-300" x-ref="titlePrev_{{ $type }}"></span></p>

                <label class="block text-sm">قالب توضیحات
                    <textarea name="templates[{{ $type }}][description]" rows="2"
                              x-ref="desc_{{ $type }}"
                              @focus="setActive($refs.desc_{{ $type }})"
                              @input="preview($refs.desc_{{ $type }}, $refs.descPrev_{{ $type }})"
                              class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('templates.'.$type.'.description', $row['description']) }}</textarea>
                </label>
                <p class="text-xs text-gray-400">پیش‌نمایش: <span class="text-gray-600 dark:text-gray-300" x-ref="descPrev_{{ $type }}"></span></p>

                @if($type === 'global')
                    <p class="text-xs text-gray-500">نمونه: <code class="font-mono ltr">تعمیر {device} {brand} در {city} {sep} {site_name}</code></p>
                @endif
            </fieldset>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
        </div>
    </form>
</div>

<script>
function seoTemplates() {
    return {
        active: null,
        sample: {
            title: 'نمونه', slug: 'sample', sitename: 'تعمیرآنلاین', site_name: 'تعمیرآنلاین',
            sitedesc: 'تعمیرات تخصصی', sep: '|', excerpt: 'توضیح نمونه', brand: 'ال جی',
            device: 'لباسشویی', city: 'تهران', guarantee: 'گارانتی', phone: '۰۲۱',
            date: '۱۴۰۳', currentyear: '۱۴۰۳', year: '۱۴۰۳', id: '۱'
        },
        setActive(el) { this.active = el; },
        insertToken(token) {
            const el = this.active;
            if (!el) return;
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? el.value.length;
            el.value = el.value.slice(0, start) + token + el.value.slice(end);
            const pos = start + token.length;
            el.focus();
            el.setSelectionRange(pos, pos);
            el.dispatchEvent(new Event('input'));
        },
        render(tpl) {
            return (tpl || '').replace(/%([a-zA-Z0-9_\-]+)%|\{([a-zA-Z0-9_\-]+)\}/g, (m, a, b) => {
                const key = (a || b || '').toLowerCase();
                return Object.prototype.hasOwnProperty.call(this.sample, key) ? this.sample[key] : '';
            }).replace(/\s+/g, ' ').trim();
        },
        preview(input, out) { if (out) out.textContent = this.render(input.value); },
        init() {
            this.$nextTick(() => {
                document.querySelectorAll('[x-ref^="title_"], [x-ref^="desc_"]').forEach(el => {
                    const ref = el.getAttribute('x-ref');
                    const prevRef = ref.startsWith('title_') ? 'titlePrev_' + ref.slice(6) : 'descPrev_' + ref.slice(5);
                    const out = this.$refs[prevRef];
                    if (out) out.textContent = this.render(el.value);
                });
            });
        }
    };
}
</script>
@endsection
