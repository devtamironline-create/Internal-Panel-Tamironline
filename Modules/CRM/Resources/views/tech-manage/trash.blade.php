@extends('layouts.admin')

@section('page-title', 'Trash تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🗑 Trash تکنسین‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">تکنسین‌هایی که حذف شده‌اند (soft delete). در فهرست‌های دیگر نمایش داده نمی‌شوند.</p>
        </div>
        <a href="{{ route('crm.tech-manage.index') }}" class="text-xs text-gray-500 hover:text-gray-700">← لیست اصلی</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs">
                <tr>
                    <th class="px-3 py-2 text-right">id</th>
                    <th class="px-3 py-2 text-right">نام</th>
                    <th class="px-3 py-2 text-right">موبایل</th>
                    <th class="px-3 py-2 text-right">wp_id</th>
                    <th class="px-3 py-2 text-right">تاریخ حذف</th>
                    <th class="px-3 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($techs as $t)
                    <tr>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $t->id }}</td>
                        <td class="px-3 py-2 font-medium">{{ $t->firstname_tech ?: trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? '')) }}</td>
                        <td class="px-3 py-2 text-xs" dir="ltr">{{ $t->mobile }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $t->wp_id ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500" dir="ltr">{{ $t->deleted_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('crm.tech-manage.restore', $t->id) }}" class="inline"
                                  onsubmit="return confirm('بازگردانی «{{ $t->firstname_tech }}»؟');">
                                @csrf
                                <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-xs">بازگردانی</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-400 text-sm">trash خالی است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $techs->links() }}</div>
    </div>
</div>
@endsection
