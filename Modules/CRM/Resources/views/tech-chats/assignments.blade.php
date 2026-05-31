@extends('layouts.admin')

@section('page-title', 'تخصیص اپراتورها به تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تخصیص اپراتورها</h1>
            <p class="text-xs text-gray-500 mt-1">برای هر تکنسین می‌توانید چند اپراتور انتخاب کنید — همگی به thread چت آن تکنسین دسترسی خواهند داشت.</p>
        </div>
        <a href="{{ route('crm.tech-chats.index') }}" class="text-sm text-brand-700 hover:underline">← گفت‌وگو</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="space-y-3">
        @foreach($technicians as $tech)
            @php
                $name = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                $name = $name !== '' ? $name : ('تکنسین #' . $tech->id);
                $assignedIds = $tech->operators->pluck('id')->all();
            @endphp
            <form action="{{ route('crm.tech-chats.assign', $tech) }}" method="POST"
                  class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
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
