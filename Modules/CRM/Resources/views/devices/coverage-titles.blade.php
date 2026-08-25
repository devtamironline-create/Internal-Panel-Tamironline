@extends('layouts.admin')

@section('page-title', 'مناطق تحت پوشش — ' . $device->name)

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto"
     x-data='{
        rows: @json($rows),
        brands: @json($brands->map(fn ($b) => ["id" => (int) $b->id, "name" => $b->name])),
        preps: @json(\Modules\CRM\Support\CoverageTitles::PREPOSITIONS),
        device: @json($device->name),
        sample: "تهران",
        brandName(id) {
            const b = this.brands.find(x => x.id === Number(id));
            return b ? b.name : null;
        },
        title(row, loc) {
            const brand = row.brand_id ? this.brandName(row.brand_id) : null;
            return [row.prefix, this.device, brand, row.preposition, loc].filter(Boolean).join(" ");
        },
        addRow() { this.rows.push({ prefix: "تعمیر", brand_id: null, preposition: "در" }); },
        removeRow(i) { this.rows.splice(i, 1); },
        move(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.rows.length) return;
            const tmp = this.rows[i]; this.rows[i] = this.rows[j]; this.rows[j] = tmp;
        },
     }'>

    {{-- ── هدر ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('crm.devices.index') }}" class="hover:text-brand-600">خدمات</a>
                <span>›</span>
                <a href="{{ route('crm.devices.edit', $device) }}" class="hover:text-brand-600">{{ $device->name }}</a>
                <span>›</span>
                <span>مناطق تحت پوشش</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مناطق تحت پوشش</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">مدیریت عناوین و قالب نمایش مناطق تحت پوشش در سایت</p>
        </div>
        <div class="flex items-center gap-2 whitespace-nowrap">
            <a href="{{ route('crm.devices.edit', $device) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">→ بازگشت</a>
            <button type="submit" form="coverage-titles-form"
                    class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-semibold">ذخیره تغییرات</button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @error('rows')
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ $message }}</div>
    @enderror

    {{-- ── الگوی عنوان مناطق ── --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 md:p-6 mb-6">
        <h2 class="font-bold text-gray-900 dark:text-gray-100">الگوی عنوان مناطق</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">ساختار و پیشوند عنوان برای نمایش مناطق تحت پوشش</p>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 items-start">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- توکن‌های غیرقابل ویرایش — فقط برای فهمِ الگو --}}
                    @php
                        // آکولادها جدا ساخته می‌شوند تا کامپایلرِ Blade آن‌ها را echo نگیرد.
                        $tok = fn (string $name) => '{'.'{'.$name.'}'.'}';
                        $tokens = [
                            ['label' => 'دستگاه', 'token' => $tok('device')],
                            ['label' => 'برند (اختیاری)', 'token' => $tok('brand')],
                            ['label' => 'حرف اضافه', 'token' => $tok('preposition')],
                            ['label' => 'منطقه / شهر / استان', 'token' => $tok('location')],
                        ];
                    @endphp
                    @foreach($tokens as $t)
                        <span class="flex flex-col items-center px-3 py-2 rounded-lg bg-brand-50/60 dark:bg-brand-900/20 border border-brand-100 dark:border-brand-800 text-xs">
                            <b class="text-gray-800 dark:text-gray-100">{{ $t['label'] }}</b>
                            <code class="text-[10px] text-gray-500" dir="ltr">{{ $t['token'] }}</code>
                        </span>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-3">
                    ترتیب نمایش: پیشوند عنوان + دستگاه + برند (در صورت وجود) + حرف اضافه + مکان (استان/شهر/منطقه)
                </p>
            </div>

            {{-- پیش‌نمایش زنده از ردیف اول --}}
            <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-xl p-4 min-w-[16rem]">
                <div class="text-xs text-gray-500 mb-1">پیش‌نمایش عنوان ℹ</div>
                <div class="font-bold text-gray-900 dark:text-gray-100" x-text="rows.length ? title(rows[0], sample) : '—'"></div>
                <div class="text-[11px] text-gray-400 mt-1"
                     x-show="rows.length && rows[0].brand_id"
                     x-text="rows.length ? ('(در صورت عدم وجود برند: ' + title({...rows[0], brand_id: null}, sample) + ')') : ''"></div>
            </div>
        </div>
    </div>

    {{-- ── لیست مناطق و عناوین ── --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 md:p-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="font-bold text-gray-900 dark:text-gray-100">لیست عناوین</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    مدیریت عناوین «{{ $device->name }}» — سایت برای هر استان/شهر/منطقهٔ تحت پوشش، این عناوین را با نام همان مکان می‌سازد.
                </p>
            </div>
            <button type="button" @click="addRow()"
                    class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm whitespace-nowrap">+ افزودن عنوان جدید</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 px-2 text-right w-10">ردیف</th>
                        <th class="py-2 px-2 text-right">پیشوند عنوان</th>
                        <th class="py-2 px-2 text-right">دستگاه</th>
                        <th class="py-2 px-2 text-right">برند</th>
                        <th class="py-2 px-2 text-right">حرف اضافه</th>
                        <th class="py-2 px-2 text-right">نمونه عنوان در «تهران»</th>
                        <th class="py-2 px-2 text-right w-32">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="i">
                        <tr class="border-b border-gray-100 dark:border-gray-700/60">
                            <td class="py-2.5 px-2 text-gray-500" x-text="i + 1"></td>
                            <td class="py-2.5 px-2">
                                <input type="text" x-model="row.prefix" maxlength="60" placeholder="مثلاً: تعمیر"
                                       class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                            </td>
                            <td class="py-2.5 px-2">
                                <span class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm">
                                    🔒 {{ $device->name }}
                                </span>
                            </td>
                            <td class="py-2.5 px-2">
                                <select x-model.number="row.brand_id"
                                        class="w-36 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                                    <option :value="null">— بدون برند —</option>
                                    <template x-for="b in brands" :key="b.id">
                                        <option :value="b.id" x-text="b.name" :selected="row.brand_id === b.id"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="py-2.5 px-2">
                                <select x-model="row.preposition"
                                        class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                                    <template x-for="p in preps" :key="p">
                                        <option :value="p" x-text="p" :selected="row.preposition === p"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="py-2.5 px-2 text-gray-700 dark:text-gray-200" x-text="title(row, sample)"></td>
                            <td class="py-2.5 px-2">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" @click="move(i, -1)" :disabled="i === 0"
                                            class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 disabled:opacity-30 text-xs" title="بالا">↑</button>
                                    <button type="button" @click="move(i, 1)" :disabled="i === rows.length - 1"
                                            class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 disabled:opacity-30 text-xs" title="پایین">↓</button>
                                    <button type="button" @click="removeRow(i)"
                                            class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs" title="حذف">🗑</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="7" class="py-6 text-center text-sm text-gray-400">
                            هیچ عنوانی تعریف نشده — «افزودن عنوان جدید» را بزنید. (بدون ردیف، سایت از پیش‌فرض «تعمیر … در …» استفاده می‌کند.)
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-[11px] text-gray-400 mt-3">
            ℹ ترتیب نمایش عناوین در سایت مطابق ترتیب ردیف‌ها خواهد بود. مکان‌های عنوان از «پوشش خدمات» می‌آیند
            (استان/شهرهای دارای تکنسین فعال — مرکز استان اول).
        </p>

        {{-- مکان‌های واقعی این خدمت — برای دید ادمین --}}
        @if($coverage)
            <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-600 rounded-xl">
                <div class="text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">
                    مکان‌هایی که این عناوین برایشان ساخته می‌شود ({{ $coverage['province_count'] }} استان / {{ $coverage['city_count'] }} شهر):
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($coverage['provinces'] as $p)
                        <span class="px-2 py-0.5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-[11px] text-gray-700 dark:text-gray-200">
                            {{ $p['name'] }} ({{ collect($p['cities'])->pluck('name')->implode('، ') }})
                        </span>
                    @endforeach
                </div>
            </div>
        @else
            <div class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700">
                این خدمت فعلاً در هیچ شهری تکنسین فعال ندارد — عنوانی هم در سایت ساخته نمی‌شود.
            </div>
        @endif
    </div>

    <form id="coverage-titles-form" method="POST" action="{{ route('crm.devices.coverage-titles.save', $device) }}"
          @submit="$refs.rowsInput.value = JSON.stringify(rows)">
        @csrf
        <input type="hidden" name="rows" x-ref="rowsInput">
    </form>
</div>
@endsection
