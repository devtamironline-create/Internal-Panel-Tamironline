@extends('layouts.admin')
@section('page-title', 'اهداف من')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">اهداف من</h1>
            <p class="text-gray-600 mt-1">اهدافی که مسئولیت آنها با شماست</p>
        </div>
    </div>

    <div class="grid gap-4">
        @forelse($objectives as $objective)
        <div class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $objective->level === 'organization' ? 'bg-purple-500' : ($objective->level === 'team' ? 'bg-blue-500' : 'bg-green-500') }}"></span>
                        <a href="{{ route('okr.objectives.show', $objective) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ $objective->title }}</a>
                        <span class="px-2 py-0.5 text-xs font-medium bg-{{ $objective->status_color }}-100 text-{{ $objective->status_color }}-800 rounded-full">{{ $objective->status_label }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
                        <span>{{ $objective->cycle->title }}</span>
                        <span>{{ $objective->keyResults->count() }} نتیجه کلیدی</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="rounded-full h-2 {{ $objective->progress >= 70 ? 'bg-green-500' : ($objective->progress >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $objective->progress }}%"></div>
                            </div>
                        </div>
                        <span class="text-lg font-bold {{ $objective->progress >= 70 ? 'text-green-600' : ($objective->progress >= 40 ? 'text-yellow-600' : 'text-red-600') }} w-16 text-left">{{ number_format($objective->progress, 0) }}%</span>
                    </div>
                </div>
                <a href="{{ route('okr.objectives.show', $objective) }}" class="p-2 text-gray-400 hover:text-brand-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز هدفی به شما اختصاص نیافته</h3>
            <p class="text-gray-500">زمانی که هدفی به شما اختصاص یابد، اینجا نمایش داده می‌شود</p>
        </div>
        @endforelse
    </div>

    @if($objectives->hasPages())
    <div class="flex justify-center">{{ $objectives->links() }}</div>
    @endif
</div>
@endsection
