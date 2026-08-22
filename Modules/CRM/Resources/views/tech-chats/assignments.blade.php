@extends('layouts.admin')

@section('page-title', 'تخصیص اپراتورها به تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto" x-data="{ q: '' }">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تخصیص اپراتورها</h1>
            <p class="text-xs text-gray-500 mt-1">برای هر تکنسین می‌توانید چند اپراتور انتخاب کنید — همگی به thread چت آن تکنسین دسترسی خواهند داشت.</p>
        </div>
        <a href="{{ route('crm.tech-chats.index') }}" class="text-sm text-brand-700 hover:underline">← گفت‌وگو</a>
    </div>

    {{-- جست‌وجو روی کارت‌ها (client-side) --}}
    <div class="relative mb-4">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 right-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" x-model="q" placeholder="جست‌وجو در نام یا موبایل تکنسین…"
               class="w-full ps-9 pe-3 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:border-brand-400">
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ $errors->first() }}</div>
    @endif

    {{-- ── تخصیص گروهی: یک اپراتور → همه تکنسین‌ها با یک کلیک ── --}}
    <form action="{{ route('crm.tech-chats.assignments.bulk') }}" method="POST"
          class="bg-indigo-50/60 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl p-4 mb-4"
          x-data="{ op: '', opName: '' }">
        @csrf
        <div class="text-sm font-bold text-indigo-900 dark:text-indigo-200 mb-1">تخصیص گروهی</div>
        <p class="text-[11px] text-indigo-700 dark:text-indigo-300 mb-3">
            یک اپراتور را انتخاب کنید و با یک کلیک به <b>همه تکنسین‌های فعال</b> اضافه (یا از همه حذف) کنید —
            تخصیص‌های فعلی بقیه اپراتورها دست نمی‌خورد.
        </p>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <select name="operator_id" required
                    x-model="op" @change="opName = $event.target.selectedOptions[0]?.text || ''"
                    class="flex-1 max-w-xs px-3 py-2 border border-indigo-200 dark:border-indigo-700 dark:bg-gray-800 dark:text-gray-100 rounded-lg text-sm">
                <option value="">— انتخاب اپراتور —</option>
                @foreach($operators as $op)
                    <option value="{{ $op->id }}">{{ $op->first_name }} {{ $op->last_name }}</option>
                @endforeach
            </select>
            <button type="submit" name="bulk_action" value="attach" :disabled="!op"
                    @click="return confirm('«' + opName + '» به همه تکنسین‌های فعال اضافه شود؟')"
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50">
                ➕ افزودن به همه تکنسین‌ها
            </button>
            <button type="submit" name="bulk_action" value="detach" :disabled="!op"
                    @click="return confirm('«' + opName + '» از همه تکنسین‌ها حذف شود؟ تخصیص‌های تکی این اپراتور هم پاک می‌شود.')"
                    class="px-4 py-2 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 text-sm font-bold disabled:opacity-50">
                حذف از همه
            </button>
        </div>
    </form>

    <div class="space-y-3">
        @foreach($technicians as $tech)
            @php
                $name = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                $name = $name !== '' ? $name : ('تکنسین #' . $tech->id);
                $assignedIds = $tech->operators->pluck('id')->all();
                $searchHaystack = mb_strtolower($name . ' ' . $tech->mobile);
            @endphp
            <form action="{{ route('crm.tech-chats.assign', $tech) }}" method="POST"
                  class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4"
                  x-show="q === '' || @js($searchHaystack).includes(q.toLowerCase())"
                  x-transition.opacity>
                @csrf
                @method('PATCH')

                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">{{ $name }}</div>
                        <div class="text-xs text-gray-500" dir="ltr">{{ $tech->mobile }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('crm.tech-chats.show', $tech) }}"
                           class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700">باز کردن چت</a>
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-bold">
                            ذخیره
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                    @foreach($operators as $op)
                        @php $checked = in_array($op->id, $assignedIds, true); @endphp
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition
                                      {{ $checked ? 'border-brand-400 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50' }}">
                            <input type="checkbox" name="operator_ids[]" value="{{ $op->id }}" @checked($checked)
                                   class="w-4 h-4 text-brand-600 rounded">
                            <span class="text-sm {{ $checked ? 'font-bold text-brand-700' : 'text-gray-700 dark:text-gray-200' }}">
                                {{ $op->first_name }} {{ $op->last_name }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @if($assignedIds === [])
                    <p class="text-[11px] text-amber-600 mt-2">⚠ هیچ اپراتوری انتخاب نشده — این تکنسین در پنل خودش پیام «هنوز کارشناسی برای شما تخصیص داده نشده» می‌بیند.</p>
                @endif
            </form>
        @endforeach
    </div>
</div>
@endsection
