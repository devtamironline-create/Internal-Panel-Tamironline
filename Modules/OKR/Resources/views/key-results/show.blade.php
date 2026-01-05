@extends('layouts.admin')
@section('page-title', $keyResult->title)
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('okr.objectives.show', $keyResult->objective) }}" class="hover:text-brand-600">{{ $keyResult->objective->title }}</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-3 h-3 rounded-full bg-{{ $keyResult->status_color }}-500"></span>
                <h1 class="text-xl font-bold text-gray-900">{{ $keyResult->title }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                <span class="px-2.5 py-1 text-xs font-medium bg-{{ $keyResult->status_color }}-100 text-{{ $keyResult->status_color }}-800 rounded-full">{{ $keyResult->status_label }}</span>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $keyResult->owner->full_name }}
                </span>
                <span>{{ $keyResult->metric_type_label }}</span>
            </div>
            @if($keyResult->description)
            <p class="text-gray-600 mt-3">{{ $keyResult->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            @if($keyResult->owner_id === auth()->id() || auth()->user()->can('manage-okr'))
            <a href="{{ route('okr.key-results.edit', $keyResult) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
            @endif
        </div>
    </div>

    <!-- Progress Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <p class="text-sm text-gray-500 mb-2">پیشرفت</p>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-900">{{ $keyResult->formatted_current_value }}</span>
                            <span class="text-gray-400">{{ $keyResult->formatted_target_value }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="rounded-full h-4 bg-{{ $keyResult->status_color }}-500 transition-all" style="width: {{ $keyResult->progress }}%"></div>
                        </div>
                    </div>
                    <span class="text-3xl font-bold text-{{ $keyResult->status_color }}-600">{{ number_format($keyResult->progress, 0) }}%</span>
                </div>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-500 mb-1">مقدار شروع</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($keyResult->start_value) }}</p>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-500 mb-1">درصد اطمینان</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($keyResult->confidence, 0) }}%</p>
            </div>
        </div>
    </div>

    <!-- Check-in Form -->
    @if($keyResult->owner_id === auth()->id() || auth()->user()->can('manage-okr'))
    <div class="bg-white rounded-xl shadow-sm p-6" x-data="{ open: false }">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">ثبت چک‌این جدید</h3>
            <button @click="open = !open" class="text-brand-600 hover:text-brand-700 text-sm">
                <span x-show="!open">نمایش فرم</span>
                <span x-show="open">بستن</span>
            </button>
        </div>
        <form action="{{ route('okr.key-results.check-in', $keyResult) }}" method="POST" x-show="open" x-collapse class="mt-4 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار جدید *</label>
                    <input type="number" step="0.01" name="new_value" value="{{ $keyResult->current_value }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">درصد اطمینان</label>
                    <input type="number" min="0" max="100" name="confidence" value="{{ $keyResult->confidence }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت</label>
                <textarea name="note" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" placeholder="توضیحات پیشرفت..."></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">موانع</label>
                <textarea name="blockers" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" placeholder="موانع یا مشکلات پیش‌رو..."></textarea>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ثبت چک‌این</button>
        </form>
    </div>
    @endif

    <!-- Check-in History -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">تاریخچه چک‌این‌ها</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($keyResult->checkIns->sortByDesc('created_at') as $checkIn)
            <div class="p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 font-medium">
                            {{ mb_substr($checkIn->user->first_name, 0, 1) }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $checkIn->user->full_name }}</p>
                            <p class="text-sm text-gray-500">{{ $checkIn->jalali_created_at_diff }}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500">{{ number_format($checkIn->previous_value) }}</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            <span class="font-medium {{ $checkIn->isPositiveChange() ? 'text-green-600' : 'text-red-600' }}">{{ number_format($checkIn->new_value) }}</span>
                        </div>
                        @if($checkIn->change != 0)
                        <p class="text-xs {{ $checkIn->isPositiveChange() ? 'text-green-600' : 'text-red-600' }}">
                            {{ $checkIn->change > 0 ? '+' : '' }}{{ number_format($checkIn->change) }}
                            ({{ $checkIn->change_percentage > 0 ? '+' : '' }}{{ number_format($checkIn->change_percentage, 1) }}%)
                        </p>
                        @endif
                    </div>
                </div>
                @if($checkIn->note)
                <div class="mt-3 pr-13 text-sm text-gray-600">
                    <p>{{ $checkIn->note }}</p>
                </div>
                @endif
                @if($checkIn->blockers)
                <div class="mt-2 pr-13">
                    <p class="text-sm text-red-600 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        {{ $checkIn->blockers }}
                    </p>
                </div>
                @endif
            </div>
            @empty
            <div class="p-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>هنوز چک‌اینی ثبت نشده</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
