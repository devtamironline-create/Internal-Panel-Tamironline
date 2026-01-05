@extends('layouts.admin')
@section('page-title', 'تایید مرخصی')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">تایید درخواست‌های مرخصی</h1>
        <p class="text-gray-600">بررسی و تایید/رد درخواست‌های مرخصی کارکنان</p>
    </div>

    <!-- Pending Requests -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">در انتظار تایید</h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                {{ $pendingRequests->total() }} درخواست
            </span>
        </div>

        @if($pendingRequests->count() > 0)
        <div class="divide-y divide-gray-200">
            @foreach($pendingRequests as $request)
            <div class="p-6" x-data="{ showDetails: false, showRejectForm: false }">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-brand-500 flex items-center justify-center text-white font-semibold">
                            {{ mb_substr($request->user->first_name ?? 'A', 0, 1) }}
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900">{{ $request->user->full_name }}</h4>
                            <p class="text-sm text-gray-500">{{ $request->leaveType->name }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="text-center px-3 py-1 bg-gray-100 rounded-lg">
                            <span class="block text-xs text-gray-500">از</span>
                            <span class="font-medium">{{ $request->jalali_start_date }}</span>
                        </div>
                        <div class="text-center px-3 py-1 bg-gray-100 rounded-lg">
                            <span class="block text-xs text-gray-500">تا</span>
                            <span class="font-medium">{{ $request->jalali_end_date }}</span>
                        </div>
                        <div class="text-center px-3 py-1 bg-blue-50 rounded-lg">
                            <span class="block text-xs text-blue-600">مدت</span>
                            <span class="font-medium text-blue-700">{{ $request->duration_text }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="showDetails = !showDetails" class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800">
                            جزئیات
                        </button>
                        <form action="{{ route('leave.approve', $request) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                تایید
                            </button>
                        </form>
                        <button @click="showRejectForm = !showRejectForm" class="px-4 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            رد
                        </button>
                    </div>
                </div>

                <!-- Details -->
                <div x-show="showDetails" x-transition class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">تاریخ ثبت:</span>
                            <span class="text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($request->created_at)->format('Y/m/d H:i') }}</span>
                        </div>
                        @if($request->substitute)
                        <div>
                            <span class="text-gray-500">جایگزین:</span>
                            <span class="text-gray-900">{{ $request->substitute->full_name }}</span>
                        </div>
                        @endif
                        @if($request->reason)
                        <div class="md:col-span-2">
                            <span class="text-gray-500">دلیل:</span>
                            <span class="text-gray-900">{{ $request->reason }}</span>
                        </div>
                        @endif
                        @if($request->document_path)
                        <div>
                            <a href="{{ Storage::url($request->document_path) }}" target="_blank" class="text-blue-600 hover:text-blue-700">
                                مشاهده مدرک
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Reject Form -->
                <div x-show="showRejectForm" x-transition class="mt-4 p-4 bg-red-50 rounded-lg">
                    <form action="{{ route('leave.reject', $request) }}" method="POST">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700 mb-2">دلیل رد درخواست</label>
                        <textarea name="note" rows="2" required
                            class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500"
                            placeholder="لطفا دلیل رد درخواست را وارد کنید..."></textarea>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" @click="showRejectForm = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                                انصراف
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                ثبت رد
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        @if($pendingRequests->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $pendingRequests->links() }}
        </div>
        @endif
        @else
        <div class="p-8 text-center text-gray-500">
            درخواست در انتظار تاییدی وجود ندارد
        </div>
        @endif
    </div>

    <!-- Recent Decisions -->
    @if($recentDecisions->count() > 0)
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">تصمیمات اخیر من</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارمند</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مدت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ تصمیم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($recentDecisions as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->user->full_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $request->leaveType->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $request->jalali_start_date }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->duration_text }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $request->status_color }}-100 text-{{ $request->status_color }}-800">
                                {{ $request->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($request->approved_at)->format('Y/m/d H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
