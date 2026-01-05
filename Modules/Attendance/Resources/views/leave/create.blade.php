@extends('layouts.admin')
@section('page-title', 'درخواست مرخصی')
@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">درخواست مرخصی جدید</h1>
            <p class="text-gray-600">فرم ثبت درخواست مرخصی</p>
        </div>
        <a href="{{ route('leave.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Balance Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-center gap-4">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm text-blue-800">
                    مانده استحقاقی: <strong>{{ $employeeSettings->annual_leave_balance }} روز</strong>
                    |
                    مانده استعلاجی: <strong>{{ $employeeSettings->sick_leave_balance }} روز</strong>
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('leave.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6" x-data="leaveForm()">
        @csrf

        <!-- Leave Type -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">نوع مرخصی</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($leaveTypes as $type)
                <label class="relative">
                    <input type="radio" name="leave_type_id" value="{{ $type->id }}"
                        x-model="leaveTypeId"
                        @change="onLeaveTypeChange({{ json_encode($type) }})"
                        class="peer sr-only"
                        {{ old('leave_type_id') == $type->id ? 'checked' : '' }}>
                    <div class="flex flex-col items-center gap-2 p-4 border-2 rounded-xl cursor-pointer transition
                        peer-checked:border-{{ $type->type_color }}-500 peer-checked:bg-{{ $type->type_color }}-50
                        hover:bg-gray-50">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-{{ $type->type_color }}-100">
                            <svg class="w-6 h-6 text-{{ $type->type_color }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $type->type_icon }}"/>
                            </svg>
                        </span>
                        <span class="text-sm font-medium text-gray-900">{{ $type->name }}</span>
                        @if($type->is_hourly)
                            <span class="text-xs text-gray-500">ساعتی</span>
                        @endif
                    </div>
                </label>
                @endforeach
            </div>
            @error('leave_type_id')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Date Selection -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">تاریخ و زمان</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">از تاریخ</label>
                    <input type="text" id="start_date_display" placeholder="انتخاب تاریخ..."
                        class="leave-datepicker w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 cursor-pointer bg-white" readonly required>
                    <input type="hidden" name="start_date" id="start_date" value="{{ old('start_date') }}">
                    @error('start_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تا تاریخ</label>
                    <input type="text" id="end_date_display" placeholder="انتخاب تاریخ..."
                        class="leave-datepicker w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 cursor-pointer bg-white" readonly required>
                    <input type="hidden" name="end_date" id="end_date" value="{{ old('end_date') }}">
                    @error('end_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Time fields for hourly leave -->
                <div x-show="isHourly" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">از ساعت</label>
                    <select name="start_time" x-model="startTime" @change="calculateHours()"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب ساعت</option>
                        @for($h = 7; $h <= 19; $h++)
                            <option value="{{ sprintf('%02d:00', $h) }}">{{ sprintf('%02d:00', $h) }}</option>
                            <option value="{{ sprintf('%02d:30', $h) }}">{{ sprintf('%02d:30', $h) }}</option>
                        @endfor
                    </select>
                </div>

                <div x-show="isHourly" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تا ساعت</label>
                    <select name="end_time" x-model="endTime" @change="calculateHours()"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب ساعت</option>
                        @for($h = 7; $h <= 19; $h++)
                            <option value="{{ sprintf('%02d:00', $h) }}">{{ sprintf('%02d:00', $h) }}</option>
                            <option value="{{ sprintf('%02d:30', $h) }}">{{ sprintf('%02d:30', $h) }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Half day option -->
                <div x-show="!isHourly && startDate && startDate === endDate" x-transition class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="isHalfDay" @change="calculateDays()"
                            class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">نیم روز مرخصی</span>
                    </label>
                </div>

                <input type="hidden" name="days_count" :value="daysCount">
            </div>

            <!-- Duration display -->
            <div x-show="daysCount > 0 || hoursCount > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    <span x-show="!isHourly">مدت مرخصی: <strong class="text-gray-900" x-text="daysCount == 0.5 ? 'نیم روز' : daysCount + ' روز'"></strong></span>
                    <span x-show="isHourly">مدت مرخصی: <strong class="text-gray-900" x-text="hoursCount + ' ساعت'"></strong></span>
                </p>
            </div>
        </div>

        <!-- Details -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">جزئیات</h3>
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دلیل مرخصی</label>
                    <textarea name="reason" rows="3" placeholder="توضیحات (اختیاری)..."
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">{{ old('reason') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">جایگزین</label>
                    <select name="substitute_id" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">انتخاب کنید (اختیاری)</option>
                        @foreach($colleagues as $colleague)
                            <option value="{{ $colleague->id }}" {{ old('substitute_id') == $colleague->id ? 'selected' : '' }}>
                                {{ $colleague->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div x-show="requiresDocument" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        مدرک پیوست
                        <span x-show="requiresDocument" class="text-red-500">*</span>
                    </label>
                    <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full rounded-lg border border-gray-300 p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-gray-500">فرمت‌های مجاز: JPG, PNG, PDF - حداکثر 5 مگابایت</p>
                    @error('document')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('leave.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                ثبت درخواست
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function leaveForm() {
    return {
        leaveTypeId: '{{ old('leave_type_id', '') }}',
        startDate: '{{ old('start_date', '') }}',
        endDate: '{{ old('end_date', '') }}',
        startTime: '{{ old('start_time', '') }}',
        endTime: '{{ old('end_time', '') }}',
        isHourly: false,
        requiresDocument: false,
        isHalfDay: false,
        daysCount: 0,
        hoursCount: 0,

        onLeaveTypeChange(type) {
            this.isHourly = type.is_hourly;
            this.requiresDocument = type.requires_document;
            if (this.isHourly) {
                this.calculateHours();
            } else {
                this.calculateDays();
            }
        },

        calculateDays() {
            if (!this.startDate || !this.endDate) {
                this.daysCount = 0;
                return;
            }

            const start = new Date(this.startDate);
            const end = new Date(this.endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

            if (this.isHalfDay && this.startDate === this.endDate) {
                this.daysCount = 0.5;
            } else {
                this.daysCount = diffDays;
            }
        },

        calculateHours() {
            if (!this.startTime || !this.endTime) {
                this.hoursCount = 0;
                return;
            }

            const [startH, startM] = this.startTime.split(':').map(Number);
            const [endH, endM] = this.endTime.split(':').map(Number);

            const startMinutes = startH * 60 + startM;
            const endMinutes = endH * 60 + endM;

            this.hoursCount = Math.max(0, (endMinutes - startMinutes) / 60);
        },

        setStartDate(gregorian) {
            this.startDate = gregorian;
            document.getElementById('start_date').value = gregorian;
            this.calculateDays();
        },

        setEndDate(gregorian) {
            this.endDate = gregorian;
            document.getElementById('end_date').value = gregorian;
            this.calculateDays();
        }
    };
}

$(document).ready(function() {
    // Start Date Picker
    $('#start_date_display').persianDatepicker({
        format: 'YYYY/MM/DD',
        initialValue: false,
        autoClose: true,
        calendar: {
            persian: {
                locale: 'fa',
                showHint: true,
                leapYearMode: 'algorithmic'
            }
        },
        toolbox: {
            enabled: true,
            calendarSwitch: { enabled: false },
            todayButton: { enabled: true, text: { fa: 'امروز' } },
            submitButton: { enabled: true, text: { fa: 'تایید' } }
        },
        onSelect: function(unix) {
            const gregorian = new persianDate(unix).toCalendar('gregorian').format('YYYY-MM-DD');
            document.getElementById('start_date').value = gregorian;

            // Update Alpine
            const el = document.querySelector('[x-data]');
            if (el && el._x_dataStack) {
                el._x_dataStack[0].setStartDate(gregorian);
            }
        }
    });

    // End Date Picker
    $('#end_date_display').persianDatepicker({
        format: 'YYYY/MM/DD',
        initialValue: false,
        autoClose: true,
        calendar: {
            persian: {
                locale: 'fa',
                showHint: true,
                leapYearMode: 'algorithmic'
            }
        },
        toolbox: {
            enabled: true,
            calendarSwitch: { enabled: false },
            todayButton: { enabled: true, text: { fa: 'امروز' } },
            submitButton: { enabled: true, text: { fa: 'تایید' } }
        },
        onSelect: function(unix) {
            const gregorian = new persianDate(unix).toCalendar('gregorian').format('YYYY-MM-DD');
            document.getElementById('end_date').value = gregorian;

            // Update Alpine
            const el = document.querySelector('[x-data]');
            if (el && el._x_dataStack) {
                el._x_dataStack[0].setEndDate(gregorian);
            }
        }
    });
});
</script>
@endpush
@endsection
