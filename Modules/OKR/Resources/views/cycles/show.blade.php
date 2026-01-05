@extends('layouts.admin')
@section('page-title', $cycle->title)
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('okr.cycles.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                </a>
                <h1 class="text-xl font-bold text-gray-900">{{ $cycle->title }}</h1>
                @if($cycle->status === 'active')
                <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                @elseif($cycle->status === 'draft')
                <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">پیش‌نویس</span>
                @else
                <span class="px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">بسته شده</span>
                @endif
            </div>
            <p class="text-gray-600">{{ verta($cycle->start_date)->format('Y/m/d') }} - {{ verta($cycle->end_date)->format('Y/m/d') }}</p>
            @if($cycle->description)
            <p class="text-gray-500 mt-2">{{ $cycle->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            @can('manage-okr')
            <a href="{{ route('okr.objectives.create', ['cycle_id' => $cycle->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                هدف جدید
            </a>
            <a href="{{ route('okr.cycles.edit', $cycle) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
            @endcan
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500 mb-1">پیشرفت کل</p>
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-brand-500 rounded-full h-3 transition-all" style="width: {{ $cycle->progress }}%"></div>
                    </div>
                    <span class="text-lg font-bold text-gray-900">{{ number_format($cycle->progress, 0) }}%</span>
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">پیشرفت زمانی</p>
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-orange-500 rounded-full h-3 transition-all" style="width: {{ $cycle->elapsed_percentage }}%"></div>
                    </div>
                    <span class="text-lg font-bold text-gray-900">{{ number_format($cycle->elapsed_percentage, 0) }}%</span>
                </div>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-500 mb-1">تعداد اهداف</p>
                <p class="text-2xl font-bold text-gray-900">{{ $cycle->objectives->count() }}</p>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-500 mb-1">روز باقی‌مانده</p>
                <p class="text-2xl font-bold text-gray-900">{{ $cycle->days_remaining }}</p>
            </div>
        </div>
    </div>

    <!-- Objectives by Level -->
    @foreach(['organization' => 'اهداف سازمانی', 'team' => 'اهداف تیمی', 'individual' => 'اهداف فردی'] as $level => $levelTitle)
    @if($objectivesByLevel[$level]->count() > 0)
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-3 h-3 rounded-full {{ $level === 'organization' ? 'bg-purple-500' : ($level === 'team' ? 'bg-blue-500' : 'bg-green-500') }}"></span>
            <h3 class="font-semibold text-gray-900">{{ $levelTitle }}</h3>
            <span class="text-sm text-gray-500">({{ $objectivesByLevel[$level]->count() }})</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($objectivesByLevel[$level] as $objective)
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <a href="{{ route('okr.objectives.show', $objective) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $objective->title }}</a>
                            <span class="px-2 py-0.5 text-xs font-medium bg-{{ $objective->status_color }}-100 text-{{ $objective->status_color }}-800 rounded-full">{{ $objective->status_label }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $objective->owner->full_name }}
                            </span>
                            <span>{{ $objective->keyResults->count() }} نتیجه کلیدی</span>
                        </div>
                        <!-- Key Results Preview -->
                        @if($objective->keyResults->count() > 0)
                        <div class="mt-3 space-y-2">
                            @foreach($objective->keyResults->take(3) as $kr)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="w-2 h-2 rounded-full bg-{{ $kr->status_color }}-500"></span>
                                <span class="text-gray-600 flex-1">{{ Str::limit($kr->title, 40) }}</span>
                                <span class="text-gray-500">{{ $kr->formatted_current_value }} / {{ $kr->formatted_target_value }}</span>
                                <span class="text-{{ $kr->status_color }}-600 font-medium">{{ number_format($kr->progress, 0) }}%</span>
                            </div>
                            @endforeach
                            @if($objective->keyResults->count() > 3)
                            <p class="text-xs text-gray-400">و {{ $objective->keyResults->count() - 3 }} نتیجه کلیدی دیگر...</p>
                            @endif
                        </div>
                        @endif
                    </div>
                    <div class="text-left">
                        <div class="w-16 h-16 relative">
                            <svg class="w-16 h-16 transform -rotate-90">
                                <circle cx="32" cy="32" r="28" stroke="#e5e7eb" stroke-width="4" fill="none"/>
                                <circle cx="32" cy="32" r="28" stroke="{{ $objective->progress >= 70 ? '#22c55e' : ($objective->progress >= 40 ? '#eab308' : '#ef4444') }}" stroke-width="4" fill="none" stroke-dasharray="{{ 2 * 3.14159 * 28 }}" stroke-dashoffset="{{ 2 * 3.14159 * 28 * (1 - $objective->progress / 100) }}" stroke-linecap="round"/>
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-900">{{ number_format($objective->progress, 0) }}%</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endforeach

    @if($cycle->objectives->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز هدفی تعریف نشده</h3>
        <p class="text-gray-500 mb-4">برای این دوره اولین هدف را ایجاد کنید</p>
        @can('manage-okr')
        <a href="{{ route('okr.objectives.create', ['cycle_id' => $cycle->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            ایجاد هدف
        </a>
        @endcan
    </div>
    @endif
</div>
@endsection
