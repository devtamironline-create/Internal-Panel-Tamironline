@extends('layouts.admin')

@section('page-title', 'نظرسنجی‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">نظرسنجی‌های مشتریان اپ</h1>
        <a href="{{ route('customer-app.dashboard') }}" class="text-xs px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">← هاب اپ</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    {{-- شمارنده‌ها / فیلتر سریع status --}}
    <div class="grid grid-cols-3 gap-3">
        <a href="?status=pending" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 text-center {{ $status === 'pending' ? 'ring-2 ring-amber-400' : '' }}">
            <div class="text-xs text-gray-500">در انتظار بررسی</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($counts['pending']) }}</div>
        </a>
        <a href="?status=approved" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 text-center {{ $status === 'approved' ? 'ring-2 ring-emerald-400' : '' }}">
            <div class="text-xs text-gray-500">تأییدشده</div>
            <div class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($counts['approved']) }}</div>
        </a>
        <a href="?status=rejected" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 text-center {{ $status === 'rejected' ? 'ring-2 ring-rose-400' : '' }}">
            <div class="text-xs text-gray-500">ردشده</div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($counts['rejected']) }}</div>
        </a>
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 flex items-end gap-3 text-sm">
        <input type="hidden" name="status" value="{{ $status }}">
        <div>
            <label class="block text-xs text-gray-700 dark:text-gray-200 mb-1">حداقل امتیاز</label>
            <select name="min_rating" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                <option value="">—</option>
                @for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected($minRating === $i)>{{ $i }} +</option>@endfor
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-700 dark:text-gray-200 mb-1">حداکثر امتیاز</label>
            <select name="max_rating" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                <option value="">—</option>
                @for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected($maxRating === $i)>{{ $i }}</option>@endfor
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg">اعمال</button>
        @if($status || $minRating || $maxRating)
            <a href="{{ route('customer-app.reviews.index') }}" class="text-xs text-gray-500 underline">پاک کردن</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-3 py-2 text-start">سفارش</th>
                    <th class="px-3 py-2 text-start">مشتری</th>
                    <th class="px-3 py-2 text-start">تکنسین</th>
                    <th class="px-3 py-2 text-center">امتیاز</th>
                    <th class="px-3 py-2 text-start">نظر</th>
                    <th class="px-3 py-2 text-center">وضعیت</th>
                    <th class="px-3 py-2 text-center">تاریخ</th>
                    <th class="px-3 py-2 text-center">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $r)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">
                            <div class="font-mono text-xs text-gray-900 dark:text-gray-100" dir="ltr">{{ $r->order->order_code ?? '#'.$r->order_id }}</div>
                            <div class="text-[10px] text-gray-500">{{ $r->order->device->name ?? '—' }} / {{ $r->order->brand->name ?? '—' }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="text-xs">{{ $r->customer ? trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) : '—' }}</div>
                            <div class="text-[10px] text-gray-500" dir="ltr">{{ $r->customer->mobile ?? '' }}</div>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            {{ $r->technician?->display_name ?: '—' }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="text-amber-500 font-bold text-lg">{{ $r->rating }}/5</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2 max-w-xs">{{ $r->comment ?: '—' }}</div>
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($r->status === 'pending')
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">در انتظار</span>
                            @elseif($r->status === 'approved')
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">تأیید</span>
                            @else
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">رد</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center text-[10px] text-gray-500">
                            {{ $r->created_at?->diffForHumans() }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('customer-app.reviews.show', $r) }}" class="text-blue-600 hover:underline text-xs">جزئیات</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500 text-sm">نظری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
