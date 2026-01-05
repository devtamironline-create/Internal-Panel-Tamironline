@extends('layouts.admin')
@section('page-title', 'ویرایش تسک')
@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش تسک</h1>
            <p class="text-gray-600">{{ $task->title }}</p>
        </div>
        <a href="{{ route('tasks.show', $task) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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

    <form action="{{ route('tasks.update', $task) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Basic Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">اطلاعات اصلی</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان تسک *</label>
                    <input type="text" name="title" value="{{ old('title', $task->title) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                    <textarea name="description" rows="3"
                        class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $task->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مسئول انجام</label>
                    <select name="assigned_to" class="w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">انتخاب کنید</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" {{ old('assigned_to', $task->assigned_to) == $member->id ? 'selected' : '' }}>
                                {{ $member->full_name }}
                            </option>
                        @endforeach
                    </select>
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
                            <input type="radio" name="priority" value="{{ $value }}" class="peer sr-only" {{ old('priority', $task->priority) === $value ? 'checked' : '' }}>
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
                    <input type="text" name="due_date" value="{{ old('due_date', $task->jalali_due_date) }}"
                        placeholder="مثال: 1404/10/20"
                        class="jalali-datepicker w-full rounded-lg border-gray-300 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('tasks.show', $task) }}"
                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>
@endsection
