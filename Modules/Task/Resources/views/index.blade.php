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
            <button @click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                تسک جدید
            </button>
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
                <div class="task-card bg-white rounded-lg shadow-sm p-4 cursor-pointer hover:shadow-md transition"
                    draggable="true"
                    data-task-id="{{ $task->id }}"
                    @dragstart="handleDragStart($event, {{ $task->id }})"
                    @dragend="handleDragEnd($event)"
                    @click="openTaskModal({{ $task->id }})">

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
                    <h4 class="font-medium text-gray-900 mb-2">
                        {{ $task->title }}
                    </h4>

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

    <!-- Create Task Modal -->
    <div x-show="showCreateModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen px-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-auto z-10"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100">

                <!-- Modal Header -->
                <div class="flex items-center justify-between p-5 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">تسک جدید</h3>
                    <button @click="showCreateModal = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="saveTask()" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">عنوان تسک *</label>
                        <input type="text" x-model="newTask.title" required
                            class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                            placeholder="مثال: طراحی صفحه اصلی">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                        <textarea x-model="newTask.description" rows="2"
                            class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                            placeholder="توضیحات تسک..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">مسئول انجام</label>
                            <select x-model="newTask.assigned_to"
                                class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                                <option value="">انتخاب کنید</option>
                                @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ددلاین</label>
                            <input type="text" x-model="newTask.due_date"
                                placeholder="۱۴۰۴/۱۰/۲۰"
                                class="jalali-datepicker w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">اولویت</label>
                        <div class="grid grid-cols-4 gap-2">
                            <label class="relative cursor-pointer">
                                <input type="radio" x-model="newTask.priority" value="low" class="peer sr-only">
                                <div class="flex flex-col items-center p-2 border-2 rounded-lg transition peer-checked:border-gray-500 peer-checked:bg-gray-50 hover:bg-gray-50">
                                    <span class="w-3 h-3 rounded-full bg-gray-500 mb-1"></span>
                                    <span class="text-xs">کم</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" x-model="newTask.priority" value="medium" class="peer sr-only">
                                <div class="flex flex-col items-center p-2 border-2 rounded-lg transition peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:bg-gray-50">
                                    <span class="w-3 h-3 rounded-full bg-blue-500 mb-1"></span>
                                    <span class="text-xs">متوسط</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" x-model="newTask.priority" value="high" class="peer sr-only">
                                <div class="flex flex-col items-center p-2 border-2 rounded-lg transition peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                    <span class="w-3 h-3 rounded-full bg-orange-500 mb-1"></span>
                                    <span class="text-xs">بالا</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer">
                                <input type="radio" x-model="newTask.priority" value="urgent" class="peer sr-only">
                                <div class="flex flex-col items-center p-2 border-2 rounded-lg transition peer-checked:border-red-500 peer-checked:bg-red-50 hover:bg-gray-50">
                                    <span class="w-3 h-3 rounded-full bg-red-500 mb-1"></span>
                                    <span class="text-xs">فوری</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" @click="showCreateModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            انصراف
                        </button>
                        <button type="submit" :disabled="saving"
                            class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition disabled:opacity-50">
                            <span x-show="!saving">ایجاد تسک</span>
                            <span x-show="saving">در حال ذخیره...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Task Modal -->
    <div x-show="showTaskModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="flex items-start justify-center min-h-screen px-4 py-8">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/50" @click="showTaskModal = false"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-auto z-10"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100">

                <!-- Loading State -->
                <div x-show="loadingTask" class="p-12 text-center">
                    <svg class="animate-spin h-8 w-8 mx-auto text-brand-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-gray-500">در حال بارگذاری...</p>
                </div>

                <!-- Task Content -->
                <div x-show="!loadingTask && currentTask" class="divide-y divide-gray-200">
                    <!-- Modal Header -->
                    <div class="p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 pl-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="'bg-' + currentTask.priority_color + '-100 text-' + currentTask.priority_color + '-700'"
                                        x-text="currentTask.priority_label"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="'bg-' + currentTask.status_color + '-100 text-' + currentTask.status_color + '-700'"
                                        x-text="currentTask.status_label"></span>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900" x-text="currentTask.title"></h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    ایجاد شده توسط <span x-text="currentTask.creator_name"></span>
                                </p>
                            </div>
                            <button @click="showTaskModal = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Status Change Buttons -->
                    <div class="p-5 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-2">تغییر وضعیت</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button @click="changeTaskStatus('todo')"
                                class="px-3 py-2 text-sm rounded-lg transition"
                                :class="currentTask.status === 'todo' ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'">
                                در انتظار
                            </button>
                            <button @click="changeTaskStatus('in_progress')"
                                class="px-3 py-2 text-sm rounded-lg transition"
                                :class="currentTask.status === 'in_progress' ? 'bg-yellow-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'">
                                در حال انجام
                            </button>
                            <button @click="changeTaskStatus('review')"
                                class="px-3 py-2 text-sm rounded-lg transition"
                                :class="currentTask.status === 'review' ? 'bg-purple-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'">
                                بررسی
                            </button>
                            <button @click="changeTaskStatus('done')"
                                class="px-3 py-2 text-sm rounded-lg transition"
                                :class="currentTask.status === 'done' ? 'bg-green-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'">
                                تکمیل
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="p-5" x-show="currentTask.description">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">توضیحات</h4>
                        <p class="text-gray-600 text-sm whitespace-pre-line" x-text="currentTask.description"></p>
                    </div>

                    <!-- Details Grid -->
                    <div class="p-5">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-500">مسئول</span>
                                <span class="font-medium text-gray-900" x-text="currentTask.assignee_name || 'بدون مسئول'"></span>
                            </div>
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-500">ددلاین</span>
                                <span class="font-medium" :class="currentTask.is_overdue ? 'text-red-600' : 'text-gray-900'"
                                    x-text="currentTask.due_date || '-'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Checklist -->
                    <div class="p-5" x-show="currentTask.checklists && currentTask.checklists.length > 0">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-700">چک‌لیست</h4>
                            <span class="text-xs text-gray-500" x-text="currentTask.checklist_completed + '/' + currentTask.checklist_total"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-3">
                            <div class="bg-green-500 h-1.5 rounded-full transition-all" :style="'width: ' + currentTask.checklist_percentage + '%'"></div>
                        </div>
                        <div class="space-y-2">
                            <template x-for="item in currentTask.checklists" :key="item.id">
                                <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                    <input type="checkbox" :checked="item.is_completed"
                                        @change="toggleChecklistItem(item.id)"
                                        class="rounded text-green-600 focus:ring-green-500">
                                    <span :class="item.is_completed ? 'line-through text-gray-400' : 'text-gray-700'" x-text="item.title"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Comments -->
                    <div class="p-5">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">نظرات</h4>

                        <!-- Add Comment -->
                        <div class="flex gap-2 mb-4">
                            <input type="text" x-model="newComment"
                                @keydown.enter="addComment()"
                                placeholder="نظر خود را بنویسید..."
                                class="flex-1 text-sm rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                            <button @click="addComment()" :disabled="!newComment.trim()"
                                class="px-4 py-2 bg-brand-500 text-white text-sm rounded-lg hover:bg-brand-600 disabled:opacity-50">
                                ارسال
                            </button>
                        </div>

                        <!-- Comments List -->
                        <div class="space-y-3 max-h-48 overflow-y-auto">
                            <template x-for="comment in currentTask.comments" :key="comment.id">
                                <div class="flex gap-3">
                                    <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-xs flex-shrink-0"
                                        x-text="comment.user_initial"></div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-medium text-gray-900 text-sm" x-text="comment.user_name"></span>
                                            <span class="text-xs text-gray-500" x-text="comment.created_at"></span>
                                        </div>
                                        <p class="text-gray-600 text-sm" x-text="comment.body"></p>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!currentTask.comments || currentTask.comments.length === 0" class="text-center text-gray-500 py-2 text-sm">
                                هنوز نظری ثبت نشده
                            </p>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-5 bg-gray-50 flex items-center justify-between">
                        <button @click="deleteTask()" class="text-red-600 hover:text-red-700 text-sm">
                            حذف تسک
                        </button>
                        <div class="flex items-center gap-3">
                            <a :href="'/tasks/' + currentTask.id + '/edit'" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                                ویرایش کامل
                            </a>
                            <button @click="showTaskModal = false" class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm">
                                بستن
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function kanbanBoard() {
    return {
        draggedTaskId: null,
        showCreateModal: false,
        showTaskModal: false,
        loadingTask: false,
        saving: false,
        currentTask: null,
        newComment: '',
        newTask: {
            title: '',
            description: '',
            assigned_to: '',
            due_date: '',
            priority: 'medium',
            team_id: '{{ $currentTeam?->id }}'
        },

        openCreateModal() {
            this.newTask = {
                title: '',
                description: '',
                assigned_to: '',
                due_date: '',
                priority: 'medium',
                team_id: '{{ $currentTeam?->id }}'
            };
            this.showCreateModal = true;
            this.$nextTick(() => {
                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch();
                }
            });
        },

        async saveTask() {
            if (!this.newTask.title.trim()) return;

            this.saving = true;
            try {
                const response = await fetch('/tasks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.newTask)
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'خطا در ذخیره تسک');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            } finally {
                this.saving = false;
            }
        },

        async openTaskModal(taskId) {
            this.showTaskModal = true;
            this.loadingTask = true;
            this.currentTask = null;

            try {
                const response = await fetch(`/tasks/${taskId}/json`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                this.currentTask = await response.json();
            } catch (error) {
                console.error('Error:', error);
                alert('خطا در بارگذاری تسک');
                this.showTaskModal = false;
            } finally {
                this.loadingTask = false;
            }
        },

        async changeTaskStatus(status) {
            if (!this.currentTask || this.currentTask.status === status) return;

            try {
                const response = await fetch(`/tasks/${this.currentTask.id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: status })
                });

                const data = await response.json();
                if (data.success) {
                    this.currentTask.status = status;
                    this.currentTask.status_label = data.status_label;
                    this.currentTask.status_color = data.status_color;
                    // Reload after short delay to update kanban
                    setTimeout(() => window.location.reload(), 500);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },

        async toggleChecklistItem(itemId) {
            try {
                const response = await fetch(`/tasks/checklist/${itemId}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                if (data.success) {
                    // Update local state
                    const item = this.currentTask.checklists.find(c => c.id === itemId);
                    if (item) {
                        item.is_completed = !item.is_completed;
                        this.updateChecklistProgress();
                    }
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },

        updateChecklistProgress() {
            if (!this.currentTask.checklists) return;
            const total = this.currentTask.checklists.length;
            const completed = this.currentTask.checklists.filter(c => c.is_completed).length;
            this.currentTask.checklist_total = total;
            this.currentTask.checklist_completed = completed;
            this.currentTask.checklist_percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
        },

        async addComment() {
            if (!this.newComment.trim() || !this.currentTask) return;

            try {
                const response = await fetch(`/tasks/${this.currentTask.id}/comment`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ body: this.newComment })
                });

                const data = await response.json();
                if (data.success) {
                    this.currentTask.comments.unshift(data.comment);
                    this.newComment = '';
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },

        async deleteTask() {
            if (!this.currentTask) return;
            if (!confirm('آیا از حذف این تسک اطمینان دارید؟')) return;

            try {
                const response = await fetch(`/tasks/${this.currentTask.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },

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
