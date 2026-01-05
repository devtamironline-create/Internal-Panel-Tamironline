@extends('layouts.admin')
@section('page-title', $objective->title)
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('okr.cycles.show', $objective->cycle) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                </a>
                <span class="w-2.5 h-2.5 rounded-full {{ $objective->level === 'organization' ? 'bg-purple-500' : ($objective->level === 'team' ? 'bg-blue-500' : 'bg-green-500') }}"></span>
                <span class="text-sm text-gray-500">{{ $objective->level_label }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ $objective->title }}</h1>
            <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-500">
                <span class="px-2.5 py-1 text-xs font-medium bg-{{ $objective->status_color }}-100 text-{{ $objective->status_color }}-800 rounded-full">{{ $objective->status_label }}</span>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $objective->cycle->title }}
                </span>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $objective->owner->full_name }}
                </span>
                @if($objective->team)
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    {{ $objective->team->name }}
                </span>
                @endif
            </div>
            @if($objective->description)
            <p class="text-gray-600 mt-3">{{ $objective->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            @can('manage-okr')
            <a href="{{ route('okr.key-results.create', ['objective_id' => $objective->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                نتیجه کلیدی
            </a>
            <a href="{{ route('okr.objectives.edit', $objective) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                ویرایش
            </a>
            @endcan
        </div>
    </div>

    <!-- Progress Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-900">پیشرفت کل هدف</h3>
            <span class="text-3xl font-bold {{ $objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600') }}">{{ number_format($objective->progress, 0) }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
            <div class="rounded-full h-4 transition-all {{ $objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $objective->progress }}%"></div>
        </div>
        <p class="text-sm text-gray-500 mt-2">براساس میانگین پیشرفت {{ $objective->keyResults->count() }} نتیجه کلیدی</p>
    </div>

    <!-- Key Results -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">نتایج کلیدی</h3>
            <span class="text-sm text-gray-500">{{ $objective->keyResults->count() }} نتیجه</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($objective->keyResults as $kr)
            <div class="p-4 hover:bg-gray-50 transition" x-data="{ showCheckIn: false }">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-{{ $kr->status_color }}-500"></span>
                            <a href="{{ route('okr.key-results.show', $kr) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $kr->title }}</a>
                            <span class="px-2 py-0.5 text-xs font-medium bg-{{ $kr->status_color }}-100 text-{{ $kr->status_color }}-800 rounded-full">{{ $kr->status_label }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $kr->owner->full_name }}
                            </span>
                            <span>{{ $kr->metric_type_label }}</span>
                        </div>
                        <!-- Progress Bar -->
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">{{ $kr->formatted_current_value }}</span>
                                    <span class="text-gray-400">{{ $kr->formatted_target_value }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="rounded-full h-2 bg-{{ $kr->status_color }}-500 transition-all" style="width: {{ $kr->progress }}%"></div>
                                </div>
                            </div>
                            <span class="text-lg font-bold text-{{ $kr->status_color }}-600 w-16 text-left">{{ number_format($kr->progress, 0) }}%</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($kr->owner_id === auth()->id() || auth()->user()->can('manage-okr'))
                        <button @click="showCheckIn = !showCheckIn" class="px-3 py-1.5 text-sm bg-brand-50 text-brand-600 rounded-lg hover:bg-brand-100 transition">
                            چک‌این
                        </button>
                        @endif
                        <a href="{{ route('okr.key-results.show', $kr) }}" class="p-2 text-gray-400 hover:text-brand-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Check-in Form -->
                <div x-show="showCheckIn" x-collapse class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <form action="{{ route('okr.key-results.check-in', $kr) }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">مقدار جدید *</label>
                                <input type="number" step="0.01" name="new_value" value="{{ $kr->current_value }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">درصد اطمینان</label>
                                <input type="number" min="0" max="100" name="confidence" value="{{ $kr->confidence }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
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
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">ثبت چک‌این</button>
                            <button type="button" @click="showCheckIn = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">انصراف</button>
                        </div>
                    </form>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <p class="mb-4">هنوز نتیجه کلیدی تعریف نشده</p>
                @can('manage-okr')
                <a href="{{ route('okr.key-results.create', ['objective_id' => $objective->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    افزودن نتیجه کلیدی
                </a>
                @endcan
            </div>
            @endforelse
        </div>
    </div>

    <!-- Child Objectives -->
    @if($objective->children->count() > 0)
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">اهداف زیرمجموعه</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($objective->children as $child)
            <a href="{{ route('okr.objectives.show', $child) }}" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-900">{{ $child->title }}</p>
                        <p class="text-sm text-gray-500">{{ $child->owner->full_name }}</p>
                    </div>
                    <span class="text-lg font-bold {{ $child->progress >= 70 ? 'text-green-600' : ($child->progress >= 40 ? 'text-yellow-600' : 'text-red-600') }}">{{ number_format($child->progress, 0) }}%</span>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
