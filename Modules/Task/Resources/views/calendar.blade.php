@extends('layouts.admin')
@section('page-title', 'تقویم پروژه')
@section('main')
@php
    $today = \Morilog\Jalali\Jalalian::now();
    $todayDay = (int) $today->getDay();
    $todayMonth = (int) $today->getMonth();
    $todayYear = (int) $today->getYear();
    $weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">تقویم پروژه</h1>
            <p class="text-gray-600 dark:text-gray-400">نمایش تسک‌ها بر اساس ددلاین</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.index', $currentTeam ? ['team' => $currentTeam->slug] : []) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
                کانبان
            </a>
        </div>
    </div>

    <!-- Team Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-2">
        <div class="flex flex-wrap gap-2">
            @foreach($teams as $team)
            <a href="{{ route('tasks.calendar', ['team' => $team->slug, 'year' => $jYear, 'month' => $jMonth]) }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg transition
                    {{ $currentTeam && $currentTeam->id === $team->id ? 'bg-'.$team->color.'-100 dark:bg-'.$team->color.'-900/30 text-'.$team->color.'-700 dark:text-'.$team->color.'-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                {{ $team->name }}
            </a>
            @endforeach
        </div>
    </div>

    <!-- Month Navigation -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <a href="{{ route('tasks.calendar', ['team' => $currentTeam?->slug, 'year' => $prevYear, 'month' => $prevMonth]) }}"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            <div class="text-center">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $monthNames[$jMonth] }} {{ $jYear }}</h2>
                @if($currentTeam)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $currentTeam->name }}</p>
                @endif
            </div>

            <a href="{{ route('tasks.calendar', ['team' => $currentTeam?->slug, 'year' => $nextYear, 'month' => $nextMonth]) }}"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
        </div>

        @if($jMonth !== $todayMonth || $jYear !== $todayYear)
        <div class="text-center mt-2">
            <a href="{{ route('tasks.calendar', ['team' => $currentTeam?->slug]) }}" class="text-sm text-brand-500 hover:text-brand-600">بازگشت به امروز</a>
        </div>
        @endif
    </div>

    <!-- Calendar Grid -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <!-- Week Days Header -->
        <div class="grid grid-cols-7 bg-gray-50 dark:bg-gray-700">
            @foreach($weekDays as $day)
            <div class="py-3 text-center text-sm font-bold {{ $day === 'ج' ? 'text-red-500' : 'text-gray-600 dark:text-gray-400' }}">
                {{ $day }}
            </div>
            @endforeach
        </div>

        <!-- Days Grid -->
        <div class="grid grid-cols-7 border-t border-gray-200 dark:border-gray-700">
            {{-- خانه‌های خالی قبل از روز اول --}}
            @for($i = 0; $i < $firstDayOfWeek; $i++)
            <div class="min-h-[100px] border-b border-l border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50"></div>
            @endfor

            {{-- روزهای ماه --}}
            @for($day = 1; $day <= $daysInMonth; $day++)
            @php
                $isToday = ($day === $todayDay && $jMonth === $todayMonth && $jYear === $todayYear);
                $isFriday = (($firstDayOfWeek + $day - 1) % 7 === 6);
                $dayTasks = $tasksByDay[$day] ?? [];
            @endphp
            <div class="min-h-[100px] border-b border-l border-gray-100 dark:border-gray-700 p-1.5 {{ $isToday ? 'bg-brand-50/50 dark:bg-brand-900/10' : '' }} {{ $isFriday ? 'bg-red-50/30 dark:bg-red-900/5' : '' }}">
                {{-- شماره روز --}}
                <div class="flex items-center justify-between mb-1">
                    <span class="inline-flex items-center justify-center w-7 h-7 text-sm font-bold rounded-full {{ $isToday ? 'bg-brand-500 text-white' : ($isFriday ? 'text-red-500' : 'text-gray-700 dark:text-gray-300') }}">
                        {{ $day }}
                    </span>
                    @if(count($dayTasks) > 0)
                    <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ count($dayTasks) }} تسک</span>
                    @endif
                </div>

                {{-- تسک‌های این روز --}}
                <div class="space-y-1">
                    @foreach(array_slice($dayTasks, 0, 3) as $task)
                    <a href="{{ route('tasks.show', $task) }}"
                        class="block px-2 py-1 rounded-md text-[11px] font-medium truncate transition hover:opacity-80
                            {{ $task->status === 'done' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 line-through' : 'bg-'.$task->priority_color.'-100 dark:bg-'.$task->priority_color.'-900/30 text-'.$task->priority_color.'-700 dark:text-'.$task->priority_color.'-400' }}"
                        title="{{ $task->title }} — {{ $task->assignee?->full_name ?? 'بدون مسئول' }}">
                        {{ $task->title }}
                    </a>
                    @endforeach
                    @if(count($dayTasks) > 3)
                    <span class="block text-center text-[10px] text-gray-400 dark:text-gray-500">+{{ count($dayTasks) - 3 }} مورد دیگر</span>
                    @endif
                </div>
            </div>
            @endfor

            {{-- خانه‌های خالی بعد از آخرین روز --}}
            @php $remainingCells = 7 - (($firstDayOfWeek + $daysInMonth) % 7); @endphp
            @if($remainingCells < 7)
            @for($i = 0; $i < $remainingCells; $i++)
            <div class="min-h-[100px] border-b border-l border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50"></div>
            @endfor
            @endif
        </div>
    </div>

    <!-- Legend -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span class="font-medium">راهنما:</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-100 dark:bg-gray-700 border"></span> کم</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-100 dark:bg-blue-900/30"></span> متوسط</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-100 dark:bg-orange-900/30"></span> بالا</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 dark:bg-red-900/30"></span> فوری</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-100 dark:bg-green-900/30"></span> تکمیل شده</span>
        </div>
    </div>
</div>
@endsection
