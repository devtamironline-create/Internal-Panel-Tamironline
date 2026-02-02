@extends('layouts.admin')
@section('page-title', 'حضور و غیاب')
@section('main')
<div class="space-y-6" x-data="attendanceApp()">
    <!-- Today's Status Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">وضعیت امروز</h2>
                <p class="text-gray-600 dark:text-gray-400">{{ \Morilog\Jalali\Jalalian::now()->format('l، d F Y') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                @if($today)
                    @if($today->check_in)
                        <div class="text-center px-4 py-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">ورود</span>
                            <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $today->check_in }}</span>
                        </div>
                    @endif

                    @if($today->lunch_start)
                        <div class="text-center px-4 py-2 {{ $today->is_on_lunch ? 'bg-orange-50 dark:bg-orange-900/30' : 'bg-amber-50 dark:bg-amber-900/30' }} rounded-lg">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">نهار</span>
                            <span class="text-lg font-bold {{ $today->is_on_lunch ? 'text-orange-600 dark:text-orange-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $today->lunch_start }} {{ $today->lunch_end ? '- ' . $today->lunch_end : '(در حال نهار)' }}
                            </span>
                        </div>
                    @endif

                    @if($today->check_out)
                        <div class="text-center px-4 py-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">خروج</span>
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $today->check_out }}</span>
                        </div>
                    @endif

                    @if($today->late_minutes > 0)
                        <div class="text-center px-4 py-2 bg-red-50 dark:bg-red-900/30 rounded-lg">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">تاخیر</span>
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ $today->late_time }}</span>
                        </div>
                    @endif
                @else
                    <div class="text-center px-4 py-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <span class="text-gray-500 dark:text-gray-400">هنوز ورود ثبت نشده</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Check-in/out Buttons -->
        <div class="mt-6 flex flex-wrap gap-4">
            @if(!$today || !$today->check_in)
                <button
                    @click="checkIn()"
                    :disabled="loading"
                    class="flex items-center gap-2 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    ثبت ورود
                </button>
            @elseif(!$today->check_out)
                @if($today->is_on_lunch)
                    <button
                        @click="endLunch()"
                        :disabled="loading"
                        class="flex items-center gap-2 px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        پایان نهار
                    </button>
                @else
                    @if(!$today->lunch_start)
                        <button
                            @click="startLunch()"
                            :disabled="loading"
                            class="flex items-center gap-2 px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            شروع نهار
                        </button>
                    @endif
                    <button
                        @click="checkOut()"
                        :disabled="loading"
                        class="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <svg x-show="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        ثبت خروج
                    </button>
                @endif
            @else
                <div class="flex items-center gap-2 px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    امروز کارکرد ثبت شده است
                </div>
            @endif
        </div>

        <!-- Message -->
        <div x-show="message" x-transition class="mt-4 p-4 rounded-lg" :class="success ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400'">
            <span x-text="message"></span>
        </div>
    </div>

    <!-- Work Settings Info -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30">
                    <svg class="w-6 h-6 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">ساعت کاری</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $employeeSettings->getWorkStartTime() }} - {{ $employeeSettings->getWorkEndTime() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/30">
                    <svg class="w-6 h-6 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">مدت نهار</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $settings->lunch_duration_minutes ?? 30 }} دقیقه</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50 dark:bg-yellow-900/30">
                    <svg class="w-6 h-6 text-yellow-500 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">تلرانس تاخیر</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $settings->late_tolerance_minutes }} دقیقه</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/30">
                    <svg class="w-6 h-6 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">روش تایید</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @php
                            $methods = $settings->verification_methods ?? ['trust'];
                            $labels = ['trust' => 'اعتماد', 'ip' => 'IP', 'gps' => 'GPS', 'selfie' => 'سلفی'];
                        @endphp
                        {{ implode('، ', array_map(fn($m) => $labels[$m] ?? $m, $methods)) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Stats -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">آمار این ماه</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
                <span class="block text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['present_days'] }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">روز حضور</span>
            </div>
            <div class="text-center p-4 bg-red-50 dark:bg-red-900/30 rounded-lg">
                <span class="block text-2xl font-bold text-red-600 dark:text-red-400">{{ $stats['absent_days'] }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">روز غیبت</span>
            </div>
            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                <span class="block text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['total_work_hours'] }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">ساعت کارکرد</span>
            </div>
            <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                <span class="block text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['total_late_minutes'] }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">دقیقه تاخیر</span>
            </div>
            <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                <span class="block text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['total_overtime_minutes'] }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">دقیقه اضافه‌کاری</span>
            </div>
        </div>
    </div>

    <!-- Recent Attendances -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">سوابق این ماه</h3>
            <a href="{{ route('attendance.history') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700">
                مشاهده همه
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ورود</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">خروج</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">کارکرد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تاخیر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($monthlyAttendances->take(10) as $attendance)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $attendance->jalali_date }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $attendance->check_in ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $attendance->check_out ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $attendance->work_hours }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $attendance->late_minutes > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                            {{ $attendance->late_time }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $attendance->status_color }}-100 dark:bg-{{ $attendance->status_color }}-900/30 text-{{ $attendance->status_color }}-800 dark:text-{{ $attendance->status_color }}-400">
                                {{ $attendance->status_label }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            هیچ رکوردی یافت نشد
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function attendanceApp() {
    return {
        loading: false,
        message: '',
        success: false,

        async checkIn() {
            this.loading = true;
            this.message = '';

            try {
                let position = null;
                if (navigator.geolocation) {
                    position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), {
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });
                }

                const formData = new FormData();
                if (position) {
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);
                }

                const response = await fetch('{{ route("attendance.check-in") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت ورود';
            }

            this.loading = false;
        },

        async checkOut() {
            this.loading = true;
            this.message = '';

            try {
                let position = null;
                if (navigator.geolocation) {
                    position = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), {
                            timeout: 5000,
                            maximumAge: 0
                        });
                    });
                }

                const formData = new FormData();
                if (position) {
                    formData.append('latitude', position.coords.latitude);
                    formData.append('longitude', position.coords.longitude);
                }

                const response = await fetch('{{ route("attendance.check-out") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت خروج';
            }

            this.loading = false;
        },

        async startLunch() {
            this.loading = true;
            this.message = '';

            try {
                const response = await fetch('{{ route("attendance.lunch-start") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت شروع نهار';
            }

            this.loading = false;
        },

        async endLunch() {
            this.loading = true;
            this.message = '';

            try {
                const response = await fetch('{{ route("attendance.lunch-end") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                this.success = data.success;
                this.message = data.message;

                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                this.success = false;
                this.message = 'خطا در ثبت پایان نهار';
            }

            this.loading = false;
        }
    };
}
</script>
@endpush
@endsection
