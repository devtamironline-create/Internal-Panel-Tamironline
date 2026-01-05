@extends('layouts.admin')
@section('page-title', 'دوره‌های OKR')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">دوره‌های OKR</h1>
            <p class="text-gray-600 mt-1">مدیریت دوره‌های زمانی OKR</p>
        </div>
        @can('manage-okr')
        <a href="{{ route('okr.cycles.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            دوره جدید
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عنوان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">بازه زمانی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اهداف</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">پیشرفت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cycles as $cycle)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="{{ route('okr.cycles.show', $cycle) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $cycle->title }}</a>
                        @if($cycle->description)
                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($cycle->description, 50) }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $cycle->jalali_start_date }} - {{ $cycle->jalali_end_date }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cycle->objectives_count }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-brand-500 rounded-full h-2" style="width: {{ $cycle->progress }}%"></div>
                            </div>
                            <span class="text-sm text-gray-600">{{ number_format($cycle->progress, 0) }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($cycle->status === 'active')
                        <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                        @elseif($cycle->status === 'draft')
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">پیش‌نویس</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">بسته شده</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('okr.cycles.show', $cycle) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            @can('manage-okr')
                            <a href="{{ route('okr.cycles.edit', $cycle) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @if($cycle->status !== 'active')
                            <form action="{{ route('okr.cycles.activate', $cycle) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg" title="فعال کردن">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                            </form>
                            @else
                            <form action="{{ route('okr.cycles.close', $cycle) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="p-2 text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg" title="بستن">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">هنوز دوره‌ای ایجاد نشده</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cycles->hasPages())
    <div class="flex justify-center">{{ $cycles->links() }}</div>
    @endif
</div>
@endsection
