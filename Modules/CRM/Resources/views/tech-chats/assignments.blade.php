@extends('layouts.admin')

@section('page-title', 'تخصیص اپراتورها به تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تخصیص اپراتورها</h1>
        <a href="{{ route('crm.tech-chats.index') }}" class="text-sm text-brand-700 hover:underline">← گفت‌وگو</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="p-3 text-start">تکنسین</th>
                    <th class="p-3 text-start">موبایل</th>
                    <th class="p-3 text-start">اپراتور</th>
                    <th class="p-3 text-start w-28">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($technicians as $tech)
                    @php
                        $name = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                        $name = $name !== '' ? $name : ('تکنسین #' . $tech->id);
                    @endphp
                    <tr>
                        <td class="p-3 font-medium">{{ $name }}</td>
                        <td class="p-3 text-gray-500" dir="ltr">{{ $tech->mobile }}</td>
                        <td class="p-3">
                            <form action="{{ route('crm.tech-chats.assign', $tech) }}" method="POST" class="flex items-center gap-2" id="assign-{{ $tech->id }}">
                                @csrf
                                @method('PATCH')
                                <select name="assigned_operator_id" onchange="document.getElementById('assign-{{ $tech->id }}').submit()"
                                        class="text-sm px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                    <option value="">— بدون اپراتور —</option>
                                    @foreach($operators as $op)
                                        <option value="{{ $op->id }}" @selected($tech->assigned_operator_id == $op->id)>
                                            {{ $op->first_name }} {{ $op->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="p-3">
                            <a href="{{ route('crm.tech-chats.show', $tech) }}" class="text-xs text-brand-700 hover:underline">باز کردن چت</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
