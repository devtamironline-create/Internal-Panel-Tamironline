@extends('layouts.admin')

@section('page-title', 'صفحات سئوی شهرها')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">صفحات سئوی شهرها</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            برای هر «شهر اصلی»، درختِ کاملِ صفحات به‌صورت پیش‌نویس ساخته می‌شود. مدیر آن‌ها را بررسی و منتشر می‌کند؛
            تا زمانِ انتشار روی سایت دیده نمی‌شوند.
        </p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">شهر</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">همهٔ صفحات</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">منتشرشده</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($cities as $city)
                    @php($c = $counts->get($city->id))
                    @php($total = (int) ($c->total ?? 0))
                    @php($published = (int) ($c->published ?? 0))
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $city->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $city->province?->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $total }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $published }}</td>
                        <td class="px-6 py-4">
                            @if($total === 0)
                                <span class="inline-flex px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-xs">ساخته نشده</span>
                            @elseif($published === 0)
                                <span class="inline-flex px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-xs">همه پیش‌نویس</span>
                            @elseif($published < $total)
                                <span class="inline-flex px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs">تا حدی منتشر</span>
                            @else
                                <span class="inline-flex px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs">همه منتشر</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('crm.cities.pages.index', $city) }}" class="text-purple-600 hover:text-purple-800 text-sm font-medium">مدیریت صفحات ←</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">شهر اصلی‌ای ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
