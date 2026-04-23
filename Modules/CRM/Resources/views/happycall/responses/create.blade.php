@extends('layouts.admin')

@section('page-title', 'ثبت HappyCall')

@section('main')
<div class="p-6 space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            ثبت HappyCall برای {{ $audience === 'technician' ? 'تکنسین' : 'مشتری' }}
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            سفارش <span dir="ltr">{{ $order->order_code }}</span> — {{ $order->customer_name }}
        </p>
    </div>

    {{-- تعویض مخاطب --}}
    <div class="flex items-center gap-2 text-sm">
        <a href="{{ route('crm.happycall.responses.create', ['order' => $order, 'audience' => 'customer']) }}"
           class="px-3 py-1.5 rounded-lg {{ $audience === 'customer' ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">برای مشتری</a>
        <a href="{{ route('crm.happycall.responses.create', ['order' => $order, 'audience' => 'technician']) }}"
           class="px-3 py-1.5 rounded-lg {{ $audience === 'technician' ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">برای تکنسین</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.happycall.responses.store', $order) }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="audience" value="{{ $audience }}">

            @if($questions->count())
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سوالات</h2>
                <div class="space-y-4">
                    @foreach($questions as $i => $q)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                        <div class="font-medium text-gray-900 dark:text-gray-100 text-sm mb-2">{{ $loop->iteration }}. {{ $q->question_text }}</div>
                        <input type="hidden" name="answers[{{ $i }}][question_id]" value="{{ $q->id }}">
                        <input type="hidden" name="answers[{{ $i }}][question_text]" value="{{ $q->question_text }}">
                        <textarea name="answers[{{ $i }}][answer]" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"></textarea>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm">
                برای این مخاطب سوال فعالی ثبت نشده.
                @can('manage-crm-happycall')
                <a href="{{ route('crm.happycall.questions.index') }}" class="underline">افزودن سوال</a>.
                @endcan
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">امتیاز کلی (1 تا 5)</label>
                    <select name="overall_rating" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                        <option value="">— بدون امتیاز —</option>
                        @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}">{{ $i }} ستاره</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">یادداشت کلی</label>
                <textarea name="note" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg"></textarea>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ثبت پاسخ</button>
                <a href="{{ route('crm.orders.show', $order) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
