@extends('layouts.admin')
@section('page-title', 'تسک‌های من')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">تسک‌های من</h1>
            <p class="text-gray-600">وظایف واگذار شده به شما</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            برد کانبان
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                    <p class="text-sm text-gray-500">کل تسک‌ها</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['in_progress'] }}</p>
                    <p class="text-sm text-gray-500">در حال انجام</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['completed'] }}</p>
                    <p class="text-sm text-gray-500">تکمیل شده</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['overdue'] }}</p>
                    <p class="text-sm text-gray-500">تاخیر دار</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Overdue Tasks Alert -->
    @if($overdueTasks->count() > 0)
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h4 class="font-medium text-red-800">{{ $overdueTasks->count() }} تسک با تاخیر</h4>
                <ul class="mt-2 space-y-1">
                    @foreach($overdueTasks as $task)
                    <li>
                        <a href="{{ route('tasks.show', $task) }}" class="text-sm text-red-700 hover:text-red-800">
                            {{ $task->title }} - ددلاین: {{ $task->jalali_due_date }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Tasks List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">لیست تسک‌ها</h3>
        </div>

        @if($tasks->count() > 0)
        <div class="divide-y divide-gray-200">
            @foreach($tasks as $task)
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Priority -->
                        <span class="w-2 h-2 rounded-full bg-{{ $task->priority_color }}-500"></span>

                        <!-- Task Info -->
                        <div>
                            <a href="{{ route('tasks.show', $task) }}" class="font-medium text-gray-900 hover:text-brand-600">
                                {{ $task->title }}
                            </a>
                            <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $task->team->icon_svg }}"/>
                                    </svg>
                                    {{ $task->team->name }}
                                </span>
                                @if($task->due_date)
                                <span class="{{ $task->is_overdue ? 'text-red-600' : '' }}">
                                    ددلاین: {{ $task->jalali_due_date }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Checklist Progress -->
                        @if($task->checklists->count() > 0)
                        @php $progress = $task->checklist_progress; @endphp
                        <div class="text-xs text-gray-500">
                            {{ $progress['completed'] }}/{{ $progress['total'] }}
                        </div>
                        @endif

                        <!-- Status Badge -->
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-{{ $task->status_color }}-100 text-{{ $task->status_color }}-700">
                            {{ $task->status_label }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="p-8 text-center text-gray-500">
            هیچ تسکی به شما واگذار نشده است
        </div>
        @endif
    </div>
</div>
@endsection
