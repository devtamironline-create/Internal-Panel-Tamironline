@extends('layouts.admin')
@section('page-title', 'تسک جدید')
@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ایجاد تسک جدید</h1>
            <p class="text-gray-600">تعریف وظیفه جدید برای تیم</p>
        </div>
        <a href="{{ route('tasks.index', ['team' => $currentTeam?->slug]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Validation Errors -->
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('tasks.store') }}" method="POST" class="space-y-6" x-data="taskForm()">
        @csrf

        <!-- Basic Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">اطلاعات اصلی</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان تسک *</label>
                    <input type="text" name="title" value="{{ old('title') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                        placeholder="مثال: طراحی صفحه اصلی" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="description" rows="3"
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500"
                        placeholder="توضیحات تسک...">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تیم *</label>
                        <select name="team_id" x-model="teamId" @change="loadTeamMembers()"
                            class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500" required>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}" {{ $currentTeam && $currentTeam->id === $team->id ? 'selected' : '' }}>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مسئول انجام</label>
                        <select name="assigned_to" class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">انتخاب کنید</option>
                            @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}" {{ old('assigned_to') == $member->id ? 'selected' : '' }}>
                                    {{ $member->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority & Due Date -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">اولویت و زمان‌بندی</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اولویت *</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['low' => ['کم', 'gray'], 'medium' => ['متوسط', 'blue'], 'high' => ['بالا', 'orange'], 'urgent' => ['فوری', 'red']] as $value => $data)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="priority" value="{{ $value }}" class="peer sr-only" {{ old('priority', 'medium') === $value ? 'checked' : '' }}>
                            <div class="flex flex-col items-center p-3 border-2 rounded-lg transition
                                peer-checked:border-{{ $data[1] }}-500 peer-checked:bg-{{ $data[1] }}-50
                                hover:bg-gray-50">
                                <span class="w-3 h-3 rounded-full bg-{{ $data[1] }}-500 mb-1"></span>
                                <span class="text-xs font-medium">{{ $data[0] }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ددلاین</label>
                    <input type="text" name="due_date" value="{{ old('due_date') }}"
                        placeholder="مثال: 1404/10/20"
                        class="jalali-datepicker w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <!-- Checklist -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">چک‌لیست</h3>
                <button type="button" @click="addChecklistItem()"
                    class="text-sm text-brand-600 hover:text-brand-700">
                    + افزودن آیتم
                </button>
            </div>

            <div class="space-y-2" id="checklist-container">
                <template x-for="(item, index) in checklistItems" :key="index">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <input type="text" :name="'checklist[' + index + ']'" x-model="item.title"
                            class="flex-1 rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500 text-sm"
                            placeholder="عنوان آیتم">
                        <button type="button" @click="removeChecklistItem(index)"
                            class="p-1 text-red-500 hover:text-red-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <p x-show="checklistItems.length === 0" class="text-sm text-gray-500 text-center py-4">
                برای افزودن چک‌لیست روی دکمه بالا کلیک کنید
            </p>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('tasks.index', ['team' => $currentTeam?->slug]) }}"
                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                ایجاد تسک
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function taskForm() {
    return {
        teamId: '{{ $currentTeam?->id }}',
        checklistItems: [],

        addChecklistItem() {
            this.checklistItems.push({ title: '' });
        },

        removeChecklistItem(index) {
            this.checklistItems.splice(index, 1);
        },

        loadTeamMembers() {
            // Could implement AJAX loading of team members
        }
    };
}
</script>
@endpush
@endsection
