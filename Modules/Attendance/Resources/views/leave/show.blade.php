@extends('layouts.admin')
@section('page-title', 'جزئیات مرخصی')
@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">جزئیات درخواست مرخصی</h1>
        </div>
        <a href="{{ route('leave.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Status Banner -->
    <div class="p-4 rounded-xl bg-{{ $leaveRequest->status_color }}-50 border border-{{ $leaveRequest->status_color }}-200">
        <div class="flex items-center gap-3">
            @if($leaveRequest->status === 'approved')
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @elseif($leaveRequest->status === 'rejected')
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @elseif($leaveRequest->status === 'pending')
                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @else
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            @endif
            <span class="font-medium text-{{ $leaveRequest->status_color }}-800">{{ $leaveRequest->status_label }}</span>
        </div>
    </div>

    <!-- Request Details -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-{{ $leaveRequest->leaveType->type_color }}-100">
                    <svg class="w-6 h-6 text-{{ $leaveRequest->leaveType->type_color }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $leaveRequest->leaveType->type_icon }}"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $leaveRequest->leaveType->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $leaveRequest->duration_text }}</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm text-gray-500">درخواست دهنده</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->user->full_name }}</dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500">تاریخ ثبت</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">
                        {{ \Morilog\Jalali\Jalalian::fromDateTime($leaveRequest->created_at)->format('Y/m/d H:i') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500">از تاریخ</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->jalali_start_date }}</dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500">تا تاریخ</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->jalali_end_date }}</dd>
                </div>

                @if($leaveRequest->start_time)
                <div>
                    <dt class="text-sm text-gray-500">از ساعت</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->start_time }}</dd>
                </div>
                @endif

                @if($leaveRequest->end_time)
                <div>
                    <dt class="text-sm text-gray-500">تا ساعت</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->end_time }}</dd>
                </div>
                @endif

                @if($leaveRequest->substitute)
                <div>
                    <dt class="text-sm text-gray-500">جایگزین</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->substitute->full_name }}</dd>
                </div>
                @endif

                @if($leaveRequest->reason)
                <div class="md:col-span-2">
                    <dt class="text-sm text-gray-500">دلیل</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $leaveRequest->reason }}</dd>
                </div>
                @endif

                @if($leaveRequest->document_path)
                <div class="md:col-span-2">
                    <dt class="text-sm text-gray-500">مدرک پیوست</dt>
                    <dd class="mt-1">
                        <a href="{{ Storage::url($leaveRequest->document_path) }}" target="_blank"
                            class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            دانلود مدرک
                        </a>
                    </dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    <!-- Approval Info -->
    @if($leaveRequest->approved_by)
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h4 class="text-sm font-bold text-gray-900 mb-4">اطلاعات تایید</h4>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm text-gray-500">تایید کننده</dt>
                <dd class="mt-1 text-sm font-medium text-gray-900">{{ $leaveRequest->approver->full_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">تاریخ تایید</dt>
                <dd class="mt-1 text-sm font-medium text-gray-900">
                    {{ \Morilog\Jalali\Jalalian::fromDateTime($leaveRequest->approved_at)->format('Y/m/d H:i') }}
                </dd>
            </div>
            @if($leaveRequest->approval_note)
            <div class="md:col-span-2">
                <dt class="text-sm text-gray-500">یادداشت</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $leaveRequest->approval_note }}</dd>
            </div>
            @endif
        </dl>
    </div>
    @endif

    <!-- Actions -->
    @if($leaveRequest->status === 'pending' && $leaveRequest->user_id === auth()->id())
    <div class="flex justify-end">
        <form action="{{ route('leave.cancel', $leaveRequest) }}" method="POST"
            onsubmit="return confirm('آیا از لغو این درخواست اطمینان دارید؟')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                لغو درخواست
            </button>
        </form>
    </div>
    @endif
</div>
@endsection
