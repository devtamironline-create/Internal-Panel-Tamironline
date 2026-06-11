@extends('layouts.admin')

@section('page-title', 'هزینه‌ها')

@section('main')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">هزینه‌های شرکت</h1>
            <p class="text-sm text-gray-500 mt-1">فاز ۱ حسابداری — ثبت و گزارش‌گیری هزینه‌ها</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.costs.report') }}" class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm font-medium">📊 گزارش‌ها</a>
            <a href="{{ route('crm.costs.create') }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">+ ثبت هزینه</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    @include('crm::accounting._filters', ['action' => route('crm.costs.index')])

    {{-- جمع نتیجهٔ فیلتر فعلی --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">جمع مبلغ (با فیلتر فعلی)</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($totalAmount) }} <span class="text-xs font-normal text-gray-400">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400">تعداد سند هزینه</div>
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ number_format($totalCount) }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تاریخ</th>
                    <th class="p-3 text-start">شرح</th>
                    <th class="p-3 text-start">دسته</th>
                    <th class="p-3 text-start">مبلغ</th>
                    <th class="p-3 text-start">حساب پرداخت</th>
                    <th class="p-3 text-start">پرداخت‌کننده</th>
                    <th class="p-3 text-start">تگ‌ها</th>
                    <th class="p-3 text-start w-36"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($expenses as $e)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($e->paid_at)</td>
                        <td class="p-3">
                            <div class="font-medium">{{ $e->description }}</div>
                            @if($e->payee)<div class="text-[10.5px] text-gray-400">دریافت‌کننده: {{ $e->payee }}</div>@endif
                            @if($e->attachment_path)
                                <a href="{{ route('crm.costs.attachment', $e) }}" target="_blank" class="text-[10.5px] text-brand-700 hover:underline">📎 ضمیمه</a>
                            @endif
                        </td>
                        <td class="p-3 text-xs">
                            <div class="text-gray-500">{{ $e->category?->parent?->name ?: '—' }}</div>
                            <div class="font-medium">{{ $e->category?->name ?: '—' }}</div>
                        </td>
                        <td class="p-3 font-bold whitespace-nowrap">{{ number_format($e->amount) }}</td>
                        <td class="p-3 text-xs text-gray-600">
                            {{ $e->account?->title ?: '—' }}
                            @if($e->payment_method_label)<div class="text-[10px] text-gray-400">{{ $e->payment_method_label }}</div>@endif
                        </td>
                        <td class="p-3 text-xs text-gray-600">{{ $e->payer ?: '—' }}</td>
                        <td class="p-3">
                            @foreach($e->tags as $tag)
                                <span class="inline-block px-1.5 py-0.5 mb-0.5 text-[10px] rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">{{ $tag->name }}</span>
                            @endforeach
                        </td>
                        <td class="p-3">
                            @can('manage-crm-costs')
                            <div class="flex items-center gap-2">
                                <a href="{{ route('crm.costs.edit', $e) }}" class="px-2.5 py-1.5 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium">ویرایش</a>
                                @if($e->isDeletable())
                                    <form method="POST" action="{{ route('crm.costs.destroy', $e) }}"
                                          onsubmit="return confirm('هزینهٔ «{{ $e->description }}» حذف شود؟');">
                                        @csrf @method('DELETE')
                                        <button class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-medium">حذف</button>
                                    </form>
                                @else
                                    <span class="text-[10px] text-gray-400" title="حذف فقط تا ۲۴ ساعت پس از ثبت مجاز است">🔒</span>
                                @endif
                            </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-sm text-gray-400">
                            هزینه‌ای پیدا نشد — اولین هزینه را با دکمهٔ «ثبت هزینه» ثبت کنید.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($expenses->hasPages())
            <div class="p-3 border-t border-gray-100 dark:border-gray-700">{{ $expenses->links() }}</div>
        @endif
    </div>
</div>
@endsection
