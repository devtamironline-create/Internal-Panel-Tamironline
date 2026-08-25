@extends('layouts.admin')

@section('page-title', 'پوشش خدمات')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto" x-data="{ q: '', open: {} }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">پوشش خدمات</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                هر خدمت در کدام استان‌ها و شهرها فعال است (+ برندهای تحت پوشش برای صفحات ترکیبی مثل «لباسشویی سامسونگ»).
                همین داده عیناً از API به سایت می‌رود.
            </p>
        </div>
        <div class="flex items-center gap-2 whitespace-nowrap">
            <a href="{{ route('crm.technicians.coverage-map') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-xs">🗺 نقشه پوشش</a>
            <a href="{{ route('crm.technicians.coverage-manage') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-xs">⚙ مدیریت پوشش</a>
        </div>
    </div>

    @unless($coverage['coverage_data_complete'])
        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs leading-6 mb-4">
            ⚠ هنوز هیچ تکنسین فعالی تگ شهر ندارد — جدول پوشش خالی است و سایت نباید به آن تکیه کند.
            ابتدا تگ‌های شهر/دستگاه/برند تکنسین‌ها را کامل کنید.
        </div>
    @endunless

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="p-3 bg-sky-50 border border-sky-200 text-sky-800 rounded-xl text-xs leading-6 mb-4">
        ℹ پوشش به‌صورت خودکار از تگ‌های تکنسین‌های فعال ساخته می‌شود (شهر + مهارت دستگاه + برند) —
        برای اضافه‌کردن پوشش، تگ‌های پروفایل تکنسین را کامل کنید.
        دکمهٔ «نمایش در سایت» فقط خروجی سایت (API سئو) را کنترل می‌کند: می‌توانید خدمتی را کلاً یا فقط در یک شهر از سایت مخفی کنید —
        فرم ثبت سفارش و تخصیص تکنسین تغییری نمی‌کنند.
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="relative flex-1">
            <input type="search" x-model="q" placeholder="جست‌وجوی خدمت، استان یا شهر…"
                   class="w-full px-4 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:border-brand-400">
        </div>
        {{-- فیلتر برند — سمت سرور تا شمارش‌ها هم درست شوند --}}
        <form method="GET" class="flex items-center gap-2">
            <select name="brand" onchange="this.form.submit()"
                    class="px-3 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm">
                <option value="">همهٔ برندها</option>
                @foreach($coverage['brands'] as $b)
                    <option value="{{ $b['slug'] }}" @selected($brandFilter === $b['slug'])>{{ $b['name'] }}</option>
                @endforeach
            </select>
            @if($brandFilter !== '')
                <a href="{{ route('crm.technicians.service-coverage') }}" class="text-xs text-gray-500 hover:text-gray-700">✕ حذف فیلتر</a>
            @endif
        </form>
    </div>

    @if($brandFilter !== '')
        @php $brandName = collect($coverage['brands'])->firstWhere('slug', $brandFilter)['name'] ?? $brandFilter; @endphp
        <div class="p-3 bg-violet-50 border border-violet-200 text-violet-800 rounded-xl text-xs mb-4">
            🔎 فیلتر برند: <b>{{ $brandName }}</b> — فقط شهرهایی که برای این برند تکنسین دارند.
            صفحهٔ ترکیبی «خدمت + {{ $brandName }}» فقط برای همین شهرها ساخته شود.
        </div>
    @endif

    <div class="space-y-3">
        @forelse($coverage['services'] as $s)
            @php
                $provinceNames = collect($s['provinces'])->pluck('name')->implode(' ');
                $cityNames = collect($s['provinces'])->flatMap(fn ($p) => collect($p['cities'])->pluck('name'))->implode(' ');
                $haystack = mb_strtolower($s['name'].' '.$provinceNames.' '.$cityNames);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm"
                 x-show="q === '' || @js($haystack).includes(q.toLowerCase())" x-transition.opacity>
                {{-- ── ردیف خدمت ── --}}
                <div class="flex items-center gap-3 p-4 cursor-pointer {{ $s['site_visible'] ? '' : 'opacity-70' }}" @click="open[{{ $s['id'] }}] = ! open[{{ $s['id'] }}]">
                    <span class="text-gray-400 text-xs" x-text="open[{{ $s['id'] }}] ? '▾' : '◂'"></span>
                    <div class="font-bold text-gray-900 dark:text-gray-100">{{ $s['name'] }}</div>
                    <span class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 text-[11px] font-bold">
                        {{ $s['province_count'] }} استان
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
                        {{ $s['city_count'] }} شهر
                    </span>
                    @unless($s['site_visible'])
                        <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-[10px]">مخفی از سایت</span>
                    @endunless
                    <div class="ms-auto flex items-center gap-2" @click.stop>
                        @can('manage-crm-devices')
                        <a href="{{ route('crm.devices.coverage-titles', $s['id']) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">✍ عناوین</a>
                        <form method="POST" action="{{ route('crm.technicians.service-coverage.toggle') }}" class="inline">
                            @csrf
                            <input type="hidden" name="device_id" value="{{ $s['id'] }}">
                            <button class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $s['site_visible'] ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                {{ $s['site_visible'] ? 'مخفی از سایت' : 'نمایش در سایت' }}
                            </button>
                        </form>
                        @endcan
                    </div>
                </div>

                <div x-show="open[{{ $s['id'] }}]" x-collapse x-cloak>
                    <div class="px-4 pb-4 space-y-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                        @foreach($s['provinces'] as $p)
                            <div>
                                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">{{ $p['name'] }}</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($p['cities'] as $c)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs
                                                     {{ $c['site_visible']
                                                         ? 'bg-gray-50 dark:bg-gray-700/60 border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-100'
                                                         : 'bg-gray-100 dark:bg-gray-700/30 border-dashed border-gray-300 dark:border-gray-600 text-gray-400 line-through' }}"
                                              @if($c['brands'] !== 'all')
                                                  title="برندها: {{ collect($coverage['brands'])->whereIn('slug', $c['brands'])->pluck('name')->implode('، ') }}"
                                              @endif>
                                            {{ $c['name'] }}
                                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold no-underline">{{ $c['technician_count'] }}</span>
                                            @if($c['brands'] !== 'all')
                                                <span class="text-[10px] text-violet-600 dark:text-violet-400">{{ count($c['brands']) }} برند</span>
                                            @endif
                                            @can('manage-crm-devices')
                                                <form method="POST" action="{{ route('crm.technicians.service-coverage.toggle') }}" class="inline leading-none">
                                                    @csrf
                                                    <input type="hidden" name="device_id" value="{{ $s['id'] }}">
                                                    <input type="hidden" name="city_id" value="{{ $c['city_id'] }}">
                                                    <button type="submit"
                                                            title="{{ $c['site_visible'] ? 'مخفی‌کردن این شهر از سایت (فقط برای این خدمت)' : 'نمایش دوباره در سایت' }}"
                                                            class="text-[11px] font-bold {{ $c['site_visible'] ? 'text-rose-500 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                                        {{ $c['site_visible'] ? '✕' : '✓' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <p class="text-[11px] text-gray-400">
                            عدد سبز = تعداد تکنسین فعال. «N برند» یعنی این شهر فقط برای همان برندها تکنسین دارد (روی چیپ نگه دارید تا لیست را ببینید)؛ بدون برچسب = همهٔ برندها.
                            ✕ = مخفی‌کردن همان شهر از سایت برای این خدمت؛ چیپ خط‌خورده یعنی الان از سایت مخفی است (✓ برای بازگرداندن).
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 text-center text-sm text-gray-500">
                {{ $brandFilter !== '' ? 'هیچ خدمتی برای این برند پوشش ندارد.' : 'هنوز هیچ خدمتی پوشش ندارد — تگ‌های تکنسین‌ها را کامل کنید.' }}
            </div>
        @endforelse
    </div>

    {{-- خدمات بدون هیچ پوششی — فقط بدونِ فیلترِ برند معنا دارد --}}
    @if($brandFilter === '')
        @php
            $coveredSlugs = collect($coverage['services'])->pluck('slug')->all();
            $uncovered = collect($coverage['devices'])->filter(fn ($d) => ! in_array($d['slug'], $coveredSlugs, true))->values();
        @endphp
        @if($uncovered->isNotEmpty())
            <div class="mt-4 p-4 bg-rose-50 border border-rose-200 rounded-xl">
                <div class="text-xs font-bold text-rose-800 mb-2">خدمات بدون پوشش (در هیچ شهری تکنسین فعال ندارند — فقط لید):</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($uncovered as $d)
                        <span class="px-2.5 py-1 rounded-lg bg-white border border-rose-200 text-xs text-rose-700">{{ $d['name'] }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
