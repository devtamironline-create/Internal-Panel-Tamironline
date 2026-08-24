@extends('layouts.admin')

@section('page-title', 'مدیریت پوشش سرویس‌دهی')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto" x-data="{ q: '', open: {} }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مدیریت پوشش سرویس‌دهی</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                فعال/غیرفعال‌کردن استان، شهر و منطقه + تعداد تکنسین هر سطح.
                جایی که تکنسین فعال ندارد، ثبت «سفارش» بسته است و فقط «لید» ممکن است.
            </p>
        </div>
        <a href="{{ route('crm.technicians.coverage-map') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm whitespace-nowrap">🗺 نقشه پوشش</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="p-3 bg-sky-50 border border-sky-200 text-sky-800 rounded-xl text-xs leading-6 mb-4">
        ℹ «غیرفعال» یعنی آن استان/شهر/منطقه در اپ مشتری و لیست‌های انتخاب نمایش داده نمی‌شود.
        «تعداد تکنسین» از تگ‌های پروفایل تکنسین‌ها می‌آید — برای اضافه‌کردن پوشش، تگ شهر/منطقه تکنسین را در پروفایلش تنظیم کنید.
    </div>

    <div class="relative mb-4">
        <input type="search" x-model="q" placeholder="جست‌وجوی استان یا شهر…"
               class="w-full px-4 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:border-brand-400">
    </div>

    <div class="space-y-3">
        @foreach($tree as $p)
            @php
                $cityNames = collect($p['cities'])->pluck('name')->implode(' ');
                $haystack = mb_strtolower($p['name'].' '.$cityNames);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm"
                 x-show="q === '' || @js($haystack).includes(q.toLowerCase())" x-transition.opacity>
                {{-- ── ردیف استان ── --}}
                <div class="flex items-center gap-3 p-4 cursor-pointer" @click="open[{{ $p['id'] }}] = ! open[{{ $p['id'] }}]">
                    <span class="text-gray-400 text-xs" x-text="open[{{ $p['id'] }}] ? '▾' : '◂'"></span>
                    <div class="font-bold text-gray-900 dark:text-gray-100">{{ $p['name'] }}</div>
                    @if(! $p['is_active'])
                        <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-[10px]">غیرفعال</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $p['tech_count'] > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                        {{ $p['tech_count'] > 0 ? $p['tech_count'].' تکنسین' : 'بدون تکنسین — فقط لید' }}
                    </span>
                    <span class="text-[11px] text-gray-400">{{ count($p['cities']) }} شهر</span>
                    <div class="ms-auto" @click.stop>
                        @can('manage-crm-provinces')
                        <form method="POST" action="{{ route('crm.provinces.toggle-active', $p['id']) }}" class="inline">
                            @csrf @method('PUT')
                            <button class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $p['is_active'] ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                {{ $p['is_active'] ? 'غیرفعال کن' : 'فعال کن' }}
                            </button>
                        </form>
                        @endcan
                    </div>
                </div>

                {{-- ── شهرها ── --}}
                <div x-show="open[{{ $p['id'] }}]" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                    @forelse($p['cities'] as $c)
                        <div class="px-4 py-3 border-b border-gray-50 dark:border-gray-700/50 last:border-b-0">
                            <div class="flex items-center gap-3">
                                <div class="text-sm font-bold text-gray-800 dark:text-gray-200 ms-6">{{ $c['name'] }}</div>
                                @if(! $c['is_active'])
                                    <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-[10px]">غیرفعال</span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $c['tech_count'] > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' }}">
                                    {{ $c['tech_count'] > 0 ? $c['tech_count'].' تکنسین' : 'بدون تکنسین — فقط لید' }}
                                </span>
                                <div class="ms-auto flex items-center gap-2">
                                    @can('manage-crm-cities')
                                    <form method="POST" action="{{ route('crm.cities.toggle-active', $c['id']) }}" class="inline">
                                        @csrf @method('PUT')
                                        <button class="px-2.5 py-1 rounded-lg text-[11px] font-bold {{ $c['is_active'] ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                            {{ $c['is_active'] ? 'غیرفعال کن' : 'فعال کن' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('crm.cities.edit', $c['id']) }}" class="text-[11px] text-brand-700 hover:underline">ویرایش</a>
                                    @endcan
                                </div>
                            </div>

                            {{-- ── مناطق شهر ── --}}
                            @if(count($c['districts']))
                                <div class="flex flex-wrap gap-1.5 mt-2 ms-6">
                                    @foreach($c['districts'] as $d)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px]
                                                    {{ $d['is_active'] ? 'border-gray-200 dark:border-gray-600' : 'border-dashed border-gray-300 opacity-60' }}">
                                            <span class="{{ $d['tech_count'] > 0 ? 'text-gray-800 dark:text-gray-200' : 'text-rose-600' }}">
                                                {{ $d['name'] }}
                                                <b>{{ $d['tech_count'] > 0 ? '('.$d['tech_count'].')' : '(۰ — فقط لید)' }}</b>
                                            </span>
                                            @can('manage-crm-cities')
                                            <form method="POST" action="{{ route('crm.cities.toggle-active', $d['id']) }}" class="inline">
                                                @csrf @method('PUT')
                                                <button class="text-[10px] font-bold {{ $d['is_active'] ? 'text-rose-600 hover:underline' : 'text-emerald-600 hover:underline' }}"
                                                        title="{{ $d['is_active'] ? 'غیرفعال‌کردن منطقه' : 'فعال‌کردن منطقه' }}">
                                                    {{ $d['is_active'] ? '✕' : '✓ فعال' }}
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-3 text-sm text-gray-400 ms-6">شهری برای این استان تعریف نشده است.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
