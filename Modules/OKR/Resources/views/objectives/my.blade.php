@extends('layouts.admin')
@section('page-title', 'اهداف من')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">اهداف من</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">اهدافی که مسئولیت آنها با شماست</p>
        </div>
    </div>

    <div class="grid gap-4">
        @forelse($objectives as $objective)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition" x-data="{ expanded: false }">
            <div class="p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $objective->level === 'organization' ? 'bg-purple-500' : ($objective->level === 'team' ? 'bg-blue-500' : 'bg-green-500') }}"></span>
                            <a href="{{ route('okr.objectives.show', $objective) }}" class="font-medium text-gray-900 dark:text-white hover:text-brand-600">{{ $objective->title }}</a>
                            <span class="px-2 py-0.5 text-xs font-medium bg-{{ $objective->status_color }}-100 text-{{ $objective->status_color }}-800 dark:bg-{{ $objective->status_color }}-900/30 dark:text-{{ $objective->status_color }}-400 rounded-full">{{ $objective->status_label }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-3">
                            <span>{{ $objective->cycle->title }}</span>
                            <span>{{ $objective->keyResults->count() }} نتیجه کلیدی</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="rounded-full h-2 {{ $objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $objective->progress }}%"></div>
                                </div>
                            </div>
                            <span class="text-lg font-bold {{ $objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600') }} w-16 text-left">{{ number_format($objective->progress, 0) }}%</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Expand/Collapse button -->
                        <button @click="expanded = !expanded" class="p-2 text-gray-400 hover:text-brand-600 transition" :class="{ 'rotate-180': expanded }">
                            <svg class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <a href="{{ route('okr.objectives.show', $objective) }}" class="p-2 text-gray-400 hover:text-brand-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Key Results Section -->
            <div x-show="expanded" x-collapse class="border-t border-gray-200 dark:border-gray-700">
                <div class="p-4 space-y-4">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">نتایج کلیدی:</h4>
                    @forelse($objective->keyResults as $kr)
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4" x-data="keyResultCheckin{{ $kr->id }}()">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-2 h-2 rounded-full bg-{{ $kr->status_color }}-500"></span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $kr->title }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ $kr->formatted_current_value }} از {{ $kr->formatted_target_value }}</span>
                                    <span class="px-2 py-0.5 text-xs bg-{{ $kr->status_color }}-100 text-{{ $kr->status_color }}-800 dark:bg-{{ $kr->status_color }}-900/30 dark:text-{{ $kr->status_color }}-400 rounded-full">{{ $kr->status_label }}</span>
                                </div>
                            </div>
                            <div class="text-xl font-bold {{ $kr->progress >= 70 ? 'text-green-600' : ($kr->progress >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($kr->progress, 0) }}%
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5 mb-3">
                            <div class="rounded-full h-1.5 {{ $kr->progress >= 70 ? 'bg-green-500' : ($kr->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $kr->progress }}%"></div>
                        </div>

                        <!-- Quick Check-in Form -->
                        <div x-show="!showForm" class="flex items-center gap-2">
                            <button @click="showForm = true" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                بروزرسانی
                            </button>
                            <a href="{{ route('okr.key-results.show', $kr) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-brand-600 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                جزئیات
                            </a>
                        </div>

                        <!-- Check-in Form -->
                        <form x-show="showForm" x-transition action="{{ route('okr.key-results.check-in', $kr) }}" method="POST" class="mt-3 space-y-3 border-t border-gray-200 dark:border-gray-600 pt-3">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">مقدار جدید</label>
                                    <input type="number" name="new_value" step="any" x-model="newValue"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-brand-500 focus:border-brand-500"
                                        placeholder="{{ $kr->current_value }}" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">اطمینان (٪)</label>
                                    <input type="number" name="confidence" min="0" max="100" x-model="confidence"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-brand-500 focus:border-brand-500"
                                        placeholder="{{ $kr->confidence ?? 70 }}">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">یادداشت (اختیاری)</label>
                                <textarea name="note" rows="2" x-model="note"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-brand-500 focus:border-brand-500"
                                    placeholder="توضیحات پیشرفت..."></textarea>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" :disabled="isSubmitting" class="inline-flex items-center gap-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                                    <svg x-show="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <svg x-show="isSubmitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    ثبت چک‌این
                                </button>
                                <button type="button" @click="showForm = false; resetForm()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">
                                    انصراف
                                </button>
                            </div>
                        </form>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">هنوز نتیجه کلیدی تعریف نشده</p>
                    @endforelse
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">هنوز هدفی به شما اختصاص نیافته</h3>
            <p class="text-gray-500 dark:text-gray-400">زمانی که هدفی به شما اختصاص یابد، اینجا نمایش داده می‌شود</p>
        </div>
        @endforelse
    </div>

    @if($objectives->hasPages())
    <div class="flex justify-center">{{ $objectives->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
@foreach($objectives as $objective)
    @foreach($objective->keyResults as $kr)
    function keyResultCheckin{{ $kr->id }}() {
        return {
            showForm: false,
            newValue: {{ $kr->current_value }},
            confidence: {{ $kr->confidence ?? 70 }},
            note: '',
            isSubmitting: false,
            resetForm() {
                this.newValue = {{ $kr->current_value }};
                this.confidence = {{ $kr->confidence ?? 70 }};
                this.note = '';
            }
        }
    }
    @endforeach
@endforeach
</script>
@endpush
@endsection
