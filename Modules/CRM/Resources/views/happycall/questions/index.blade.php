@extends('layouts.admin')

@section('page-title', 'قالب سوالات HappyCall')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">قالب سوالات HappyCall</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">سوالاتی که هنگام تماس با مشتری یا تکنسین پرسیده می‌شوند</p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach([['customer', 'مشتری', $customerQuestions], ['technician', 'تکنسین', $technicianQuestions]] as [$aud, $audLabel, $list])
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">سوالات برای {{ $audLabel }}</h2>

            @if($list->count())
            <ol class="space-y-2 mb-6">
                @foreach($list as $q)
                <li class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                    <form action="{{ route('crm.happycall.questions.update', $q) }}" method="POST" class="space-y-2">
                        @csrf @method('PUT')
                        <textarea name="question_text" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">{{ $q->question_text }}</textarea>
                        <div class="flex items-center gap-2 flex-wrap">
                            <input type="number" name="sort_order" value="{{ $q->sort_order }}" min="0"
                                   class="w-20 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs" title="ترتیب">
                            <label class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" name="is_active" value="1" @checked($q->is_active) class="w-3 h-3">
                                فعال
                            </label>
                            <button class="px-2 py-1 bg-brand-600 text-white rounded text-xs hover:bg-brand-700">ذخیره</button>
                        </div>
                    </form>
                    <form action="{{ route('crm.happycall.questions.destroy', $q) }}" method="POST" class="inline mt-1" onsubmit="return confirm('حذف شود؟');">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:text-red-800">حذف</button>
                    </form>
                </li>
                @endforeach
            </ol>
            @else
            <p class="text-sm text-gray-500 mb-4">سوالی ثبت نشده.</p>
            @endif

            {{-- افزودن سوال --}}
            <form action="{{ route('crm.happycall.questions.store') }}" method="POST" class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
                @csrf
                <input type="hidden" name="audience" value="{{ $aud }}">
                <textarea name="question_text" rows="2" required placeholder="متن سوال جدید..."
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
                <div class="flex items-center gap-2">
                    <input type="number" name="sort_order" value="0" min="0" placeholder="ترتیب"
                           class="w-20 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-xs">
                    <button class="px-3 py-1.5 bg-brand-600 text-white rounded text-sm hover:bg-brand-700">افزودن سوال</button>
                </div>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endsection
