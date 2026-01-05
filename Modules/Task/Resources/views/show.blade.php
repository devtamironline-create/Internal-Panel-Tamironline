@extends('layouts.admin')
@section('page-title', $task->title)
@section('main')
<div class="max-w-5xl mx-auto space-y-6" x-data="taskDetail()">
    <!-- Header -->
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-{{ $task->priority_color }}-100 text-{{ $task->priority_color }}-700">
                    {{ $task->priority_label }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-{{ $task->status_color }}-100 text-{{ $task->status_color }}-700">
                    {{ $task->status_label }}
                </span>
                @if($task->is_overdue)
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-red-100 text-red-700">
                    تاخیر دارد
                </span>
                @endif
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $task->title }}</h1>
            <p class="text-gray-500 mt-1">
                ایجاد شده توسط {{ $task->creator->full_name }}
                در {{ \Morilog\Jalali\Jalalian::fromDateTime($task->created_at)->format('Y/m/d H:i') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tasks.edit', $task) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                ویرایش
            </a>
            <a href="{{ route('tasks.index', ['team' => $task->team->slug]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                </svg>
                بازگشت
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Description -->
            @if($task->description)
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-3">توضیحات</h3>
                <div class="prose prose-sm max-w-none text-gray-600">
                    {!! nl2br(e($task->description)) !!}
                </div>
            </div>
            @endif

            <!-- Checklist -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">چک‌لیست</h3>
                    @php $progress = $task->checklist_progress; @endphp
                    @if($progress['total'] > 0)
                    <span class="text-sm text-gray-500">{{ $progress['completed'] }}/{{ $progress['total'] }} ({{ $progress['percentage'] }}%)</span>
                    @endif
                </div>

                @if($progress['total'] > 0)
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
                @endif

                <div class="space-y-2">
                    @foreach($task->checklists as $checklist)
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer group">
                        <input type="checkbox" {{ $checklist->is_completed ? 'checked' : '' }}
                            @change="toggleChecklist({{ $checklist->id }})"
                            class="rounded text-green-600 focus:ring-green-500">
                        <span class="{{ $checklist->is_completed ? 'line-through text-gray-400' : 'text-gray-700' }}">
                            {{ $checklist->title }}
                        </span>
                    </label>
                    @endforeach
                </div>

                <!-- Add new checklist item -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <form @submit.prevent="addChecklist()" class="flex items-center gap-2">
                        <input type="text" x-model="newChecklistTitle"
                            placeholder="افزودن آیتم جدید..."
                            class="flex-1 text-sm rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                            افزودن
                        </button>
                    </form>
                </div>
            </div>

            <!-- Comments -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">نظرات</h3>

                <!-- Add Comment -->
                <form action="{{ route('tasks.comment', $task) }}" method="POST" class="mb-6">
                    @csrf
                    <textarea name="body" rows="2" required
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                        placeholder="نظر خود را بنویسید..."></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm">
                            ارسال نظر
                        </button>
                    </div>
                </form>

                <!-- Comments List -->
                <div class="space-y-4">
                    @forelse($task->comments as $comment)
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-sm flex-shrink-0">
                            {{ mb_substr($comment->user->first_name ?? 'U', 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-900">{{ $comment->user->full_name }}</span>
                                <span class="text-xs text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($comment->created_at)->format('Y/m/d H:i') }}</span>
                            </div>
                            <p class="text-gray-600 text-sm">{{ $comment->body }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-gray-500 py-4">هنوز نظری ثبت نشده</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Change -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">تغییر وضعیت</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['todo' => 'در انتظار', 'in_progress' => 'در حال انجام', 'review' => 'بررسی', 'done' => 'تکمیل'] as $status => $label)
                    <button @click="changeStatus('{{ $status }}')"
                        class="px-3 py-2 text-sm rounded-lg transition
                            {{ $task->status === $status ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            <!-- Details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">جزئیات</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">تیم</dt>
                        <dd class="font-medium text-gray-900">{{ $task->team->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">مسئول</dt>
                        <dd class="font-medium text-gray-900">{{ $task->assignee?->full_name ?? 'بدون مسئول' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ددلاین</dt>
                        <dd class="font-medium {{ $task->is_overdue ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $task->jalali_due_date ?? '-' }}
                        </dd>
                    </div>
                    @if($task->started_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">شروع</dt>
                        <dd class="font-medium text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($task->started_at)->format('Y/m/d') }}</dd>
                    </div>
                    @endif
                    @if($task->completed_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">پایان</dt>
                        <dd class="font-medium text-green-600">{{ \Morilog\Jalali\Jalalian::fromDateTime($task->completed_at)->format('Y/m/d') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Activity Log -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">تاریخچه فعالیت</h3>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    @foreach($task->activities->take(10) as $activity)
                    <div class="flex items-start gap-2 text-xs">
                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activity->action_icon }}"/>
                        </svg>
                        <div>
                            <span class="text-gray-900">{{ $activity->user->first_name }}</span>
                            <span class="text-gray-500">{{ $activity->description ?? $activity->action_label }}</span>
                            <span class="block text-gray-400">{{ \Morilog\Jalali\Jalalian::fromDateTime($activity->created_at)->format('m/d H:i') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Delete -->
            <form action="{{ route('tasks.destroy', $task) }}" method="POST"
                onsubmit="return confirm('آیا از حذف این تسک اطمینان دارید؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-sm">
                    حذف تسک
                </button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function taskDetail() {
    return {
        newChecklistTitle: '',

        changeStatus(status) {
            fetch('{{ route('tasks.status', $task) }}', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        },

        toggleChecklist(id) {
            fetch(`/tasks/checklist/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        },

        addChecklist() {
            if (!this.newChecklistTitle.trim()) return;

            fetch('{{ route('tasks.checklist.add', $task) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ title: this.newChecklistTitle })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        }
    };
}
</script>
@endpush
@endsection
