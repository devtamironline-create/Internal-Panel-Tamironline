@extends('layouts.admin')

@section('page-title', 'جزئیات HappyCall')

@section('main')
<div class="p-6 space-y-6 max-w-3xl">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">HappyCall — {{ $response->audienceLabel() }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                سفارش <a href="{{ route('crm.orders.show', $response->order) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $response->order?->order_code }}</a>
                · {{ $response->called_at?->format('Y-m-d H:i') }}
                · توسط {{ $response->caller?->name ?? '—' }}
            </p>
        </div>
        <a href="{{ route('crm.happycall.responses.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="mb-4 text-yellow-500 text-lg">{{ $response->ratingStars() }}</div>

        @if(!empty($response->answers))
        <div class="space-y-3 mb-4">
            @foreach($response->answers as $i => $a)
            <div class="border-b border-gray-100 dark:border-gray-700 pb-3 last:border-0">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $i + 1 }}. {{ $a['question_text'] ?? '' }}</div>
                <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap mt-1">{{ $a['answer'] ?? '—' }}</div>
            </div>
            @endforeach
        </div>
        @endif

        @if($response->note)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
            <div class="text-xs text-yellow-800 dark:text-yellow-200 font-medium mb-1">یادداشت</div>
            <p class="text-sm text-yellow-900 dark:text-yellow-100 whitespace-pre-wrap">{{ $response->note }}</p>
        </div>
        @endif

        @can('manage-crm-happycall')
        <form action="{{ route('crm.happycall.responses.destroy', $response) }}" method="POST" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" onsubmit="return confirm('حذف شود؟');">
            @csrf @method('DELETE')
            <button class="text-red-600 hover:text-red-800 text-sm">حذف این پاسخ</button>
        </form>
        @endcan
    </div>
</div>
@endsection
