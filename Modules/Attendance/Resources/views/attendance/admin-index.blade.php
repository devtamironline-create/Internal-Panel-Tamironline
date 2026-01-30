@extends('layouts.admin')
@section('page-title', 'مدیریت حضور و غیاب')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مدیریت حضور و غیاب</h1>
            <p class="text-gray-600 dark:text-gray-400">مشاهده وضعیت حضور کارکنان</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="exportReport('pdf')" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                PDF
            </button>
            <button onclick="exportReport('excel')" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Excel
            </button>
            <a href="{{ route('attendance.settings') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                تنظیمات
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('attendance.admin') }}" method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاریخ</label>
                <input type="text" name="date" id="report_date" value="{{ \Morilog\Jalali\Jalalian::fromDateTime($date)->format('Y/m/d') }}"
                    class="jalali-datepicker rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                <svg class="w-5 h-5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                نمایش
            </button>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30">
                    <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">کل پرسنل</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $allStaff->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/30">
                    <svg class="w-7 h-7 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">حاضر</p>
                    <p class="text-3xl font-bold text-green-600">{{ count($presentIds) }}</p>
                    <p class="text-xs text-gray-400">{{ $allStaff->count() > 0 ? round(count($presentIds) / $allStaff->count() * 100) : 0 }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/30">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">غایب</p>
                    <p class="text-3xl font-bold text-red-600">{{ $allStaff->count() - count($presentIds) }}</p>
                    <p class="text-xs text-gray-400">{{ $allStaff->count() > 0 ? round(($allStaff->count() - count($presentIds)) / $allStaff->count() * 100) : 0 }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 hover:shadow-md transition">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-yellow-50 dark:bg-yellow-900/30">
                    <svg class="w-7 h-7 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">با تاخیر</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $attendances->where('late_minutes', '>', 0)->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Pie Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">نمودار وضعیت</h3>
            <div id="statusChart" class="h-64"></div>
        </div>

        <!-- Summary Stats -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">خلاصه گزارش</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                    <p class="text-sm text-gray-500 dark:text-gray-400">کل ساعت کاری</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $attendances->sum(function($a) { return $a->work_minutes ?? 0; }) / 60 }} ساعت
                    </p>
                </div>
                <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-xl">
                    <p class="text-sm text-gray-500 dark:text-gray-400">کل تاخیر</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">
                        {{ $attendances->sum('late_minutes') }} دقیقه
                    </p>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-xl">
                    <p class="text-sm text-gray-500 dark:text-gray-400">کل اضافه‌کاری</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">
                        {{ $attendances->sum('overtime_minutes') }} دقیقه
                    </p>
                </div>
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                    <p class="text-sm text-gray-500 dark:text-gray-400">میانگین ورود</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">
                        @php
                            $avgCheckIn = $attendances->filter(fn($a) => $a->check_in)->avg(fn($a) => strtotime($a->check_in));
                            echo $avgCheckIn ? date('H:i', $avgCheckIn) : '-';
                        @endphp
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                حضور و غیاب {{ \Morilog\Jalali\Jalalian::fromDateTime($date)->format('l، d F Y') }}
            </h3>
            <div class="flex items-center gap-2">
                <input type="text" id="searchTable" placeholder="جستجو..." class="px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full" id="attendanceTable">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">نام</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">ورود</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">خروج</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">کارکرد</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">تاخیر</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">اضافه‌کاری</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($allStaff as $staff)
                        @php
                            $attendance = $attendances->where('user_id', $staff->id)->first();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center text-white font-semibold shadow-sm">
                                        {{ mb_substr($staff->first_name ?? 'A', 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $staff->full_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $staff->mobile }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm {{ $attendance && $attendance->check_in ? 'text-green-600 font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $attendance && $attendance->check_in ? $attendance->check_in : '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm {{ $attendance && $attendance->check_out ? 'text-blue-600 font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $attendance && $attendance->check_out ? $attendance->check_out : '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
                                {{ $attendance ? $attendance->work_hours : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($attendance && $attendance->late_minutes > 0)
                                    <span class="inline-flex items-center gap-1 text-sm text-red-600 font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>
                                        {{ $attendance->late_time }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($attendance && $attendance->overtime_minutes > 0)
                                    <span class="inline-flex items-center gap-1 text-sm text-green-600 font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        {{ $attendance->overtime_minutes }} دقیقه
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($attendance)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-{{ $attendance->status_color }}-100 dark:bg-{{ $attendance->status_color }}-900/30 text-{{ $attendance->status_color }}-800 dark:text-{{ $attendance->status_color }}-400">
                                        {{ $attendance->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-400">
                                        ثبت نشده
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Status Chart
document.addEventListener('DOMContentLoaded', function() {
    var options = {
        series: [{{ count($presentIds) }}, {{ $allStaff->count() - count($presentIds) }}, {{ $attendances->where('late_minutes', '>', 0)->count() }}],
        chart: {
            type: 'donut',
            height: 250,
        },
        labels: ['حاضر', 'غایب', 'با تاخیر'],
        colors: ['#10B981', '#EF4444', '#F59E0B'],
        legend: {
            position: 'bottom',
            fontFamily: 'Rokh',
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '55%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'کل',
                            fontSize: '14px',
                            fontFamily: 'Rokh',
                        }
                    }
                }
            }
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: { width: 200 },
                legend: { position: 'bottom' }
            }
        }]
    };

    var chart = new ApexCharts(document.querySelector("#statusChart"), options);
    chart.render();
});

// Table Search
document.getElementById('searchTable').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#attendanceTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Export functions
function exportReport(type) {
    const date = document.getElementById('report_date')?.value || '{{ $date }}';
    window.location.href = `{{ route('attendance.admin') }}?date=${date}&export=${type}`;
}
</script>
@endpush
@endsection
