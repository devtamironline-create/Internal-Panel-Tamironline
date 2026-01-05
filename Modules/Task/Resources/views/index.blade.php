@extends('layouts.admin')
@section('page-title', 'مدیریت تسک‌ها')
@section('main')
<div class="space-y-6" x-data="kanbanBoard()">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مدیریت تسک‌ها</h1>
            <p class="text-gray-600">برد کانبان برای مدیریت وظایف</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.my') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                تسک‌های من
            </a>
            @if($currentTeam)
            <a href="{{ route('tasks.create', ['team' => $currentTeam->slug]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                تسک جدید
            </a>
            @endif
        </div>
    </div>

    <!-- Team Tabs -->
    <div class="bg-white rounded-xl shadow-sm p-2">
        <div class="flex flex-wrap gap-2">
            @foreach($teams as $team)
            <a href="{{ route('tasks.index', ['team' => $team->slug]) }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg transition
                    {{ $currentTeam && $currentTeam->id === $team->id ? 'bg-'.$team->color.'-100 text-'.$team->color.'-700' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $team->icon_svg }}"/>
                </svg>
                {{ $team->name }}
                <span class="text-xs bg-{{ $currentTeam && $currentTeam->id === $team->id ? $team->color.'-200' : 'gray-200' }} px-2 py-0.5 rounded-full">
                    {{ $team->tasks()->whereNotIn('status', ['done'])->count() }}
                </span>
            </a>
            @endforeach
        </div>
    </div>

    @if($currentTeam)
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form method="GET" action="{{ route('tasks.index') }}" class="flex flex-wrap items-center gap-4">
            <input type="hidden" name="team" value="{{ $currentTeam->slug }}">

            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">مسئول:</label>
                <select name="assignee" onchange="this.form.submit()" class="text-sm rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">همه</option>
                    @foreach($teamMembers as $member)
                        <option value="{{ $member->id }}" {{ request('assignee') == $member->id ? 'selected' : '' }}>
                            {{ $member->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">اولویت:</label>
                <select name="priority" onchange="this.form.submit()" class="text-sm rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">همه</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>فوری</option>
                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>متوسط</option>
                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>کم</option>
                </select>
            </div>

            @if(request('assignee') || request('priority'))
            <a href="{{ route('tasks.index', ['team' => $currentTeam->slug]) }}" class="text-sm text-red-600 hover:text-red-700">
                پاک کردن فیلترها
            </a>
            @endif
        </form>
    </div>

    <!-- Kanban Board -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($columns as $status => $column)
        <div class="bg-gray-100 rounded-xl p-4 min-h-[500px]"
            x-data="{ dragOver: false }"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop="handleDrop($event, '{{ $status }}')"
            :class="{ 'ring-2 ring-brand-500 ring-opacity-50': dragOver }">

            <!-- Column Header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-{{ $column['color'] }}-500"></span>
                    <h3 class="font-bold text-gray-900">{{ $column['label'] }}</h3>
                </div>
                <span class="text-sm text-gray-500">{{ $column['tasks']->count() }}</span>
            </div>

            <!-- Tasks -->
            <div class="space-y-3" id="column-{{ $status }}">
                @foreach($column['tasks'] as $task)
                <div class="task-card bg-white rounded-lg shadow-sm p-4 cursor-move hover:shadow-md transition"
                    draggable="true"
                    data-task-id="{{ $task->id }}"
                    @dragstart="handleDragStart($event, {{ $task->id }})"
                    @dragend="handleDragEnd($event)">

                    <!-- Priority Badge -->
                    <div class="flex items-center justify-between mb-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $task->priority_color }}-100 text-{{ $task->priority_color }}-700">
                            {{ $task->priority_label }}
                        </span>
                        @if($task->is_overdue)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                            تاخیر
                        </span>
                        @endif
                    </div>

                    <!-- Title -->
                    <a href="{{ route('tasks.show', $task) }}" class="block font-medium text-gray-900 hover:text-brand-600 mb-2">
                        {{ $task->title }}
                    </a>

                    <!-- Checklist Progress -->
                    @if($task->checklists->count() > 0)
                    @php $progress = $task->checklist_progress; @endphp
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>چک‌لیست</span>
                            <span>{{ $progress['completed'] }}/{{ $progress['total'] }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $progress['percentage'] }}%"></div>
                        </div>
                    </div>
                    @endif

                    <!-- Footer -->
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                        <!-- Assignee -->
                        @if($task->assignee)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-brand-500 flex items-center justify-center text-white text-xs">
                                {{ mb_substr($task->assignee->first_name ?? 'U', 0, 1) }}
                            </div>
                            <span class="text-xs text-gray-500">{{ $task->assignee->first_name }}</span>
                        </div>
                        @else
                        <span class="text-xs text-gray-400">بدون مسئول</span>
                        @endif

                        <!-- Due Date -->
                        @if($task->due_date)
                        <span class="text-xs {{ $task->is_overdue ? 'text-red-600' : 'text-gray-500' }}">
                            {{ $task->jalali_due_date }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm p-8 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-gray-500">هیچ تیمی وجود ندارد</p>
    </div>
    @endif
</div>

@push('scripts')
<script>
function kanbanBoard() {
    return {
        draggedTaskId: null,

        handleDragStart(event, taskId) {
            this.draggedTaskId = taskId;
            event.target.classList.add('opacity-50');
            event.dataTransfer.effectAllowed = 'move';
        },

        handleDragEnd(event) {
            event.target.classList.remove('opacity-50');
            this.draggedTaskId = null;
        },

        handleDrop(event, newStatus) {
            event.preventDefault();
            event.currentTarget.querySelector('[x-data]').__x.$data.dragOver = false;

            if (!this.draggedTaskId) return;

            // Update task status via AJAX
            fetch(`/tasks/${this.draggedTaskId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to reflect changes
                    window.location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    };
}
</script>
@endpush
@endsection
